<?php

namespace AdCaptcha\Widget\Verify;

class Verify {
    public static function verify_token($successToken = null) {
        $apiKey = get_option('adcaptcha_api_key');
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
            update_option('adcaptcha_success_token', '');
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $message = json_decode($body);
        if ($message && $message->message === 'Token verified') {
            update_option('adcaptcha_success_token', '');
            return true;
        }

        return false;
    }

    public function get_success_token() {
        $script = '
        document.addEventListener("DOMContentLoaded", function() {
            document.addEventListener("adcaptcha_onSuccess", function(e) {
                document.getElementById("adcaptcha_successToken").value = e.detail.successToken;
            });
        });';
    
        wp_add_inline_script( 'adcaptcha-script', $script );
    }
}
