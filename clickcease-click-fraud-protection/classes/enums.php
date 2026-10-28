<?php

abstract class ErrorCodes
{
    const ERROR = 2000;
    const NO_KEYS = 2007;
    const AUTH_ERROR = 2008;
    const RTI_SERVER_AUTH_ERROR = 2009;
    const SCRIPT_NOT_INSTALLED = 2010;
    const RTI_SERVER_NO_RESPONSE = 2011;
    const RTI_SERVER_MISSING_THREAT_TYPE = 2012;
    const INCORRECT_SECRET_KEY = 2013;
    const RTI_SERVER_INCORRECT_COOKIE = 2014;
    const RTI_SERVER_EMPTY_RESPONSE_FORMAT = 2015;
    const RTI_SERVER_INVALID_RESPONSE_FORMAT = 2016;
    const RTI_SERVER_MISSING_COOKIE = 2017;
    const USER_MOVED_FROM_FORM_PAGE_MIDVALIDATION = 2018;
    const PLUGIN_INSTALL = 2019;
    const PLUGIN_REMOVE = 2020;
    const PLUGIN_START_AUTHENTICATE = 2021;
    const PLUGIN_SUCCESS_AUTHENTICATE = 2022;
    const NO_CLIENT_IP = 2023;
    const WHITELIST_TRACK = 2024;
    const TAG_VALIDATION_ERROR = 2025;
    const FORM_GUARD_CLASSIFICATION_FAILED = 2026;
    const FORM_GUARD_CLASSIFICATION_NO_KEYS = 2027;
    const FORM_GUARD_VALIDATE_FAILED = 2028;
    const CC_CORE_STATE_REPORT_FAILED = 2029;
    const FORM_GUARD_SYNC_FAILED = 2030;
    const FORM_GUARD_ADAPTER_FAILED = 2031;
}
abstract class CCGTMS_BlockedCodes
{
    const SCRAPERS = 2;
    const NETWORK_ANOMALIES = 18;
    const FALSE_REPRESENTATION = 11;
    const AUTOMATION_TOOL = 3;
    const MALICIOUS_BOTS = 10;
    const EXCESSIVE_RATE_LIMIT = 6;
    const CLICK_HIJACKING = 17;
    const DISABLED_COOKIES = 16;
    const DISABLED_JS = 7;
    const CLICK_FARM = 9;
    const MALICIOUS_USER_INPUT = 25;
}
abstract class AllowedCodes
{
    const VALID = 0;
    const FREQUENCY_CAPPING = 4;
    const ABNORMAL_RATE_LIMIT = 5;
    const DATA_CENTER = 13;
    const VPN = 14;
    const PROXY = 15;
    const CLICK_HIJACKING = 17;
    const GOOD_BOT = 19;
    const CRAWLERS = 20;
}

abstract class Urls
{
    const RTI_SERVER_EUROPE = 'https://rti-eu-west-1.cheqzone.com/v1/realtime-interception';
    const RTI_LOGGER = 'https://rtilogger.production.cheq-platform.com/';
    const BOTZAPPING = 'https://botzapping.eu.cheq-platform.com';
    const CHEQ_TAG = 'obs.sornavellon.com';
    const CLICKCEASE = 'https://www.clickcease.com';
    const CLICKCEASE_MONITORING = 'https://monitor.clickcease.com/stats';
    const CLICKCEASE_BOTZAPPING = 'https://api.clickcease.com/dashboard/api/BotZappingDomain';
    const CC_CORE = 'https://cc-core.cheq-platform.com/api';
    const HTTPS = 'https://';
}

abstract class CCCorePaths
{
    const CLASSIFICATION = '/v2/form-guard/wordpress/plugin/classification';
    const VALIDATE = '/v2/form-guard/wordpress/plugin/validate';
    const STATE = '/bot-zapping/plugin/state';
    const STATUS = '/bot-zapping/plugin/status';
    const FORMS = '/v2/form-guard/wordpress/plugin/forms';
}

abstract class HTTPMethod
{
    const GET = 'GET';
    const POST = 'POST';
    const PUT = 'PUT';
}

abstract class HTTPCode
{
    const SUCCESS = 200;
    const CREATED = 201;
    const BAD_REQUEST = 400;
    const FORBIDDEN = 403;
    const NOT_FOUND = 404;
}

abstract class ResponseMessage
{
    const INVALID_KEYS = 'Error: Please make sure you entered the right keys. Those can be found on your ClickCease Bot Zapping dashboard.';
    const INVALID_DOAMIN = 'Error: Domain key is invalid';
    const SUCCESS = 'success';
}

abstract class DomainState
{
    const BZ_PLUGIN_ACTIVATED = 2;
    const BZ_PLUGIN_DEACTIVATED = 4;
    const BZ_PLUGIN_UNINSTALLED = 5;
    const PLUGIN_ACTIVATED = 9;
    const PLUGIN_DEACTIVATED = 10;
}

abstract class SiteClassification
{
    const UNKNOWN = '';
    const ELIGIBLE = 'eligible';
    const NOT_ELIGIBLE = 'not_eligible';
}

abstract class Cookies
{
    // Set by the CHEQ tag: the visitor's RTI request id
    const CHEQ_RTI = '_cheq_rti_en';
}

// On the wire: the data.action values of cc-core's validate answer.
abstract class ValidateAction
{
    const SKIP = 'skip';
    const VALIDATED = 'validated';
}

// On the wire: must match cc-core's WordpressFormPluginType exactly.
abstract class FormPluginType
{
    const GRAVITY_FORMS = 'GravityForms';
    const WPFORMS = 'WPForms';
    const CONTACT_FORM_7 = 'ContactForm7';
}

abstract class AnnotationColumns
{
    const ACTION = 'cq_action';
    const EMAIL_VERDICT = 'cq_email_verdict';
    const PHONE_VERDICT = 'cq_phone_verdict';
    const THREAT_TYPE = 'cq_threat_type';
    const KEYS = [self::ACTION, self::EMAIL_VERDICT, self::PHONE_VERDICT, self::THREAT_TYPE];
}
