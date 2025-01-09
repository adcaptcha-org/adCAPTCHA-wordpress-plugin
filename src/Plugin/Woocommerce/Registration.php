<?php

namespace AdCaptcha\Plugin\Woocommerce;

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
        add_action( 'woocommerce_register_form', [ AdCaptcha::class, 'enqueue_scripts' ] );
        add_action( 'woocommerce_register_form', [ Verify::class, 'get_success_token' ] );
        add_action( 'woocommerce_register_form', [ AdCaptcha::class, 'captcha_trigger' ] );
        add_filter( 'woocommerce_registration_errors', [ $this, 'verify' ], 10, 3 );
    }

    public function verify( $validation_errors, $username, $email ) {
        global $adCAPTCHAWordpressRegistration;
        remove_action( 'registration_errors', [ $adCAPTCHAWordpressRegistration, 'verify' ], 10 );
        $successToken = sanitize_text_field(wp_unslash($_POST['adcaptcha_successToken']));
        $response = $this->verify->verify_token($successToken);

        if ( !$response && !is_checkout() ) {
            $validation_errors = new WP_Error('adcaptcha_error', __( 'Incomplete captcha, Please try again.', 'adcaptcha' ) );
        }

        return $validation_errors;
    }
}
