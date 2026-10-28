<?php

class FormGuardService
{
    const ADAPTER_CLASSES = [
        FormPluginType::GRAVITY_FORMS => GravityFormsAdapter::class,
        FormPluginType::WPFORMS => WPFormsAdapter::class,
        FormPluginType::CONTACT_FORM_7 => ContactForm7Adapter::class,
    ];
    const CLASSIFY_HOOK = 'cc_form_guard_classify';
    const SYNC_FORMS_HOOK = 'cc_form_guard_sync_forms';
    // cc-core's validate request limits
    const MAX_EMAIL_LENGTH = 254;
    const MAX_PHONE_LENGTH = 64;
    const MAX_REQUEST_ID_LENGTH = 128;
    const MAX_LOGGED_ERROR_LENGTH = 200;

    public static function init()
    {
        add_action(self::CLASSIFY_HOOK, ['FormGuardService', 'classify_and_schedule_sync']);
        add_action(self::SYNC_FORMS_HOOK, ['FormGuardService', 'sync_forms']);
        self::schedule_classification();

        if (!SiteClassificationService::is_eligible_site()) {
            return;
        }

        add_action('activated_plugin', self::fail_open(['FormGuardService', 'schedule_sync_forms']));

        $plugin_types = (new FormPluginDetector())->get_supported_plugin_types();

        if (!$plugin_types) {
            return;
        }

        foreach (self::create_adapters($plugin_types) as $adapter) {
            $adapter->register_hooks();
        }
    }

    /**
     * The adapters' single entry point to cc-core. Returns the envelope's data, or null whenever
     * the submission must go through untouched: cc-core unreachable, slow, erroring, or answering
     * that this site is no longer eligible, in which case the cached classification is demoted.
     */
    public static function validate_submission($plugin_type, $external_form_id, $email = '', $phone = '')
    {
        $request_id = self::read_request_id();
        $optional_fields = array_filter([
            'email' => self::drop_if_too_long(self::to_trimmed_string($email), self::MAX_EMAIL_LENGTH),
            'phone' => self::drop_if_too_long(self::to_trimmed_string($phone), self::MAX_PHONE_LENGTH),
            'requestId' => self::drop_if_too_long($request_id, self::MAX_REQUEST_ID_LENGTH),
        ], 'strlen');
        $payload = array_merge([
            'pluginType' => $plugin_type,
            'externalFormId' => (string) $external_form_id,
        ], $optional_fields);

        $response = CCCoreClient::validate($payload);

        if (SiteClassificationService::mark_not_eligible_if_rejected($response)) {
            return null;
        }

        $verdict = self::verdict_from_response($response);

        if (is_null($verdict)) {
            LogService::logPluginError(ErrorCodes::FORM_GUARD_VALIDATE_FAILED, CCCoreResponse::describe($response));
        }

        return $verdict;
    }

    public static function sync_forms()
    {
        if (!SiteClassificationService::is_eligible_site()) {
            return;
        }

        $forms = self::collect_forms(self::create_adapters((new FormPluginDetector())->get_supported_plugin_types()));

        if (is_null($forms)) {
            return;
        }

        $response = CCCoreClient::sync_forms(['forms' => $forms]);

        if (CCCoreResponse::is_success($response) || SiteClassificationService::mark_not_eligible_if_rejected($response)) {
            return;
        }

        LogService::logPluginError(ErrorCodes::FORM_GUARD_SYNC_FAILED, CCCoreResponse::describe($response));
    }

    // The form field filled by the CHEQ tag, else the cookie set by the server-side RTI check
    private static function read_request_id()
    {
        $posted = isset($_POST[FormGuardAdapter::REQUEST_ID_FIELD]) ? $_POST[FormGuardAdapter::REQUEST_ID_FIELD] : '';
        $request_id = self::to_trimmed_string($posted) !== '' ? $posted : Utils::getCookieVariable(Cookies::CHEQ_RTI);

        return sanitize_text_field(wp_unslash(self::to_trimmed_string($request_id)));
    }

    public static function classify_and_schedule_sync()
    {
        SiteClassificationService::classify();

        if (SiteClassificationService::is_eligible_site()) {
            self::schedule_sync_forms();
        }
    }

    // A WP-Cron request of its own, so nobody waits for cc-core
    public static function schedule_sync_forms()
    {
        if (wp_next_scheduled(self::SYNC_FORMS_HOOK)) {
            return;
        }

        wp_schedule_single_event(time(), self::SYNC_FORMS_HOOK);
    }

    // PHP 5.6 has no Throwable and only ever matches Exception
    public static function fail_open($callback)
    {
        return function () use ($callback) {
            $args = func_get_args();

            try {
                return call_user_func_array($callback, $args);
            } catch (Throwable $error) {
                self::log_adapter_error($error);
            } catch (Exception $error) {
                self::log_adapter_error($error);
            }

            // A filter keeps the value it was given
            return $args ? $args[0] : null;
        };
    }

    public static function annotation_of($result)
    {
        if (!isset($result['shouldAnnotate'], $result['verdict']) || $result['shouldAnnotate'] !== true || !is_array($result['verdict'])) {
            return null;
        }

        $verdict = $result['verdict'];
        $annotation = array_filter([
            AnnotationColumns::ACTION => self::to_trimmed_string(isset($verdict['action']) ? $verdict['action'] : ''),
            AnnotationColumns::EMAIL_VERDICT => self::to_trimmed_string(isset($verdict['email']['verdict']) ? $verdict['email']['verdict'] : ''),
            AnnotationColumns::PHONE_VERDICT => self::to_trimmed_string(isset($verdict['phone']['verdict']) ? $verdict['phone']['verdict'] : ''),
            AnnotationColumns::THREAT_TYPE => self::to_trimmed_string(isset($verdict['threatTypeCode']) ? $verdict['threatTypeCode'] : ''),
        ], 'strlen');

        return $annotation ? $annotation : null;
    }

    public static function first_non_empty($values)
    {
        foreach ($values as $value) {
            $value = self::to_trimmed_string($value);

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    public static function unschedule_events()
    {
        wp_clear_scheduled_hook(self::CLASSIFY_HOOK);
        wp_clear_scheduled_hook(self::SYNC_FORMS_HOOK);
    }

    /**
     * Visitor input and cookies can arrive as arrays (a multi-input field, a crafted cookie), which
     * cc-core rejects as a whole request, skipping the check. Anything non-scalar is dropped instead.
     */
    private static function to_trimmed_string($value)
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function drop_if_too_long($value, $max_length)
    {
        return mb_strlen($value, 'UTF-8') > $max_length ? '' : $value;
    }

    /**
     * cc-core wraps a successful answer in an envelope and puts the payload under data. An
     * action this plugin does not know is treated like a failure: fail open.
     */
    private static function verdict_from_response($response)
    {
        if (!CCCoreResponse::is_success($response)) {
            return null;
        }

        $body = json_decode(CCCoreResponse::body_of($response), true);
        $verdict = isset($body['data']) && is_array($body['data']) ? $body['data'] : [];
        $action = isset($verdict['action']) ? $verdict['action'] : '';

        return in_array($action, [ValidateAction::SKIP, ValidateAction::VALIDATED], true) ? $verdict : null;
    }

    // Null on any failure: a partial list would delete forms in cc-core
    private static function collect_forms($adapters)
    {
        $forms = [];

        try {
            foreach ($adapters as $adapter) {
                foreach ($adapter->list_forms() as $form) {
                    $forms[] = [
                        'pluginType' => $adapter::PLUGIN_TYPE,
                        'externalFormId' => (string) $form['id'],
                        'name' => (string) $form['name'],
                    ];
                }
            }
        } catch (Throwable $error) {
            self::log_adapter_error($error);
            return null;
        } catch (Exception $error) {
            self::log_adapter_error($error);
            return null;
        }

        return $forms;
    }

    private static function log_adapter_error($error)
    {
        // Messages can carry submitted field values
        LogService::logPluginError(ErrorCodes::FORM_GUARD_ADAPTER_FAILED, get_class($error) . ': ' . mb_substr($error->getMessage(), 0, self::MAX_LOGGED_ERROR_LENGTH, 'UTF-8'));
    }

    /**
     * Anchored on time(): a fixed hour fires every install in the field in one window.
     */
    private static function schedule_classification()
    {
        if (wp_next_scheduled(self::CLASSIFY_HOOK)) {
            return;
        }

        wp_schedule_event(time(), 'daily', self::CLASSIFY_HOOK);
    }

    private static function create_adapters($plugin_types)
    {
        $adapter_classes = array_intersect_key(self::ADAPTER_CLASSES, array_flip($plugin_types));

        return array_map(function ($adapter_class) {
            return new $adapter_class();
        }, array_values($adapter_classes));
    }
}

require_once clickcease_plugin_PLUGIN_PATH . 'classes/siteClassificationService.php';
require_once clickcease_plugin_PLUGIN_PATH . 'classes/ccCoreClient.php';
require_once clickcease_plugin_PLUGIN_PATH . 'classes/ccCoreResponse.php';
require_once clickcease_plugin_PLUGIN_PATH . 'classes/logService.php';
require_once clickcease_plugin_PLUGIN_PATH . 'classes/utils.php';
require_once clickcease_plugin_PLUGIN_PATH . 'classes/formPluginDetector.php';
require_once clickcease_plugin_PLUGIN_PATH . 'classes/adapters/gravityFormsAdapter.php';
require_once clickcease_plugin_PLUGIN_PATH . 'classes/adapters/wpFormsAdapter.php';
require_once clickcease_plugin_PLUGIN_PATH . 'classes/adapters/contactForm7Adapter.php';
require_once clickcease_plugin_PLUGIN_PATH . 'classes/enums.php';
