<?php

namespace AdCaptcha\Settings;

class Settings {
    public function setup() {
        add_action('admin_menu', array($this, 'add_adcaptcha_options_page'));
        add_action('admin_enqueue_scripts', [ $this, 'add_styles_to_settings' ]);
        add_filter('admin_footer_text', array($this, 'change_admin_footer_text'));
        add_filter('update_footer', array($this, 'change_admin_footer_version'), PHP_INT_MAX);
    }
    
    public function add_adcaptcha_options_page() {
        add_options_page(
            'adCAPTCHA',
            'adCAPTCHA',
            'manage_options',
            'adcaptcha',
            array($this, 'render_adcaptcha_options_page')
        );
    }

    public function add_styles_to_settings() {
        wp_enqueue_style('adcaptcha-admin-styles', plugins_url('../styles/settings.css', __FILE__), array(), PLUGIN_VERSION_ADCAPTCHA);
    }
     
    public function render_adcaptcha_options_page() {
        $tab = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $tab = isset( $tab ) ? $tab : 'general';
        if (!isset($tab) || ($tab !== 'general' && $tab !== 'plugins')) {
            $tab = 'general';
        }

        ?>
        <div>
            <div class="adcap_header container">
                <?php printf('<img src="%s" class="logo"/>', esc_url(untrailingslashit(plugin_dir_url(dirname(dirname(__FILE__)))) . '/assets/logo.png')); ?>
                <hr>
                <nav class="nav">
                    <a href="?page=adcaptcha" class="nav-tab
                        <?php
                            if ( $tab === 'general' ) :
                        ?>
                    nav-tab-active<?php endif; ?>">General</a>
                    <a href="?page=adcaptcha&tab=plugins" class="nav-tab
                        <?php
                            if ( $tab === 'plugins' ) :
                        ?>
                    nav-tab-active<?php endif; ?>">Plugins</a>
            </div>
            <?php
                switch ($tab) {
                    case 'general':
                        $generalSettings = new \AdCaptcha\Settings\General\General();
                        $generalSettings->render_general_settings();
                        break;
                    case 'plugins':
                        $pluginsSettings = new \AdCaptcha\Settings\Plugins\Plugins();
                        $pluginsSettings->render_Plugins_settings();
                        break;
                }
            ?>
        </div>
		<?php
    }

    public function change_admin_footer_text() {
        $current_year = gmdate('Y');
        return '© ' . $current_year . ' adCAPTCHA. All rights reserved.';
    }

    public function change_admin_footer_version() {
        return 'Version 1.4.0';
    }
}
