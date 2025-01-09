<?php

namespace AdCaptcha\Plugin;

use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use AdCaptcha\Plugin\AdCaptchaPlugin;
use WP_Error;

class Login extends AdCaptchaPlugin {
    private $verify;

    public function __construct() {
        parent::__construct();
        $this->verify = new Verify();
    }

    public function setup() {
        global $adCAPTCHAWordpressLogin;
        $adCAPTCHAWordpressLogin = $this;
        $enableSubmitButtonScript = true;
        add_action('login_enqueue_scripts', function() use ($enableSubmitButtonScript) {
            AdCaptcha::enqueue_scripts($enableSubmitButtonScript);
        });
        add_action( 'login_enqueue_scripts', [ Verify::class, 'get_success_token' ] );
        add_action( 'login_enqueue_scripts', [ $this, 'disable_safari_auto_submit' ] );
        add_action( 'login_form', [ AdCaptcha::class, 'captcha_trigger' ] );
        add_action( 'wp_authenticate_user', [ $adCAPTCHAWordpressLogin, 'verify' ], 10, 1 );
    }

    public function verify( $errors ) {
        $successToken = sanitize_text_field(wp_unslash($_POST['adcaptcha_successToken']));
        $response = $this->verify->verify_token($successToken);

        if ( $response === false ) {
            $errors = new WP_Error('adcaptcha_error', __( 'Incomplete captcha, Please try again.', 'adcaptcha' ));
        }

        return $errors;
    }

    public function disable_safari_auto_submit() {
        wp_add_inline_script( 'adcaptcha-script', 'document.addEventListener("DOMContentLoaded", function() {
                var form = document.querySelector("#loginform");
                var submitButton = document.querySelector("#wp-submit");

                if (form) {
                    if (submitButton) {
                        submitButton.disabled = true;
                    }

                    form.addEventListener("submit", function(event) {
                        if (!window.adcap.successToken) {
                            event.preventDefault();
                        }
                    });
                }
            });'
        );
    }
}
