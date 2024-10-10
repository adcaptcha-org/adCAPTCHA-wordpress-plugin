<?php
/**
 * Plugin Name: adCAPTCHA for WordPress
 * Description: Secure your site. Elevate your brand. Boost Ad Revenue.
 * Version: 1.4.0
 * Requires at least: 6.4.2
 * Requires PHP: 7.4
 * Author: adCAPTCHA
 * Author URI: https://adcaptcha.com
 * Text Domain: adcaptcha
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * 
 * @package adCAPTCHA
 * @copyright 2024 adCAPTCHA. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once plugin_dir_path(__FILE__) . 'src/Instantiate.php';
require_once plugin_dir_path(__FILE__) . 'src/Settings/Settings.php';
require_once plugin_dir_path(__FILE__) . 'src/Settings/General.php';
require_once plugin_dir_path(__FILE__) . 'src/Settings/Plugins.php';
require_once plugin_dir_path(__FILE__) . 'src/Plugin/AdCaptchaPlugin.php';
require_once plugin_dir_path(__FILE__) . 'src/Plugin/Login.php';
require_once plugin_dir_path(__FILE__) . 'src/Plugin/Registration.php';
require_once plugin_dir_path(__FILE__) . 'src/Plugin/PasswordReset.php';
require_once plugin_dir_path(__FILE__) . 'src/Plugin/Comments.php';
require_once plugin_dir_path(__FILE__) . 'src/Widget/AdCaptcha.php';
require_once plugin_dir_path(__FILE__) . 'src/Widget/Verify.php';
require_once plugin_dir_path(__FILE__) . 'src/Plugin/Woocommerce/Login.php';
require_once plugin_dir_path(__FILE__) . 'src/Plugin/Woocommerce/PasswordReset.php';
require_once plugin_dir_path(__FILE__) . 'src/Plugin/Woocommerce/Registration.php';
require_once plugin_dir_path(__FILE__) . 'src/Plugin/ContactForm7/Forms.php';
require_once plugin_dir_path(__FILE__) . 'src/Plugin/Mailchimp/Forms.php';
require_once plugin_dir_path(__FILE__) . 'src/Plugin/NinjaForms/Forms.php';
require_once plugin_dir_path(__FILE__) . 'src/Plugin/WPForms/Forms.php';
require_once plugin_dir_path(__FILE__) . 'src/Plugin/Elementor/Forms.php';
require_once plugin_dir_path(__FILE__) . 'src/Plugin/FluentForms/Forms.php';

use AdCaptcha\Instantiate;

const PLUGIN_VERSION_ADCAPTCHA = '1.4.0';
define('ADCAPTCHA_ERROR_MESSAGE', __( 'Please complete the I am human box.', 'adcaptcha' ));

// Deletes data saved in the wp db on plugin uninstall
register_uninstall_hook( __FILE__, 'adcaptcha_uninstall' );

function adcaptcha_uninstall() {
    delete_option( 'adcaptcha_api_key' );
    delete_option( 'adcaptcha_placement_id' );
    delete_option( 'adcaptcha_render_captcha' );
    delete_option( 'adcaptcha_selected_plugins' );
}

$instantiate = new Instantiate();
$instantiate->setup();
