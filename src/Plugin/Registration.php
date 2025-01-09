<?php

namespace AdCaptcha\Plugin;

use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use AdCaptcha\Plugin\AdCaptchaPlugin;
use WP_Error;

class Registration extends AdCaptchaPlugin {
    private $verify;

    public function __construct() {
        parent::__construct();
        $this->verify = new Verify();
    }

    public function setup() {
        global $adCAPTCHAWordpressRegistration;
        $adCAPTCHAWordpressRegistration = $this;
        add_action( 'register_form', [ AdCaptcha::class, 'enqueue_scripts' ] );
        add_action( 'register_form', [ Verify::class, 'get_success_token' ] );
        add_action( 'register_form', [ AdCaptcha::class, 'captcha_trigger' ] );
        add_action( 'registration_errors', [ $adCAPTCHAWordpressRegistration, 'verify' ], 10, 1 );
    }

    public function verify( $errors ) {
        $successToken = sanitize_text_field(wp_unslash($_POST['adcaptcha_successToken']));
        $response = $this->verify->verify_token($successToken);


        if ( $response === false ) {
            $errors = new WP_Error('adcaptcha_error', __( 'Incomplete captcha, Please try again.', 'adcaptcha' ));
        }

        return $errors;
    }
}
