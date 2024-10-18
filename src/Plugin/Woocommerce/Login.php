<?php

namespace AdCaptcha\Plugin\Woocommerce;

use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use AdCaptcha\Plugin\AdCaptchaPlugin;
use WP_Error;

class Login extends AdCaptchaPlugin {

    public function setup() {
        add_action( 'woocommerce_login_form', [ AdCaptcha::class, 'enqueue_scripts' ] );
        add_action( 'woocommerce_login_form', [ Verify::class, 'get_success_token' ] );
        add_action( 'woocommerce_login_form', [ AdCaptcha::class, 'captcha_trigger' ] );
        add_filter( 'woocommerce_process_login_errors', [ $this, 'verify' ], 10, 3 );
    }

    public function verify( $validation_error, $login, $password ) {
        global $adCAPTCHAWordpressLogin;
        remove_action( 'wp_authenticate_user', [ $adCAPTCHAWordpressLogin, 'verify' ], 10 );
        $successToken = sanitize_text_field(wp_unslash($_POST['adcaptcha_successToken']));
        $response = Verify::verify_token($successToken);

        if ( $response === false ) {
            $validation_error = new WP_Error('adcaptcha_error', __( 'Incomplete captcha, Please try again.', 'adcaptcha' ));
        }

        return $validation_error;
    }
}
