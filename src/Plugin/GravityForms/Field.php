<?php

namespace AdCaptcha\Plugin\GravityForms;

use GF_Field;
use GF_Fields;
use GFAPI;
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

        if (!class_exists('GF_Fields')) {
            return;
        }

        if (GF_Fields::get('adcaptcha')) {
            return;
        }

        try {
            if (!GF_Fields::get('adcaptcha')) {
                GF_Fields::register($this);
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
        add_action('admin_head', [$this, 'custom_admin_field_icon_style']);
        add_action('admin_init', [$this, 'update_adcaptcha_label']);
    }

    public function get_field_input($form, $value = '', $entry = null) {
        $form_id = $form['id'];
        $field_id = (int) $this->id;
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

    public function update_adcaptcha_label() {
        $forms = GFAPI::get_forms();
        if (!$forms || !is_array($forms)) {
            return;
        }
        foreach ($forms as $form) {
            foreach ($form['fields'] as &$field) {
                if ($field->type === 'adcaptcha') {
                    $field->label = __('adCAPTCHA', 'adcaptcha');
                    $field['label'] = __('adCAPTCHA', 'adcaptcha');
                    $result = GFAPI::update_form($form);
                    if (is_wp_error($result)) {
                        error_log("❌ Failed to update adCAPTCHA label in Form ID {$form['id']}: " . $result->get_error_message());
                    } else {
                        error_log("✅ Successfully updated adCAPTCHA label in Form ID {$form['id']}.");
                    }
                    return;
                }
            }
        }
    }

    public function get_form_editor_field_title() {
        return esc_html__('adCAPTCHA', 'adcaptcha');
    }

    public function get_form_editor_field_settings() {
        return [ 'description_setting', 'error_message_setting'];
    }

    function custom_admin_field_icon_style() {
        echo '<style>
            #sidebar_field_info #sidebar_field_icon img {
                width: 16px !important; 
            }
        </style>';
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
        $field_groups['advanced_fields']['fields'][] = [
            'data-type' => $this->type,
            'value'     => $this->get_form_editor_field_title(),
            'label'     => $this->get_form_editor_field_title(),
        ];
    
        return $field_groups;
    }

    public function verify_captcha($validation_result) {
        $form = $validation_result['form'];
        $is_valid = $validation_result['is_valid'];
        foreach ($form['fields'] as &$field) {
            if ($field->type === 'adcaptcha') {
                $successToken = sanitize_text_field(wp_unslash($_POST['adcaptcha_successToken'] ?? ''));
    
                if (empty($successToken) || trim($successToken) === '') {
                    $field->failed_validation = true;
                    $field->validation_message = __('Incomplete CAPTCHA, Please try again.', 'adcaptcha');
                    $is_valid = false;
                } elseif (!$this->verify->verify_token($successToken)) {
                    $field->failed_validation = true;
                    $field->validation_message = __('Invalid token.', 'adcaptcha');
                    $is_valid = false;
                }
            }
        }
        $validation_result['is_valid'] = $is_valid; 
        $validation_result['form'] = $form;
        return $validation_result;
    }
}