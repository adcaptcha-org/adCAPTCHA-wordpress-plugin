<?php

namespace AdCaptcha\Plugin\Woocommerce\Registration;

use AdCaptcha\Widget\AdCaptcha\AdCaptcha;
use AdCaptcha\Widget\Verify\Verify;
use WP_Error;

class Registration {

    public function setup() {
        add_action( 'woocommerce_register_form', [ AdCaptcha::class, 'enqueue_scripts' ] );
        add_action( 'woocommerce_register_form', [ AdCaptcha::class, 'captcha_trigger' ] );
        add_filter( 'woocommerce_registration_errors', [ $this, 'verify' ], 10, 3 );
    }

    public function verify( $validation_errors, $username, $email ) {
        global $adCAPTCHAWordpressRegistration;
        remove_action( 'registration_errors', [ $adCAPTCHAWordpressRegistration, 'verify' ], 10 );
        $response = Verify::verify_token();

        if ( !$response ) {
            $validation_errors = new WP_Error('adcaptcha_error', __( 'Incomplete captcha, Please try again.', 'adcaptcha' ) );
        }

        return $validation_errors;
    }
}
