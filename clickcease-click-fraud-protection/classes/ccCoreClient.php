<?php

class CCCoreClient
{
    const CLASSIFICATION_TIMEOUT_SECONDS = 5;
    // Above cc-core's 4s cap on its UDV call, leaving time for its checks before that call.
    const VALIDATE_TIMEOUT_SECONDS = 6;
    const STATE_TIMEOUT_SECONDS = 3;
    // Fire and forget, as LogService::remote_log: the request is sent without waiting for an answer.
    const PLUGIN_STATUS_TIMEOUT_SECONDS = 0.5;
    // Never in a visitor's request
    const SYNC_FORMS_TIMEOUT_SECONDS = 10;

    public static function request_classification()
    {
        return self::request(HTTPMethod::GET, CCCorePaths::CLASSIFICATION, self::CLASSIFICATION_TIMEOUT_SECONDS);
    }

    public static function validate($payload)
    {
        return self::request(HTTPMethod::POST, CCCorePaths::VALIDATE, self::VALIDATE_TIMEOUT_SECONDS, $payload);
    }

    public static function sync_forms($payload)
    {
        return self::request(HTTPMethod::PUT, CCCorePaths::FORMS, self::SYNC_FORMS_TIMEOUT_SECONDS, $payload);
    }

    public static function report_state($state)
    {
        return self::request(HTTPMethod::POST, CCCorePaths::STATE, self::STATE_TIMEOUT_SECONDS, ['state' => intval($state)]);
    }

    public static function report_plugin_status($is_latest_version)
    {
        self::request(HTTPMethod::POST, CCCorePaths::STATUS, self::PLUGIN_STATUS_TIMEOUT_SECONDS, ['latestVersion' => (bool) $is_latest_version], false);
    }

    /**
     * The cc-core plugin guard authenticates the trio, so they are sent as headers on every call.
     */
    public static function get_credentials()
    {
        return [
            'api-key' => get_option('clickcease_api_key', ''),
            'secret-key' => get_option('clickcease_secret_key', ''),
            'tag-hash' => get_option('clickcease_domain_key', ''),
        ];
    }

    public static function has_stored_credentials()
    {
        $credentials = self::get_credentials();

        return count(array_filter($credentials)) === count($credentials);
    }

    private static function request($method, $path, $timeout, $payload = null, $blocking = true)
    {
        // The base URL is production unless CLICKCEASE_CC_CORE_URL is defined in wp-config.php
        $base_url = rtrim(defined('CLICKCEASE_CC_CORE_URL') ? CLICKCEASE_CC_CORE_URL : Urls::CC_CORE, '/');
        $request = [
            'method' => $method,
            'timeout' => $timeout,
            'blocking' => $blocking,
            'redirection' => 0,
            'headers' => array_merge(['Content-Type' => 'application/json', 'Origin' => 'plugin'], self::get_credentials()),
        ];

        if (!is_null($payload)) {
            $body = wp_json_encode($payload);

            if ($body === false) {
                return self::transport_failure('Payload could not be JSON encoded');
            }

            $request['body'] = $body;
        }

        $response = wp_remote_request($base_url . $path, $request);

        if (is_wp_error($response)) {
            return self::transport_failure($response->get_error_message());
        }

        return [
            'status' => intval(wp_remote_retrieve_response_code($response)),
            'body' => (string) wp_remote_retrieve_body($response),
        ];
    }

    private static function transport_failure($error_message)
    {
        return [
            'status' => 0,
            'body' => (string) $error_message,
        ];
    }
}

require_once clickcease_plugin_PLUGIN_PATH . 'classes/enums.php';
