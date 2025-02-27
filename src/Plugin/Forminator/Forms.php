<?php

namespace AdCaptcha\Plugin\Forminator;

use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use AdCaptcha\Plugin\AdCaptchaPlugin;

class Forms extends AdCaptchaPlugin {
    private $verify;
    private $verified = false;
    private $has_captcha = false;

    public function __construct() {
        parent::__construct();
        $this->verify = new Verify();
    }

    public function setup() {
        add_action('forminator_before_form_render', [$this, 'before_form_render'], 10, 5);
        add_action('forminator_before_form_render', [AdCaptcha::class, 'enqueue_scripts']);
        add_action('forminator_before_form_render', [Verify::class, 'get_success_token']);

        add_filter('forminator_render_button_markup', [$this, 'captcha_trigger_filter'], 10, 2);
        add_filter('forminator_cform_form_is_submittable', [$this, 'verify'], 10, 3);
        add_filter('forminator_custom_form_fields', [$this, 'register_adcaptcha_field']);
    }

    public function before_form_render($id, $form_type, $post_id, $form_fields, $form_settings) {
        $this->has_captcha = $this->has_adcaptcha_field($form_fields);
    }

    public function register_adcaptcha_field($fields) {
        $fields['adcaptcha'] = [
            'field'      => 'AdCaptcha',
            'icon'       => 'dashicons-shield', 
            'position'   => 'basic',
            'class'      => 'forminator-custom-adcaptcha',
            'template'   => 'adcaptcha',
            'settings'   => [$this, 'adcaptcha_settings_template'],
        ];
        return $fields;
    }

    public function verify($can_show, $form_id, $field_data_array) {
        if ($this->verified) {
            error_log('[AdCaptcha] Already verified.');
            return $can_show;
        }
    
        $successToken = sanitize_text_field(wp_unslash($_POST['adcaptcha_successToken'] ?? ''));
        if (empty($successToken)) {
            error_log('[AdCaptcha] No token received.');
            return [
                'can_submit' => false,
                'error'      => __('Please complete the AdCaptcha verification.', 'adcaptcha'),
            ];
        }
        $response = $this->verify->verify_token($successToken);
        if (!$response) {
            error_log('[AdCaptcha] Verification failed.');
            return [
                'can_submit' => false,
                'error'      => __('AdCaptcha verification failed. Please try again.', 'adcaptcha'),
            ];
        }

        $this->verified = true;
        return $can_show;
    }

    public function captcha_trigger_filter($html, string $button) {
        if ($this->has_captcha) {
            return $html;
        }

        return AdCaptcha::ob_captcha_trigger() . $html;
    }

    private function has_adcaptcha_field($form_fields) {
        foreach ($form_fields as $field) {
            if (!empty($field['type']) && $field['type'] === 'captcha' && !empty($field['captcha_provider']) && $field['captcha_provider'] === 'adcaptcha') {
                return true;
            }
        }
        return false;
    }
}