<?php

namespace AdCaptcha\Plugin\GravityForms;

use GF_Field;
use GF_Fields;
use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use Exception;

if (!class_exists('GF_Field')) {
    return;
}

class Field extends GF_Field { 
    public $type = 'adcaptcha';
    private $verify;

    public function __construct($data = []) {
        parent::__construct($data);
        $this->verify = new Verify();
        $this->setup();
    }

    private function setup(): void {
        error_log('✅ Initializing AdCaptcha Field.');

        if (!class_exists('GF_Fields')) {
            error_log('❌ Gravity Forms not loaded. AdCaptcha registration skipped.');
            return;
        }

        if (GF_Fields::get('adcaptcha')) {
            error_log('⚠️ AdCaptcha field is already registered. Skipping.');
            return;
        }

        try {
            if (!GF_Fields::get('adcaptcha')) {
                GF_Fields::register($this);
                error_log('✅ AdCaptcha field registered.');
            } else {
                error_log('⚠️ AdCaptcha field already registered.');
            }
        } catch (Exception $e) {
            error_log('❌ Error registering AdCaptcha field: ' . $e->getMessage());
        }

        $this->setup_hooks();
    }

    private function setup_hooks(): void {
        add_action('wp_enqueue_scripts', [AdCaptcha::class, 'enqueue_scripts'], 9);
        add_action( 'wp_enqueue_scripts', [ Verify::class, 'get_success_token' ] );
        add_filter('gform_field_groups_form_editor', [$this, 'add_to_field_groups']);
        add_filter('gform_field_content', function ($content, $field) {
            if ($field->type === 'adcaptcha') {
                return str_replace($field->get_field_label(false, ''), '', $content);
            }
            return $content;
        }, 10, 2);
        add_filter('gform_validation', [$this, 'verify_captcha']);
    }

    public function get_field_input($form, $value = '', $entry = null) {
        $form_id = $form['id'];
        $field_id = (int) $this->id;
        error_log("ℹ️ Rendering get_field_input() for field ID: {$field_id} in form ID: {$form_id}");
        if ($this->is_form_editor()) {
            return "<div class='ginput_container'>AdCaptcha will be rendered here.</div>";
        }

        $captcha_html = AdCaptcha::ob_captcha_trigger();
        $input = "<div class='ginput_container ginput_container_adcaptcha' id='ginput_container_{$field_id}'>" .
        $captcha_html . 
        "<input type='hidden' class='adcaptcha_successToken' name='adcaptcha_successToken' id='input_{$form_id}_{$field_id}' value='' />" .
     "</div>";

        return $input;
    }

    public function get_form_editor_field_title() {
        return esc_html__('adCAPTCHA', 'adcaptcha');
    }

    public function get_form_editor_field_settings() {
        return [ 'description_setting', 'error_message_setting'];
    }

    public function get_form_editor_field_description() {
        return esc_attr__(
            'Adds an adCAPTCHA verification field to enhance security and prevent spam submissions on your forms.',
            'adcaptcha-for-forms'
        );
    }

    public function get_form_editor_field_icon() {
        return plugin_dir_url( __FILE__ ) . '../../../assets/adcaptcha_icon.png'; 
    }

    public function add_to_field_groups($field_groups): array {
        foreach ($field_groups['advanced_fields']['fields'] as $field) {
            if ($field['data-type'] === 'adcaptcha') {
                return $field_groups;
            }
        }
    
        error_log($this->get_form_editor_field_icon()); 
        $field_groups['advanced_fields']['fields'][] = [
            'data-type' => $this->type,
            'value'     => $this->get_form_editor_field_title(),
            'description' => $this->get_form_editor_field_description(),
            'label'     => $this->get_form_editor_field_title(),
            'icon'      => $this->get_form_editor_field_icon()
        ];
    
        return $field_groups;
    }

    public function verify_captcha($validation_result) {
        error_log('✅ Verifying AdCaptcha field running.');
        $form = $validation_result['form'];
        $is_valid = true;
        error_log('ℹ️ Verifying AdCaptcha field.' . print_r($form['fields'], true));
        foreach ($form['fields'] as &$field) {
            if ($field->type === 'adcaptcha') {
                $successToken = sanitize_text_field(wp_unslash($_POST['adcaptcha_successToken']));
                error_log('ℹ️ Verifying AdCaptcha field success token.' . print_r($successToken, true));
                if (!$successToken) {
                    error_log("❌ AdCaptcha token is missing.");
                    $field->failed_validation = true;
                    $field->validation_message = __('Captcha token is missing.', 'adcaptcha');
                    $is_valid = false;
                } elseif (!$this->verify->verify_token($successToken)) {
                    error_log("❌ AdCaptcha verification failed.");
                    $field->failed_validation = true;
                    $field->validation_message = __('Incomplete captcha, Please try again.', 'adcaptcha');
                    $is_valid = false;
                }
            }
        }

        $validation_result['is_valid'] = $is_valid;
        $validation_result['form'] = $form;
        return $validation_result;
    }
}