<?php

namespace AdCaptcha;

use AdCaptcha\Settings\Settings;
use AdCaptcha\Plugin\Login\Login;
use AdCaptcha\Plugin\Registration\Registration;
use AdCaptcha\Plugin\PasswordReset\PasswordReset;
use AdCaptcha\Plugin\Comments\Comments;
use AdCaptcha\Plugin\Woocommerce\Login\Login as WoocommerceLogin;
use AdCaptcha\Plugin\Woocommerce\PasswordReset\PasswordReset as WoocommercePasswordReset;
use AdCaptcha\Plugin\Woocommerce\Registration\Registration as WoocommerceRegistration;
use AdCaptcha\Plugin\ContactFrom7\Froms\Forms as ContactForm7;
use AdCaptcha\Plugin\Mailchimp\Froms\Forms as MailchimpForms;
use AdCaptcha\Plugin\NinjaForms\Froms\Forms as NinjaForms;
use AdCaptcha\Plugin\WPForms\Froms\Forms as WPForms;
use AdCaptcha\Plugin\Elementor\Forms\Forms as Elementor;
use AdCaptcha\Plugin\FluentForms\Forms\Forms as FluentForms;

class Instantiate {

    public function setup() {

        if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
        
        $classes = [
            'Wordpress_Login' => [
                'instance' => Login::class,
                'plugin' => [],
            ],
            'Wordpress_Register' => [
                'instance' => Registration::class,
                'plugin' => [],
            ],
            'Wordpress_ForgotPassword' => [
                'instance' => PasswordReset::class,
                'plugin' => [],
            ],
            'Wordpress_Comments' => [
                'instance' => Comments::class,
                'plugin' => [],
            ],
            'Woocommerce_Login' => [
                'instance' => WoocommerceLogin::class,
                'plugin' => [ 'woocommerce/woocommerce.php' ],
            ],
            'Woocommerce_ForgotPassword' => [
                'instance' => WoocommercePasswordReset::class,
                'plugin' => [ 'woocommerce/woocommerce.php' ],
            ],
            'Woocommerce_Register' => [
                'instance' => WoocommerceRegistration::class,
                'plugin' => [ 'woocommerce/woocommerce.php' ],
            ],
            'ContactForm7_Forms' => [
                'instance' => ContactForm7::class,
                'plugin' => [ 'contact-form-7/wp-contact-form-7.php' ],
            ],
            'Mailchimp_Forms' => [
                'instance' => MailchimpForms::class,
                'plugin' => [ 'mailchimp-for-wp/mailchimp-for-wp.php' ],
            ],
            'NinjaForms_Forms' => [
                'instance' => NinjaForms::class,
                'plugin' => [ 'ninja-forms/ninja-forms.php' ],
            ],
            'WPForms_Forms' => [
                'instance' => WPForms::class,
                'plugin' => [ 'wpforms-lite/wpforms.php', 'wpforms/wpforms.php' ],
            ],
            'Elementor_Forms' => [
				'instance' => Elementor::class,
				'plugin' => [ 'elementor-pro/elementor-pro.php' ],
			],
            'FluentForms_Forms' => [
                'instance' => FluentForms::class,
                'plugin' => [ 'fluentform/fluentform.php' ],
            ],
        ];

        $selected_plugins = get_option('adcaptcha_selected_plugins') ? get_option('adcaptcha_selected_plugins') : array();

        $settings = new Settings();
        $settings->setup();

        if (get_option('adcaptcha_render_captcha') === '1') {
            foreach ($selected_plugins as $selected_plugin) {
                if (isset($classes[$selected_plugin])) {
                    $className = $classes[$selected_plugin]['instance'];
                    if (empty($classes[$selected_plugin]['plugin'])) {
                        new $className();
                    } else {
                        foreach ($classes[$selected_plugin]['plugin'] as $plugin) {
                            if (is_plugin_active($plugin)) {
                                $className = $classes[$selected_plugin]['instance'];
                                new $className();
                            }
                        }
                    }
                }
            }
        }
    }
}
