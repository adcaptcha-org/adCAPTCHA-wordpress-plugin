<?php
/**
 * Plugin Name: adCAPTCHA for WordPress
 * Description: Secure your site. Elevate your brand. Boost Ad Revenue.
 * Version: 1.5.5
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

if ( ! defined( 'ABSPATH' ) ) exit; 
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

use AdCaptcha\Instantiate;

const PLUGIN_VERSION_ADCAPTCHA = '1.5.5';
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
