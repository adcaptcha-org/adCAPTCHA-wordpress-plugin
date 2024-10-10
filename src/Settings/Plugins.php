<?php

namespace AdCaptcha\Settings\Plugins;

class Plugins {
     
    public function render_Plugins_settings() {
        $plugins = array(
            array(
                'label' => 'Wordpress',
                'logo' => 'wordpress_logo.png',
                'options' => array('Login', 'Register', 'Comments', 'Forgot Password')
            ),
            array(
                'label' => 'Woocommerce',
                'logo' => 'woocommerce_logo.png',
                'options' => array('Login', 'Register', 'Forgot Password')
            ),
            array(
                'label' => 'ContactForm7',
                'logo' => 'contactForm7_logo.png',
                'options' => array('Forms')
            ),
            array(
                'label' => 'Mailchimp',
                'logo' => 'mailchimp_logo.png',
                'options' => array('Forms')
            ),
            array(
                'label' => 'NinjaForms',
                'logo' => 'ninjaForms_logo.png',
                'options' => array('Forms')
            ),
            array(
                'label' => 'WPForms',
                'logo' => 'wpforms_logo.png',
                'options' => array('Forms')
            ),
            array(
                'label' => 'Elementor',
                'logo' => 'elementor_logo.png',
                'options' => array('Forms')
            ),
            array(
                'label' => 'FluentForms',
                'logo' => 'fluent_forms_logo.png',
                'options' => array('Forms')
            ),
        );

        $saved_setting = false;

        $selected_plugins = get_option('adcaptcha_selected_plugins') ? get_option('adcaptcha_selected_plugins') : array();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce']));
            if (!isset($nonce) || !wp_verify_nonce($nonce, 'adcaptcha_form_action')) {
                die('Invalid nonce');
            }
            $checked_ids = isset($_POST['selected_plugins']) && is_array($_POST['selected_plugins']) 
            ? array_map('sanitize_text_field', wp_unslash($_POST['selected_plugins'])) 
            : array();
            $selected_plugins = $checked_ids;
            update_option('adcaptcha_selected_plugins', $checked_ids);
            if (!empty($selected_plugins)) {
                $saved_setting = true;
            }
        }

        ?>
                <div class="plugins-container">
                    <h1>Manage Plugins</h1>
                    <p>adCAPTCHA integrates with other Wordpress plugins to provide a CAPTCHA that is both secure and user-friendly.</p>
                    <p>If you are using a plugin that is not listed here, please contact our support team at<a class="link" href="mailto:support@adcaptcha.com"> support@adcaptcha.com.</a></p>
                    <?php if ($saved_setting === true) : ?>
                        <div style="background-color: #22C55E; color: #ffffff; padding: 10px; border-radius: 5px; margin: 10px 0; max-width: 450px; font-size: 14px;">
                            Settings saved. Captcha will be displayed in the selected plugins.
                        </div>
                    <?php endif; ?>
                    <?php if (empty($selected_plugins)) : ?>
                    <div style="background-color: #DC2626; color: #ffffff; padding: 10px; border-radius: 5px; margin: 10px 0; max-width: 800px; font-size: 14px;">
                    Captcha is currently not being displayed anywhere. Please select the plugins where you want the Captcha to be displayed.
                    </div>
                <?php endif; ?>
                    <form method="post" class="plugin-form">
                        <div class="plugins-layout">
                            <?php
                                foreach ($plugins as $plugin) {
                                    ?>
                                        <div class="plugin-container">
                                            <?php
                                            printf('<img class="plugin_logo" src="%s" height="40px" />', esc_url(untrailingslashit(plugin_dir_url(dirname(dirname(__FILE__)))) . '/assets/' . $plugin['logo']));
                                            foreach ($plugin['options'] as $option) {
                                                $optionId = $plugin['label'] . '_' . str_replace(' ', '', $option);
                                                $checked = in_array($optionId, $selected_plugins, true) ? 'checked' : '';
                                                ?>
                                                <div class="checkbox-container">
                                                    <input type="checkbox" id="<?php echo $optionId; ?>" name="selected_plugins[<?php echo $optionId; ?>]" value="<?php echo $optionId; ?>" <?php echo $checked; ?>>
                                                    <label class="checkbox-label" for="<?php echo $option; ?>"><?php echo $option; ?></label><br>
                                                </div>
                                                <?php
                                            }
                                            ?>
                                        </div>
                                    <?php
                                }
                            ?>
                        </div>
                        <?php wp_nonce_field('adcaptcha_form_action'); ?>
                        <button type="submit" class="save-button">Save</button>
                    </form>
                </div>
		<?php
    }
}
