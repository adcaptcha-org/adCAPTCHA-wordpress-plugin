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

class Instantiate {

    public function setup() {
        $classes = array(
            'Wordpress_Login' => new Login(),
            'Wordpress_Register' => new Registration(),
            'Wordpress_ForgotPassword' => new PasswordReset(),
            'Wordpress_Comments' => new Comments(),
            'Woocommerce_Login' => new WoocommerceLogin(),
            'Woocommerce_ForgotPassword' => new WoocommercePasswordReset(),
            'Woocommerce_Register' => new WoocommerceRegistration(),
            'ContactForm7_Forms' => new ContactForm7(),
            'Mailchimp_Forms' => new MailchimpForms(),
        );

        $selected_plugins = get_option('adcaptcha_selected_plugins') ? get_option('adcaptcha_selected_plugins') : array();

        $settings = new Settings();
        $settings->setup();

        if (get_option('adcaptcha_render_captcha') === '1') {
            foreach ($selected_plugins as $selected_plugin) {
                if (isset($classes[$selected_plugin])) {
                    $classes[$selected_plugin]->setup();
                }
            }
        }
    }
}
