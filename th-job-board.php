<?php
/**
 * Plugin Name: Talent Hero Job Board
 * Version:     2.1
 * Description: Custom plugin for adding Job Board feature
 * Author:      Yelk
 * Author URI:  https://yelk.com.ua
 * Text Domain: th_jb
 * Domain Path: /languages/
 * Requires at least: 5.8
 * Requires PHP: 5.6.20
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

define('THJB_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );
define('THJB_PLUGIN_DIR_URL', plugin_dir_url( __FILE__ ) );
define('THJB_PLUGIN_INC_PATH', plugin_dir_path( __FILE__ ) . 'includes/' );

define('THJB_PLUGIN_NAME', plugin_basename(dirname(__FILE__)));

if ( ! defined('THJB_JWT_AUTH_SECRET_KEY') && defined('NONCE_SALT') ) {
    define('THJB_JWT_AUTH_SECRET_KEY', NONCE_SALT);
}

// Require composer.
require __DIR__ . '/vendor/autoload.php';

// load dependencies
require_once THJB_PLUGIN_INC_PATH . 'loader.php';

new THJB\Plugin();

register_activation_hook( __FILE__, 'activate_th_plugin_function' );
register_deactivation_hook( __FILE__, 'deactivate_th_plugin_function' );

function activate_th_plugin_function()
{

}

function deactivate_th_plugin_function()
{

}

