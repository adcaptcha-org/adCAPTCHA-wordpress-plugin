<?php

namespace AdCaptcha\Settings\General;

class General {

    public function verify_input_data($api_key, $placement_id) {
        $url = 'https://api.adcaptcha.com/v1/placements/' . $placement_id;
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ];

        $response = wp_remote_get($url, array(
            'headers' => $headers,
        ));
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            echo esc_html("Something went wrong: $error_message");
        }

        return $response;
    }
     
    public function render_general_settings() {
        $save_error = false;
        $saved_successfully = false;
        // Saves the Api Key and Placements ID in the wp db
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce']));
            // Verify the nonce
            if (!isset($nonce) || !wp_verify_nonce($nonce, 'adcaptcha_form_action')) {
                die('Invalid nonce');
            }

            $api_key = sanitize_text_field(wp_unslash($_POST['adcaptcha_option_name']['api_key']));
            $placement_id = sanitize_text_field(wp_unslash($_POST['adcaptcha_option_name']['placement_id']));
            $response = $this->verify_input_data($api_key, $placement_id);
            if ($response['response']['code'] === 200) {
                update_option('adcaptcha_api_key', $api_key);
                update_option('adcaptcha_placement_id', $placement_id);
                update_option('adcaptcha_render_captcha', true);
                $saved_successfully = true;
            } else {
                $save_error = true;
                update_option('adcaptcha_render_captcha', false);
            }
        }

        ?>
            <div class="integrating-description">
                <div>
                    <p>Before integrating, you must have an adCAPTCHA account and gone through the setup process. <a class="dashboard-link link" href="https://app.adcaptcha.com" target="_blank">Dashboard &rarr;</a><a class="documentation-link link" href="https://docs.adcaptcha.com/" target="_blank">Documentation &rarr;</a></p>
                </div>
                <?php if ($saved_successfully === true) : ?>
                    <div style="background-color: #22C55E; color: #ffffff; padding: 10px; border-radius: 5px; margin: 10px 0; max-width: 450px; font-size: 14px;">
                        Settings saved successfully. The captcha will now be displayed.
                    </div>
                <?php endif; ?>
                <?php if (get_option('adcaptcha_render_captcha') !== '1' && $saved_successfully !== true || $save_error === true) : ?>
                    <div style="background-color: #DC2626; color: #ffffff; padding: 10px; border-radius: 5px; margin: 10px 0; max-width: 800px; font-size: 14px;">
                        Placement ID or API Key is Invalid. Please try again. Captcha will not be displayed until the settings are saved successfully.
                    </div>
                <?php endif; ?>
                <form method="post" class="form">
                    <?php
                        echo '<label for="api_key" class="input-label">API Key</label>';
                        echo '<input type="password" id="api_key" class="input-field" name="adcaptcha_option_name[api_key]" value="' . esc_attr(get_option('adcaptcha_api_key')) . '" placeholder="API key">';
                    ?>
                    <?php
                        echo '<label for="placement_id" class="input-label">Placement ID</label>';
                        echo '<input type="text" id="placement_id" class="input-field" name="adcaptcha_option_name[placement_id]" value="' . esc_attr(get_option('adcaptcha_placement_id')) . '" placeholder="Placement ID">';
                    ?>
                    <?php wp_nonce_field('adcaptcha_form_action'); ?>
                    <button type="submit" class="save-button">Save</button>
                </form>
            </div>
		<?php
    }
}
