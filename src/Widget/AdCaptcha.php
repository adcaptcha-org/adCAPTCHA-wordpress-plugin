<?php

namespace AdCaptcha\Widget;

class AdCaptcha {

    public static function enqueue_scripts($enableSubmitButton = false) {
        wp_enqueue_script('adcaptcha-script', 'https://widget.adcaptcha.com/index.js', array('jquery'), PLUGIN_VERSION_ADCAPTCHA, true);
    
        $ajax_nonce = wp_create_nonce("adcaptcha_nonce");
        wp_localize_script('adcaptcha-script', 'adcaptcha_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => $ajax_nonce,
        ));

        wp_add_inline_script( 'adcaptcha-script', 'window.onload = function() {
            if (window.adcap) {
                ' . self::setupScript($enableSubmitButton) . '
            }
        }');
    }

    public static function setupScript($enableSubmitButton = false) {
        return 'window.adcap.init();
        window.adcap.setupTriggers({
            onComplete: () => {
                ' . ($enableSubmitButton ? self::enable_submit_button() : '') . '
                const event = new CustomEvent("adcaptcha_onSuccess", {
                    detail: { successToken: window.adcap.successToken },
                });
                document.dispatchEvent(event);
            }
        });';
    }

    public static function enable_submit_button() {
        return 'var submitButton = document.querySelector("#wp-submit");
        if (submitButton) {
            submitButton.disabled = false;
        }';
    }

    public static function ob_captcha_trigger() {
		ob_start();
        self::captcha_trigger();

        return ob_get_clean();
    }

    public static function captcha_trigger() {
        printf(
            '<div data-adcaptcha="%s" style="margin-bottom: 20px; max-width: 400px; width: 100%%; outline: none !important;"></div><input type="hidden" class="adcaptcha_successToken" name="adcaptcha_successToken">',
            esc_attr(get_option('adcaptcha_placement_id'))
        );
    }
}
