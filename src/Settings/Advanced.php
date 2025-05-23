<?php

namespace AdCaptcha\Settings;

class Advanced {
     
    public function render_advance_settings() {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce']));
            // Verify the nonce
            if (!isset($nonce) || !wp_verify_nonce($nonce, 'adcaptcha_form_action')) {
                die('Invalid nonce');
            }

            $wc_checkout = isset($_POST['adcaptcha_advance']['wc-checkout']) ? sanitize_text_field(wp_unslash($_POST['adcaptcha_advance']['wc-checkout'])) : '';
            update_option('adcaptcha_wc_checkout_optional_trigger', $wc_checkout);

            $experimental_disable_wc_checkout_endpoint = isset($_POST['adcaptcha_advance']['experimental_disable_wc_checkout_endpoint']) ? sanitize_text_field(wp_unslash($_POST['adcaptcha_advance']['experimental_disable_wc_checkout_endpoint'])) : '';
            update_option('experimental_disable_wc_checkout_endpoint', $experimental_disable_wc_checkout_endpoint);
        }

        ?>
            <div class="advance-container">
                <h1 style="margin-bottom: 30px;">Advance Settings</h1>

                <form method="post" class="plugin-form">
                        <div class="plugins-layout">
                            <div class="advance-item-container">
                                    <?php
                                        $checked = get_option('adcaptcha_wc_checkout_optional_trigger') ? 'checked' : '';
                                        $checked_experimental = get_option('experimental_disable_wc_checkout_endpoint') ? 'checked' : '';
                                    ?>
                                    <h2 style="font-size:x-large;">Woocommerce</h2>
                                    <div class="checkbox-container">
                                        <h4 style="padding-right: 20px; font-size:medium;">Checkout:</h4>
                                        <input type="checkbox" id="wc-checkout" name="adcaptcha_advance[wc-checkout]" value="wc-checkout" <?php echo $checked; ?>>
                                        <label class="checkbox-label" for="wc-checkout">Enable to trigger adCAPTCHA on the "Place order" button.</label><br>
                                    </div>
                                    <div class="checkbox-container">
                                        <h4 style="padding-right: 20px; font-size:medium;">Disable Checkout Endpoint:</h4>
                                        <input type="checkbox" id="wc-checkout" name="adcaptcha_advance[experimental_disable_wc_checkout_endpoint]" value="experimental_disable_wc_checkout_endpoint" <?php echo $checked_experimental; ?>>
                                        <label class="checkbox-label" for="wc-checkout">Enable to disable the WooCommerce checkout endpoint. This will help prevent unauthorised request, for example stopping credit card fraud.</label><br>
                                    </div>
                            </div>
                        </div>
                        <?php wp_nonce_field('adcaptcha_form_action'); ?>
                        <button type="submit" class="save-button">Save</button>
                    </form>
            </div>
        <?php
    }
}