<?php

namespace AdCaptcha\Plugin\Forminator;

use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use AdCaptcha\Plugin\AdCaptchaPlugin;

class Forms extends AdCaptchaPlugin {
    private $verify;
    private $has_captcha = false;

    public function __construct() {
        parent::__construct();
        $this->verify = new Verify();
    }

    public function setup() {
        add_action('forminator_before_form_render', [$this, 'before_form_render'], 10, 5);
        add_action('forminator_before_form_render', [AdCaptcha::class, 'enqueue_scripts'], 9);
        add_action('forminator_before_form_render', [Verify::class, 'get_success_token'], 9);
        add_filter('forminator_render_button_markup', [$this, 'captcha_trigger_filter'], 10, 2);
        add_action( 'wp_enqueue_scripts', [ $this, 'reset_captcha_script' ], 9 );
        add_filter('forminator_cform_form_is_submittable', [$this, 'verify'], 10, 3);
    }

    public function before_form_render($id, $form_type, $post_id, $form_fields, $form_settings) {
        $this->has_captcha = $this->has_adcaptcha_field($form_fields);
    }

    private function has_adcaptcha_field($form_fields) {
        foreach ($form_fields as $field) {
            if (!empty($field['type']) && $field['type'] === 'captcha' && !empty($field['captcha_provider']) && $field['captcha_provider'] === 'adcaptcha') {
                return true;
            }
        }
        return false;
    }

    public function captcha_trigger_filter($html, string $button) {
        if ($this->has_captcha) {
            return $html;
        }
        return AdCaptcha::ob_captcha_trigger() . $html;
    }


    public function reset_captcha_script() {
        add_action('wp_footer', function() {
            echo "<script>
                (function checkJQuery() {
                    if (typeof jQuery === 'undefined') {
                        setTimeout(checkJQuery, 100);
                        return;
                    }
                    jQuery(document).on('after:forminator:form:submit', function() {
                        if (window.adcap && typeof window.adcap.reset === 'function') {
                            window.adcap.reset();
                            window.adcap.successToken = '';
                            jQuery('[name=\"adcaptcha_successToken\"]').val('');
                        }   
                    });
                })();
            </script>";
        });
    }

    public function verify($can_show, $form_id, $field_data_array) {
        $successToken = sanitize_text_field(wp_unslash($_POST['adcaptcha_successToken'] ?? ''));
        if (empty($successToken)) {
            return [
                'can_submit' => false,
                'error'      => __('Please complete the AdCaptcha verification.', 'adcaptcha'),
            ];
        }
        $response = $this->verify->verify_token($successToken);
        if (!$response) {
            return [
                'can_submit' => false,
                'error'      => __('AdCaptcha verification failed. Please try again.', 'adcaptcha'),
            ];
        }
        return $can_show;
    }
}
