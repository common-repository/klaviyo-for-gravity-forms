<?php
/**
* Plugin Name: Klaviyo For Gravity Forms
* Plugin URI: https://themecafe.net
* Description: Automatically send data to Klaviyo with every Gravity Forms submission.
* Author: pluginscafe
* Author URI: https://pluginscafe.com
* Version: 1.0.0
* Text Domain: klaviyo-for-gravity-forms
* Domain Path: /languages/
* License: GPL-2.0+
* License URI: http://www.gnu.org/licenses/gpl-2.0.txt
*/


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'GF_KLAVIYO_FREE' ) ) {
	define( 'GF_KLAVIYO_FREE', '1.0.0' );
}


class GF_Klaviyo_Bootstrap {

    public static function load() {
        if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
            return;
        }

        require_once( 'class-klaviyo-feed.php' );
        require_once( 'includes/class-klaviyo-api.php' );

        GFAddOn::register( 'GF_Klaviyo_Free' );
    }
}

add_action( 'gform_loaded', array( 'GF_Klaviyo_Bootstrap', 'load' ), 5 );

function gf_klaviyo() {
	return GF_Klaviyo_Free::get_instance();
}