<?php

namespace AdCaptcha\Widget;

class Verify {
    public static function verify_token($successToken) {
        $apiKey = get_option('adcaptcha_api_key');

        if (!$successToken || !$apiKey) {
            return false;
        }

        $url = 'https://api.adcaptcha.com/v1/verify';
        $body = wp_json_encode([
            'token' => $successToken
        ]);
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $apiKey,
        ];

        $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body' => $body,
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $message = json_decode($body);
        if ($message && $message->message === 'Token verified') {
            return true;
        }

        return false;
    }

    public static function get_success_token() {
        $script = '
        document.addEventListener("DOMContentLoaded", function() {
            document.addEventListener("adcaptcha_onSuccess", function(e) {
                var elements = document.querySelectorAll(".adcaptcha_successToken");
                elements.forEach(function(element) {
                    element.value = e.detail.successToken;
                });
            });
        });';
    
        wp_add_inline_script( 'adcaptcha-script', $script );
    }
}
