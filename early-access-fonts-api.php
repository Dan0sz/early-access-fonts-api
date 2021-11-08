<?php

/**
 * Plugin Name: OMGF - Early Access Fonts API
 * Description: Provides external API for Early Access Fonts compatibility for OMGF Pro.
 * Version: 1.1.0
 * Author: Daan from FFW.Press
 * Author URI: https://ffw.press
 * Text Domain: early-access-fonts-api
 * Github Plugin URI: Dan0sz/early-access-fonts-api
 */

defined('ABSPATH') || exit;

/**
 * Define constants.
 */
define('EAF_API_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EAF_API_PLUGIN_FILE', __FILE__);
define('EAF_API_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Takes care of loading classes on demand.
 *
 * @param $class
 *
 * @return mixed|void
 */
function eaf_api_autoload($class)
{
    $path = explode('_', $class);

    if ($path[0] != 'EAFA') {
        return;
    }

    if (!class_exists('FFWP_Autoloader')) {
        require_once EAF_API_PLUGIN_DIR . 'ffwp-autoload.php';
    }

    $autoload = new FFWP_Autoloader($class);

    return include EAF_API_PLUGIN_DIR . 'includes/' . $autoload->load();
}

spl_autoload_register('eaf_api_autoload');

/**
 * @return EAFA
 */
function eaf_api_init()
{
    static $eaf_api = null;

    if ($eaf_api === null) {
        $eaf_api = new EAFA();
    }

    return $eaf_api;
}

eaf_api_init();
