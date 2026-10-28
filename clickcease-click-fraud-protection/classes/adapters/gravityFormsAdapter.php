<?php

require_once clickcease_plugin_PLUGIN_PATH . 'classes/adapters/formGuardAdapter.php';

class GravityFormsAdapter extends FormGuardAdapter
{
    const PLUGIN_TYPE = FormPluginType::GRAVITY_FORMS;
    const FORM_CHANGE_HOOKS = [
        'gform_after_save_form',
        'gform_post_form_duplicated',
        'gform_forms_post_import',
        'gform_post_form_trashed',
        'gform_post_form_restored',
        'gform_after_delete_form',
    ];
    const SUBMISSION_HOOK_ARGS = 2;
    const SPAM_STATUS = 'spam';

    public function register_hooks()
    {
        $this->add_submission_hook('gform_after_submission', self::SUBMISSION_HOOK_ARGS);
        $this->add_hook('gform_entry_meta', [$this, 'register_entry_meta']);
        $this->add_hook('gform_form_tag', [$this, 'add_request_id_field']);
        $this->schedule_sync_on(self::FORM_CHANGE_HOOKS);
    }

    public function list_forms()
    {
        // Active and inactive forms, not trashed ones
        return array_map(function ($form) {
            return ['id' => $form['id'], 'name' => $form['title']];
        }, GFAPI::get_forms(null, false));
    }

    public function add_request_id_field($form_tag)
    {
        return $form_tag . $this->request_id_field_html();
    }

    public function register_entry_meta($entry_meta)
    {
        foreach (AnnotationColumns::KEYS as $meta_key) {
            $entry_meta[$meta_key] = [
                'label' => $meta_key,
                'is_numeric' => false,
                'is_default_column' => true,
            ];
        }

        return $entry_meta;
    }

    protected function read_submission($hook_args)
    {
        list($entry, $form) = $hook_args;

        // Gravity Forms passes spam entries to this hook too
        if (rgar($entry, 'status') === self::SPAM_STATUS) {
            return null;
        }

        return $this->submission(
            rgar($form, 'id'),
            $this->first_value_of_type($entry, $form, 'email'),
            $this->first_value_of_type($entry, $form, 'phone'),
            rgar($entry, 'id')
        );
    }

    protected function write_annotation($entry_id, $form_id, $annotation)
    {
        foreach ($annotation as $meta_key => $value) {
            gform_update_meta($entry_id, $meta_key, $value, $form_id);
        }
    }

    private function first_value_of_type($entry, $form, $type)
    {
        $values = array_map(function ($field) use ($entry) {
            return $field->get_value_export($entry, (string) $field->id);
        }, GFAPI::get_fields_by_type($form, [$type], true));

        return FormGuardService::first_non_empty($values);
    }
}

require_once clickcease_plugin_PLUGIN_PATH . 'classes/formGuardService.php';
require_once clickcease_plugin_PLUGIN_PATH . 'classes/enums.php';
