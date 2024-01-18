<?php

namespace AdCaptcha\Settings;

class Settings {

    public function setup() {
        add_action('admin_menu', array($this, 'add_adcaptcha_options_page'));
        add_action('admin_enqueue_scripts', [ $this, 'add_styles_to_settings' ]);
        add_filter('admin_footer_text', array($this, 'change_admin_footer_text'), 11);
        add_filter('update_footer', array($this, 'change_admin_footer_version'), 11);
    }
    
    public function add_adcaptcha_options_page() {
        add_options_page(
            'adCaptcha',
            'adCaptcha',
            'manage_options',
            'adcaptcha',
            array($this, 'render_adcaptcha_options_page')
        );
    }

    public function add_styles_to_settings() {
        wp_enqueue_style('adcaptcha-admin-styles', plugins_url('../styles/settings.css', __FILE__));
    }
     
    public function render_adcaptcha_options_page() {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
                // Verify the nonce
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'adcaptcha_form_action')) {
                die('Invalid nonce');
            }

            update_option('adcaptcha_api_key', sanitize_text_field($_POST['adcaptcha_option_name']['api_key']));
            update_option('adcaptcha_placement_id', sanitize_text_field($_POST['adcaptcha_option_name']['placement_id']));
        }

        ?>
        <div>
            <div class="header container">
                <?php printf('<img src="%s" class="logo"/>', esc_url('https://assets.adcaptcha.com/mail/logo_gradient.png')); ?>
                <hr>
            </div>
            <div>
            <div class="integrating-description">
                <p>Before integrating, you must have an adCAPTCHA account and gone through the setup process. <a class="dashboard-link" href="https://app.adcaptcha.com/login" target="_blank">Dashboard &rarr;</a></p>
            </div>
            </div>
            <form method="post" class="form">
                <?php
                    echo '<input type="text" id="api_key" class="api-key-input" name="adcaptcha_option_name[api_key]" value="' . esc_attr(get_option('adcaptcha_api_key')) . '" placeholder="API key">';
                ?>
                <?php
                    echo '<input type="text" id="placement_id" class="placement-id" name="adcaptcha_option_name[placement_id]" value="' . esc_attr(get_option('adcaptcha_placement_id')) . '" placeholder="Placement ID">';
                ?>
                <?php wp_nonce_field('adcaptcha_form_action'); ?>
                <button type="submit" class="save-button">Save</button>
            </form>
        </div>
		<?php
    }

    public function change_admin_footer_text() {
        return '© 2024 adCaptcha. All rights reserved.';
    }

    public function change_admin_footer_version() {
        return 'Version 1.0';
    }
}