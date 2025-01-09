<?php

namespace AdCaptcha\Plugin\Woocommerce;

use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use AdCaptcha\Plugin\AdCaptchaPlugin;

use DateTime;

class Checkout extends AdCaptchaPlugin {
    private $verify;

    public function __construct() {
        parent::__construct();
        $this->verify = new Verify();
    }

    public function setup() { 
        add_action( 'wp_enqueue_scripts', [ AdCaptcha::class, 'enqueue_scripts' ] );
        add_action( 'wp_enqueue_scripts', [ Verify::class, 'get_success_token' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'init_trigger' ] );
        if (get_option('adcaptcha_wc_checkout_optional_trigger')) {
            add_action( 'wp_enqueue_scripts', [ $this, 'block_submission' ] );
        }
        add_action( 'woocommerce_review_order_before_submit', [ AdCaptcha::class, 'captcha_trigger' ] );
        add_action('woocommerce_payment_complete', [ $this, 'reset_hasVerified' ]);
        add_action( 'woocommerce_checkout_process', [ $this, 'verify' ] );
    }

    public function verify() {
        $session = WC()->session;
        $hasVerified = $session->get('hasVerified');

        if ( $hasVerified && strtotime($hasVerified) < time() ) {
            $this->reset_hasVerified();
        }

        if ( $hasVerified && strtotime($hasVerified) > time() ) {
            return;
        }

        $successToken = sanitize_text_field(wp_unslash($_POST['adcaptcha_successToken']));

        $response = $this->verify->verify_token($successToken);
  
        if ( !$response ) {
            wc_add_notice( __( 'Incomplete captcha, Please try again.', 'adcaptcha' ), 'error' );
            return;
        }
        // Add 10 minutes to the current date and time
        $date = new DateTime();
        $date->modify('+10 minutes');
        $formatted_date = $date->format('Y-m-d H:i:s');
        $session->set('hasVerified', $formatted_date);
    }

    public function reset_hasVerified() {
        WC()->session->set('hasVerified', null);
    }

    public function init_trigger() {
        wp_register_script('adcaptcha-wc-init-trigger', null);
        wp_add_inline_script('adcaptcha-wc-init-trigger', '
            const initTrigger = ($) => {
                $(document.body).on("updated_checkout", function () {
                    if (window.adcap) {
                        window.adcap.init({onComplete: () => {
                            const event = new CustomEvent("adcaptcha_onSuccess", {
                                detail: { successToken: window.adcap.successToken },
                            });
                            document.dispatchEvent(event);  

                            if (window.adcap.tmp && window.adcap.tmp.didSubmitTrigger) {
                                const checkoutForm = $("form.checkout");
                                if (checkoutForm.length) {
                                    checkoutForm.submit();
                                }
                                window.adcap.tmp = { didSubmitTrigger: false };
                            }
                        }});

                        ' . (WC()->session->get('hasVerified') ? ' window.adcap.setVerificationState(true);' : '' ) . '
                    }
                });
            };

            jQuery(document).ready(initTrigger);
        ');
        wp_enqueue_script('adcaptcha-wc-init-trigger');
    }

    public function block_submission() {
        $script = '
        jQuery(document).ready(function($) {
            var checkoutForm = $("form.checkout");
            if (checkoutForm.length) {
                checkoutForm.on("submit", function(event) {
                    if (!window.adcap.successToken) {
                        event.preventDefault();
    
                        if (window.adcap) {
                            window.adcap.tmp = { didSubmitTrigger: true };
                            window.adcap.handleTriggerClick("' . esc_js(get_option('adcaptcha_placement_id')) . '");
                        }
                    }
                });
            }
        });
    ';
    
        wp_add_inline_script('adcaptcha-script', $script);
    }
}
