<?php

class FormPluginDetector
{
    public function get_supported_plugin_types()
    {
        $plugin_types = [];

        if ($this->is_gravity_forms_supported()) {
            $plugin_types[] = FormPluginType::GRAVITY_FORMS;
        }

        if ($this->is_wpforms_supported()) {
            $plugin_types[] = FormPluginType::WPFORMS;
        }

        if ($this->is_contact_form_7_supported()) {
            $plugin_types[] = FormPluginType::CONTACT_FORM_7;
        }

        return $plugin_types;
    }

    public function is_gravity_forms_supported()
    {
        return class_exists('GFForms');
    }

    public function is_wpforms_supported()
    {
        return defined('WPFORMS_VERSION');
    }

    public function is_contact_form_7_supported()
    {
        return defined('WPCF7_VERSION') && class_exists('WPCF7_ContactForm');
    }
}

require_once clickcease_plugin_PLUGIN_PATH . 'classes/enums.php';
