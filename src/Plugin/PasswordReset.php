<?php

namespace AdCaptcha\Plugin;

use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use AdCaptcha\Plugin\AdCaptchaPlugin;
use WP_Error;

class PasswordReset extends AdCaptchaPlugin {

    public function setup() {
        global $adCAPTCHAWordpressPasswordReset;
        $adCAPTCHAWordpressPasswordReset = $this;
        add_action( 'lostpassword_form', [ AdCaptcha::class, 'enqueue_scripts' ] );
        add_action( 'lostpassword_form', [ Verify::class, 'get_success_token' ] );
        add_action( 'lostpassword_form', [ AdCaptcha::class, 'captcha_trigger' ] );
        add_action( 'lostpassword_post', [ $adCAPTCHAWordpressPasswordReset, 'verify' ], 10, 1 );
    }

    public function verify( $errors ) {
        $successToken = sanitize_text_field(wp_unslash($_POST['adcaptcha_successToken']));
        $verify = new Verify();
        $response = $verify->verify_token($successToken);

        if ( !$response ) {
            $errors = new WP_Error('adcaptcha_error', __( 'Incomplete captcha, Please try again.', 'adcaptcha' ) );
        }

        return $errors;
    }
}
