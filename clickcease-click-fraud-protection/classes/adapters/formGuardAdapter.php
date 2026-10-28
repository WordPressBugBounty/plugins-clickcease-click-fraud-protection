<?php

abstract class FormGuardAdapter
{
    const HOOK_PRIORITY = 10;
    // After the other shutdown callbacks
    const SHUTDOWN_PRIORITY = PHP_INT_MAX;
    // Rendered empty; the CHEQ tag fills it with the visit's request id
    const REQUEST_ID_FIELD = 'cq_req_id';

    private static $pending_submissions = [];

    abstract public function register_hooks();

    abstract public function list_forms();

    public function handle_submission()
    {
        $submission = $this->read_submission(func_get_args());

        if (is_null($submission)) {
            return;
        }

        if (!self::$pending_submissions) {
            add_action('shutdown', FormGuardService::fail_open([__CLASS__, 'validate_pending_submissions']), self::SHUTDOWN_PRIORITY);
        }

        self::$pending_submissions[] = [$this, $submission];
    }

    public static function validate_pending_submissions()
    {
        // A plugin's open session would block the visitor's next request until we finish
        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // Ends the response so the visitor does not wait for cc-core
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        }

        $pending_submissions = self::$pending_submissions;
        self::$pending_submissions = [];

        foreach ($pending_submissions as $pending_submission) {
            list($adapter, $submission) = $pending_submission;
            call_user_func(FormGuardService::fail_open([$adapter, 'validate']), $submission);
        }
    }

    // Null leaves the submission alone.
    abstract protected function read_submission($hook_args);

    protected function write_annotation($entry_id, $form_id, $annotation)
    {
    }

    // add_action is an alias of add_filter
    protected function add_hook($hook, $callback, $accepted_args = 1)
    {
        add_filter($hook, FormGuardService::fail_open($callback), self::HOOK_PRIORITY, $accepted_args);
    }

    protected function add_submission_hook($hook, $accepted_args)
    {
        $this->add_hook($hook, [$this, 'handle_submission'], $accepted_args);
    }

    protected function schedule_sync_on($hooks)
    {
        foreach ($hooks as $hook) {
            $this->add_hook($hook, ['FormGuardService', 'schedule_sync_forms']);
        }
    }

    protected function request_id_field_html()
    {
        return '<input type="hidden" name="' . self::REQUEST_ID_FIELD . '" value="" />';
    }

    protected function submission($form_id, $email, $phone, $entry_id = 0)
    {
        return [
            'form_id' => $form_id,
            'email' => $email,
            'phone' => $phone,
            'entry_id' => $entry_id,
        ];
    }

    public function validate($submission)
    {
        $result = FormGuardService::validate_submission(static::PLUGIN_TYPE, $submission['form_id'], $submission['email'], $submission['phone']);
        $annotation = FormGuardService::annotation_of($result);

        if (is_null($annotation)) {
            return;
        }

        $this->write_annotation($submission['entry_id'], $submission['form_id'], array_map('sanitize_text_field', $annotation));
    }
}

require_once clickcease_plugin_PLUGIN_PATH . 'classes/formGuardService.php';
