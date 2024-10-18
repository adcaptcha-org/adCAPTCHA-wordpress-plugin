<?php

namespace AdCaptcha\Plugin\WPForms;

use AdCaptcha\Plugin\WPForms\AdCAPTCHA_WPForms_Field;
use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use AdCaptcha\Plugin\AdCaptchaPlugin;

class Forms extends AdCaptchaPlugin {

        public function setup() {
            add_action('plugins_loaded', function() {
                require_once plugin_dir_path(__FILE__) . '/AdCAPTCHA_WPForms_Field.php';
                new AdCAPTCHA_WPForms_Field();
                add_action( 'wp_enqueue_scripts', [ AdCaptcha::class, 'enqueue_scripts' ]);
                add_action( 'wp_enqueue_scripts', [ Verify::class, 'get_success_token' ] );
                add_action( 'wp_enqueue_scripts', [ $this, 'block_submission' ] );

                add_action('admin_enqueue_scripts', function() {
                    $screen = get_current_screen();
                    if ($screen->id !== 'wpforms_page_wpforms-builder') {
                        return;
                    }

                    AdCaptcha::enqueue_scripts();
                });

                add_filter('wpforms_load_fields', function($fields) {
                    $fields[] = 'adcaptcha';
                    return $fields;
                });

                add_filter('wpforms_fields', function($fields) {
                    $fields['adcaptcha'] = 'AdCAPTCHA_WPForms_Field';
                    return $fields;
                });
            });

            add_action( 'wpforms_process', [ $this, 'verify' ], 10, 3 );
        }

        public function block_submission() {
            $script = '
                document.addEventListener("DOMContentLoaded", function() {
                    var form = document.querySelector(".wpforms-form");
                    if (form) {
                        var submitButton =[... document.querySelectorAll("[type=\'submit\']")];
                        if (submitButton) {
                            submitButton.forEach(function(submitButton) {
                                submitButton.addEventListener("click", function(event) {
                                    if (!window.adcap || !window.adcap.successToken) {
                                        event.preventDefault();
                                        var errorMessage = document.createElement("div");
                                        errorMessage.id = "adcaptcha-error-message";
                                        errorMessage.className = "wpforms-error-container";
                                        errorMessage.role = "alert";
                                        errorMessage.innerHTML = \'<span class="wpforms-hidden" aria-hidden="false">Form error message</span><p>Please complete the I am human box.</p>\';
                                        var parent = submitButton.parentNode;
                                        parent.parentNode.insertBefore(errorMessage, parent);
                                        return false;
                                    }
                                });
                            });
                        }
                    }
                });';
    
            wp_add_inline_script('adcaptcha-script', $script);
        }

        public function verify( array $fields, array $entry, array $form_data ) {
            $successToken = sanitize_text_field(wp_unslash($_POST['adcaptcha_successToken']));
            $verify = new Verify();
            $response = $verify->verify_token($successToken);
    
            if ( $response === false ) {
                wpforms()->get( 'process' )->errors[ $form_data['id'] ]['footer'] = __( ADCAPTCHA_ERROR_MESSAGE );
            }
        }
}
