<?php

namespace AdCaptcha\Plugin\Mailchimp;

use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use AdCaptcha\Plugin\AdCaptchaPlugin;

use MC4WP_Form;
use MC4WP_Form_Element;

class Forms extends AdCaptchaPlugin {

    public function setup() {
        add_action( 'wp_enqueue_scripts', [ AdCaptcha::class, 'enqueue_scripts' ], 9 );
        add_action( 'wp_enqueue_scripts', [ Verify::class, 'get_success_token' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'block_submission' ], 9 );
        add_filter( 'mc4wp_form_content', [ $this, 'add_hidden_input' ], 20, 3 );
        add_action( 'admin_enqueue_scripts', [ $this, 'form_preview_setup_triggers' ], 9 );
        add_filter( 'mc4wp_form_errors', [ $this, 'verify' ], 10, 2 );
        add_filter('mc4wp_form_messages', function($messages) {
            $messages = (array) $messages;
            $messages['invalid_captcha'] = [
                'type' => 'error',
                'text' => ADCAPTCHA_ERROR_MESSAGE,
            ];
            return $messages;
        });
    }

    public function add_hidden_input( string $content, MC4WP_Form $form, MC4WP_Form_Element $element ): string {
        return preg_replace(
            '/(<(input|button).*?type=(["\']?)submit(["\']?))/',
            '<input type="hidden" class="adcaptcha_successToken" name="adcaptcha_successToken">' . '$1',
            $content
		);
    }

    public function verify( $errors, MC4WP_Form $form ) {
        $successToken = sanitize_text_field(wp_unslash($_POST['adcaptcha_successToken']));
        $verify = new Verify();
        $response = $verify->verify_token($successToken);

        if ( $response === false ) {
            $errors     = (array) $errors;
            $errors[]   = 'invalid_captcha';
        }

        return  $errors;
    }

    public function block_submission() {
        $script = '
            document.addEventListener("DOMContentLoaded", function() {
                var form = document.querySelector(".mc4wp-form");
                if (form) {
                    var submitButton =[... document.querySelectorAll("[type=\'submit\']")];
                    if (submitButton) {
                        submitButton.forEach(function(submitButton) {
                            submitButton.addEventListener("click", function(event) {
                                if (!window.adcap || !window.adcap.successToken) {
                                    event.preventDefault();
                                    return false;
                                }
                            });
                        });
                    }
                }
            });';

        wp_register_script('adcaptcha-script', '', [], false, true);
        wp_localize_script('adcaptcha-script', 'adCaptchaErrorMessage', array(ADCAPTCHA_ERROR_MESSAGE));
        wp_add_inline_script('adcaptcha-script', $script);
    }

    public function form_preview_setup_triggers() {
        wp_register_script('adcaptcha-mc4wp-preview-script', null);
        wp_add_inline_script('adcaptcha-mc4wp-preview-script', 'window.onload = function() {
            if (adminpage === "mc4wp_page_mailchimp-for-wp-forms") {
                document.getElementById("mc4wp-form-content").addEventListener("change", function() {
                    document.getElementById("mc4wp-form-preview").contentWindow.adcap.setupTriggers();
                }); 
            }
        };');
        wp_enqueue_script('adcaptcha-mc4wp-preview-script');
    }
}
