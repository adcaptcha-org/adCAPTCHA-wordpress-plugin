<?php

namespace AdCaptcha;

use AdCaptcha\Settings\Settings;
use AdCaptcha\Plugin\Login;
use AdCaptcha\Plugin\Registration;
use AdCaptcha\Plugin\PasswordReset;
use AdCaptcha\Plugin\Comments;
use AdCaptcha\Plugin\Woocommerce\Login as WoocommerceLogin;
use AdCaptcha\Plugin\Woocommerce\PasswordReset as WoocommercePasswordReset;
use AdCaptcha\Plugin\Woocommerce\Registration as WoocommerceRegistration;
use AdCaptcha\Plugin\Woocommerce\Checkout as WoocommerceCheckout;
use AdCaptcha\Plugin\ContactForm7\Forms as ContactForm7;
use AdCaptcha\Plugin\Mailchimp\Forms as MailchimpForms;
use AdCaptcha\Plugin\NinjaForms\Forms as NinjaForms;
use AdCaptcha\Plugin\WPForms\Forms as WPForms;
use AdCaptcha\Plugin\Elementor\Forms as Elementor;
use AdCaptcha\Plugin\FluentForms\Forms as FluentForms;

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
            'Woocommerce_Checkout' => [
                'instance' => WoocommerceCheckout::class,
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
