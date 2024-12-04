<?php

namespace AdCaptcha\Plugin\ContactForm7;

use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use AdCaptcha\Plugin\AdCaptchaPlugin;

class Forms extends AdCaptchaPlugin {
    private $verify;

    public function __construct() {
        parent::__construct();
        $this->verify = new Verify();
    }
    private ?AdCaptcha $adCaptcha = null; 

    public function setup() {
        add_action( 'wp_enqueue_scripts', [ AdCaptcha::class, 'enqueue_scripts' ], 9 );
        add_action( 'wp_enqueue_scripts', [ $this, 'block_submission' ], 9 );
        add_action( 'wp_enqueue_scripts', [ $this, 'get_success_token' ], 9 );
        add_action( 'wp_enqueue_scripts', [ $this, 'reset_captcha_script' ], 9 );
        add_filter( 'wpcf7_form_elements', [ $this, 'captcha_trigger_filter' ], 20, 1 );
        add_filter('wpcf7_form_hidden_fields', [$this, 'add_adcaptcha_response_field']);
        add_filter( 'wpcf7_spam', [ $this, 'verify' ], 9, 1 );
    }

    public function verify( $spam ) {
      
        $token = trim( $_POST['_wpcf7_adcaptcha_response']);
    
        $response = $this->verify->verify_token($token);
    
        if ( $response === false ) {
            $spam = true;
           
            add_filter('wpcf7_display_message', function($message, $status) {
                if ($status == 'spam') {
                    $message = __( 'Please complete the I am human box', 'adcaptcha' );
                }
                return $message;
            }, 10, 2);
        }
      
        return $spam;
    }

    public function captcha_trigger_filter(string $elements) {
        if (strpos($elements, 'data-adcaptcha') !== false) {
            return preg_replace(
                '/(<(input|button).*?type=(["\']?)submit(["\']?))/',
                '<input type="hidden" class="adcaptcha_successToken" name="adcaptcha_successToken">' . '$1',
                $elements
            );
        }

    return preg_replace(
        '/(<(input|button).*?type=(["\']?)submit(["\']?))/',
        AdCaptcha::ob_captcha_trigger() . '$1',
        $elements
    );
    }
         
    public function add_adcaptcha_response_field($fields) {
        return array_merge( $fields, array(
            '_wpcf7_adcaptcha_response' => '',
        ) );
    }

    public function reset_captcha_script() {
        wp_add_inline_script( 'adcaptcha-script', 'document.addEventListener("wpcf7mailsent", function(event) { ' . AdCaptcha::setupScript() . ' window.adcap.successToken = ""; }, false);' );
    }

    public function block_submission() {
        // Log to see if this method is called
    error_log("block_submission method called"); 
        $script = '
            document.addEventListener("DOMContentLoaded", function() {
                var form = document.querySelector(".wpcf7-form");
                if (form) {
                var submitButton =[... document.querySelectorAll(".wpcf7 [type=\'submit\']")];
                    if (submitButton) {
                        submitButton.forEach(function(submitButton) {
                            submitButton.addEventListener("click", function(event) {
                                if (!window.adcap || !window.adcap.successToken) {
                                    event.preventDefault();
                                    var errorMessage = form.querySelector(".wpcf7-response-output");
                                    errorMessage.className += " wpcf7-validation-errors";
                                    errorMessage.style.display = "block";
                                    errorMessage.textContent = "Please complete the I am human box";
                                    errorMessage.setAttribute("aria-hidden", "false");
                                    return false;
                                }
                                var removeMessage = form.querySelector(".wpcf7-response-output");
                                removeMessage.classList.remove("wpcf7-validation-errors");
                                removeMessage.style = "";
                                removeMessage.textContent = "";
                            });
                        });
                    }
                }
            });';
    
        wp_add_inline_script( 'adcaptcha-script', $script );
    }

    public function get_success_token() {
        $script = '
        document.addEventListener("DOMContentLoaded", function() {
            document.addEventListener("adcaptcha_onSuccess", (e) => {
                const t = document.querySelectorAll(
                "form.wpcf7-form input[name=\'_wpcf7_adcaptcha_response\']"
                );
                for (let c = 0; c < t.length; c++)
                t[c].setAttribute("value", e.detail.successToken);
            });
        });';
    
        wp_add_inline_script( 'adcaptcha-script', $script );
    }
    public function setAdCaptcha(AdCaptcha $adCaptcha) {
        $this->adCaptcha = $adCaptcha;
    }
}
