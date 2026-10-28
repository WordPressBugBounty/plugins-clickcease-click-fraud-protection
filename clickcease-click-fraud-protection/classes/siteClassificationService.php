<?php

class SiteClassificationService
{
    const CLASSIFICATION_OPTION = 'cc_form_guard_site_classification';
    const BOT_ZAPPING_AUTHENTICATED_OPTION = 'clickcease_bot_zapping_authenticated';
    const FORM_GUARD_NOT_ELIGIBLE = 'FORM_GUARD_NOT_ELIGIBLE';

    public static function is_eligible_site()
    {
        return self::is_bot_zapping_authenticated() && self::get_classification() === SiteClassification::ELIGIBLE;
    }

    public static function classify()
    {
        if (!self::is_bot_zapping_authenticated()) {
            return;
        }

        if (!CCCoreClient::has_stored_credentials()) {
            LogService::logPluginError(ErrorCodes::FORM_GUARD_CLASSIFICATION_NO_KEYS, self::describe_credentials(CCCoreClient::get_credentials()));
            return;
        }

        $response = CCCoreClient::request_classification();
        $classification = self::classification_from_response($response);

        if ($classification === SiteClassification::UNKNOWN) {
            LogService::logPluginError(ErrorCodes::FORM_GUARD_CLASSIFICATION_FAILED, CCCoreResponse::describe($response));
            return;
        }

        self::store_classification($classification);
    }

    /**
     * Validate answers a site that stopped being eligible with the same errors as the probe, so a
     * cancelled subscription is caught on the next submission instead of the next daily run.
     * Any other answer says nothing about eligibility and leaves the cache alone.
     */
    public static function mark_not_eligible_if_rejected($response)
    {
        if (self::classification_from_response($response) !== SiteClassification::NOT_ELIGIBLE) {
            return false;
        }

        self::store_classification(SiteClassification::NOT_ELIGIBLE);

        return true;
    }

    /**
     * Whenever Bot Zapping stops being authenticated (Deactivate, invalid keys) and on uninstall,
     * so form hooks stop at once instead of at the next classification.
     */
    public static function clear_classification()
    {
        delete_option(self::CLASSIFICATION_OPTION);
    }

    /**
     * Only two answers mean this site is not eligible: 404 with DOMAIN_NOT_FOUND (no CC2 domain
     * carries the tag hash) and 403 with FORM_GUARD_NOT_ELIGIBLE (the domain exists but is not
     * served). Status and message must both match: a bare 404 is ambiguous (the same decorator
     * answers INVALID_INPUTS), and the auth guard, which ClickCease (CC1) keys pass, also answers
     * 401 and 403, so neither says anything about the domain on its own.
     */
    private static function classification_from_response($response)
    {
        $status_code = CCCoreResponse::status_of($response);
        $body = json_decode(CCCoreResponse::body_of($response), true);

        if ($status_code === HTTPCode::SUCCESS && isset($body['data']['eligible']) && $body['data']['eligible'] === true) {
            return SiteClassification::ELIGIBLE;
        }

        $is_form_guard_not_eligible = $status_code === HTTPCode::FORBIDDEN && CCCoreResponse::message_of($response) === self::FORM_GUARD_NOT_ELIGIBLE;

        if (CCCoreResponse::is_domain_not_found($response) || $is_form_guard_not_eligible) {
            return SiteClassification::NOT_ELIGIBLE;
        }

        return SiteClassification::UNKNOWN;
    }

    private static function get_classification()
    {
        return get_option(self::CLASSIFICATION_OPTION, SiteClassification::UNKNOWN);
    }

    private static function store_classification($classification)
    {
        update_option(self::CLASSIFICATION_OPTION, $classification);
    }

    private static function is_bot_zapping_authenticated()
    {
        return (bool) get_option(self::BOT_ZAPPING_AUTHENTICATED_OPTION, '');
    }

    /**
     * Which of the trio is missing, without the values: the keys are secrets.
     */
    private static function describe_credentials($credentials)
    {
        return json_encode([
            'has_api_key' => (bool) $credentials['api-key'],
            'has_secret_key' => (bool) $credentials['secret-key'],
            'has_tag_hash' => (bool) $credentials['tag-hash'],
        ]);
    }
}

require_once clickcease_plugin_PLUGIN_PATH . 'classes/ccCoreClient.php';
require_once clickcease_plugin_PLUGIN_PATH . 'classes/ccCoreResponse.php';
require_once clickcease_plugin_PLUGIN_PATH . 'classes/logService.php';
require_once clickcease_plugin_PLUGIN_PATH . 'classes/enums.php';
