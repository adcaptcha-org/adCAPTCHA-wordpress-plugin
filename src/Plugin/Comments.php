<?php

namespace AdCaptcha\Plugin;

use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use AdCaptcha\Plugin\AdCaptchaPlugin;
use WP_Error;

class Comments extends AdCaptchaPlugin {

    public function setup() {
        add_action( 'comment_form', [ AdCaptcha::class, 'enqueue_scripts' ] );
        add_action( 'comment_form', [ Verify::class, 'get_success_token' ] );
        add_filter( 'comment_form_submit_field', [ $this, 'captcha_trigger_filter' ] );
        add_action( 'pre_comment_approved', [ $this, 'verify' ], 20, 2 );
    }

    public function verify( $approved, array $commentdata ) {
        $successToken = sanitize_text_field(wp_unslash($_POST['adcaptcha_successToken']));
        $verify = new Verify();
        $response = $verify->verify_token($successToken);


        if ( $response === false ) {
            $approved = new WP_Error( 'adcaptcha_error', __( 'Incomplete captcha, Please try again', 'adcaptcha' ), 400 );
        }

        return $approved;
    }

    // Renders the captcha before the submit button
    public function captcha_trigger_filter($submit_field) {
        return AdCaptcha::captcha_trigger() . $submit_field;
    }
}
