<?php

require_once clickcease_plugin_PLUGIN_PATH . 'classes/adapters/formGuardAdapter.php';

class ContactForm7Adapter extends FormGuardAdapter
{
    const PLUGIN_TYPE = FormPluginType::CONTACT_FORM_7;
    // mail_failed too: sites with broken mail are common
    const VALIDATED_STATUSES = ['mail_sent', 'mail_failed'];
    const SUBMISSION_HOOK_ARGS = 2;
    const DELETED_POST_HOOK_ARGS = 2;

    public function register_hooks()
    {
        $this->add_submission_hook('wpcf7_submit', self::SUBMISSION_HOOK_ARGS);
        // Create and copy end in a save
        $this->schedule_sync_on(['wpcf7_after_save']);
        $this->add_hook('deleted_post', [$this, 'schedule_sync_after_delete'], self::DELETED_POST_HOOK_ARGS);
        $this->add_hook('wpcf7_form_hidden_fields', [$this, 'add_request_id_field']);
        $this->add_hook('wpcf7_posted_data', [$this, 'hide_request_id_field']);
    }

    public function add_request_id_field($hidden_fields)
    {
        $hidden_fields[self::REQUEST_ID_FIELD] = '';

        return $hidden_fields;
    }

    // Keeps it out of Flamingo, CFDB7 and mail tags; FormGuardService reads it from $_POST
    public function hide_request_id_field($posted_data)
    {
        unset($posted_data[self::REQUEST_ID_FIELD]);

        return $posted_data;
    }

    public function list_forms()
    {
        return array_map(function ($contact_form) {
            return ['id' => $contact_form->id(), 'name' => $contact_form->title()];
        }, WPCF7_ContactForm::find());
    }

    public function schedule_sync_after_delete($post_id, $post)
    {
        if ($post->post_type === WPCF7_ContactForm::post_type) {
            FormGuardService::schedule_sync_forms();
        }
    }

    protected function read_submission($hook_args)
    {
        list($contact_form, $result) = $hook_args;
        $submission = WPCF7_Submission::get_instance();

        if (!isset($result['status']) || !in_array($result['status'], self::VALIDATED_STATUSES, true) || !$submission) {
            return null;
        }

        return $this->submission(
            $contact_form->id(),
            $this->first_value_of_type($contact_form, $submission, 'email'),
            $this->first_value_of_type($contact_form, $submission, 'tel')
        );
    }

    private function first_value_of_type($contact_form, $submission, $basetype)
    {
        $values = array_map(function ($form_tag) use ($submission) {
            return $submission->get_posted_data($form_tag->name);
        }, $contact_form->scan_form_tags(['basetype' => [$basetype]]));

        return FormGuardService::first_non_empty($values);
    }
}

require_once clickcease_plugin_PLUGIN_PATH . 'classes/formGuardService.php';
require_once clickcease_plugin_PLUGIN_PATH . 'classes/enums.php';
