<?php

namespace AdCaptcha\Plugin\Woocommerce;

use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use AdCaptcha\Plugin\AdCaptchaPlugin;
use WP_Error;

class PasswordReset extends AdCaptchaPlugin {
    private $verify;

    public function __construct() {
        parent::__construct();
        $this->verify = new Verify();
    }

    public function setup() {
        add_action( 'woocommerce_lostpassword_form', [ AdCaptcha::class, 'enqueue_scripts' ] );
        add_action( 'woocommerce_lostpassword_form', [ Verify::class, 'get_success_token' ] );
        add_action( 'woocommerce_lostpassword_form', [ AdCaptcha::class, 'captcha_trigger' ] );
        add_action( 'wp_loaded', [ $this, 'remove_wp_action' ], 10 );
        add_filter( 'allow_password_reset', [ $this, 'verify' ], 10, 1 );
    }

    public function remove_wp_action() {
        global $adCAPTCHAWordpressPasswordReset;
        remove_action( 'lostpassword_post', [ $adCAPTCHAWordpressPasswordReset, 'verify' ], 10 );
    }

    public function verify( $error ) {
        $successToken = sanitize_text_field(wp_unslash($_POST['adcaptcha_successToken']));
        $response = $this->verify->verify_token($successToken);

        if ( !$response ) {
            $error = new WP_Error('adcaptcha_error', __( 'Incomplete captcha, Please try again.', 'adcaptcha' ) );
        }

        return $error;
    }
}
