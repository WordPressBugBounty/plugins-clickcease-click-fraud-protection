<?php

require_once clickcease_plugin_PLUGIN_PATH . 'classes/adapters/formGuardAdapter.php';

class WPFormsAdapter extends FormGuardAdapter
{
    const PLUGIN_TYPE = FormPluginType::WPFORMS;
    // Trash and restore are status updates
    const FORM_CHANGE_HOOKS = [
        'wpforms_create_form',
        'wpforms_save_form',
        'wpforms_form_handler_update_status',
        'wpforms_delete_form',
    ];
    const SUBMISSION_HOOK_ARGS = 4;
    const COLUMN_VALUE_HOOK_ARGS = 3;
    // Pro only: Lite stores no entries.
    const ENTRY_STORAGE_CLASS = 'WPForms_Entry_Handler';

    public function register_hooks()
    {
        $this->add_submission_hook('wpforms_process_complete', self::SUBMISSION_HOOK_ARGS);
        $this->schedule_sync_on(self::FORM_CHANGE_HOOKS);
        $this->add_hook('wpforms_display_submit_before', [$this, 'add_request_id_field']);

        if ($this->stores_entries()) {
            $this->add_hook('wpforms_entries_table_columns', [$this, 'add_entry_columns']);
            $this->add_hook('wpforms_entry_table_column_value', [$this, 'entry_column_value'], self::COLUMN_VALUE_HOOK_ARGS);
        }
    }

    public function add_request_id_field()
    {
        echo $this->request_id_field_html();
    }

    public function list_forms()
    {
        $form_handler = $this->wpforms_object('form');

        // An empty list here would delete every WPForms form in cc-core
        if (!$form_handler) {
            throw new Exception('WPForms form handler is not available');
        }

        // cap false: every form, not only the current user's; false means no forms
        $forms = $form_handler->get('', ['cap' => false]);

        return array_map(function ($form) {
            return ['id' => $form->ID, 'name' => function_exists('wpforms_get_post_title') ? wpforms_get_post_title($form) : get_the_title($form)];
        }, $forms ? $forms : []);
    }

    public function add_entry_columns($columns)
    {
        foreach (AnnotationColumns::KEYS as $meta_key) {
            $columns[$meta_key] = $meta_key;
        }

        return $columns;
    }

    public function entry_column_value($value, $entry, $column_name)
    {
        if (!in_array($column_name, AnnotationColumns::KEYS, true) || !is_object($entry) || empty($entry->entry_id)) {
            return $value;
        }

        $entry_meta = $this->wpforms_object('entry_meta');

        if (!$entry_meta) {
            return $value;
        }

        $meta = $entry_meta->get_meta([
            'entry_id' => $entry->entry_id,
            'type' => $column_name,
            'number' => 1,
        ]);

        return isset($meta[0]->data) ? esc_html($meta[0]->data) : $value;
    }

    protected function read_submission($hook_args)
    {
        list($fields, , $form_data, $entry_id) = $hook_args;

        return $this->submission(
            isset($form_data['id']) ? $form_data['id'] : '',
            $this->first_value_of_type($fields, 'email'),
            $this->first_value_of_type($fields, 'phone'),
            $entry_id
        );
    }

    protected function write_annotation($entry_id, $form_id, $annotation)
    {
        $entry_meta = $this->wpforms_object('entry_meta');

        // Lite passes entry id 0
        if (!$this->stores_entries() || intval($entry_id) <= 0 || !$entry_meta) {
            return;
        }

        foreach ($annotation as $meta_key => $value) {
            $entry_meta->add(
                [
                    'entry_id' => $entry_id,
                    'form_id' => $form_id,
                    'user_id' => 0,
                    'type' => $meta_key,
                    'data' => $value,
                ],
                'entry_meta'
            );
        }
    }

    private function stores_entries()
    {
        return class_exists(self::ENTRY_STORAGE_CLASS);
    }

    // obj() since 1.9.1; get() is deprecated but works back to 1.5.7.
    private function wpforms_object($name)
    {
        return method_exists(wpforms(), 'obj') ? wpforms()->obj($name) : wpforms()->get($name);
    }

    private function first_value_of_type($fields, $type)
    {
        $values = [];

        foreach ($fields as $field) {
            if (isset($field['type'], $field['value']) && $field['type'] === $type) {
                $values[] = $field['value'];
            }
        }

        return FormGuardService::first_non_empty($values);
    }
}

require_once clickcease_plugin_PLUGIN_PATH . 'classes/formGuardService.php';
require_once clickcease_plugin_PLUGIN_PATH . 'classes/enums.php';
