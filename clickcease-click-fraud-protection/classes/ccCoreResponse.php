<?php

/**
 * Reads the ['status' => int, 'body' => string] result of a CCCoreClient call. Status 0 means
 * the request never completed, and body then carries the transport error message.
 */
class CCCoreResponse
{
    const DOMAIN_NOT_FOUND = 'DOMAIN_NOT_FOUND';
    const MAX_LOGGED_BODY_LENGTH = 500;

    public static function is_success($response)
    {
        return in_array(self::status_of($response), [HTTPCode::SUCCESS, HTTPCode::CREATED], true);
    }

    /**
     * What every cc-core plugin call answers for a ClickCease (CC1) site: expected, not an error.
     */
    public static function is_domain_not_found($response)
    {
        return self::status_of($response) === HTTPCode::NOT_FOUND && self::message_of($response) === self::DOMAIN_NOT_FOUND;
    }

    public static function status_of($response)
    {
        return isset($response['status']) ? intval($response['status']) : 0;
    }

    public static function body_of($response)
    {
        return isset($response['body']) && is_string($response['body']) ? $response['body'] : '';
    }

    /**
     * Error bodies carry the message at the top level; only success bodies are wrapped in data.
     */
    public static function message_of($response)
    {
        $body = json_decode(self::body_of($response), true);

        return isset($body['message']) ? $body['message'] : '';
    }

    public static function describe($response)
    {
        return json_encode([
            'status' => self::status_of($response),
            'body' => substr(self::body_of($response), 0, self::MAX_LOGGED_BODY_LENGTH),
        ]);
    }
}

require_once clickcease_plugin_PLUGIN_PATH . 'classes/enums.php';
