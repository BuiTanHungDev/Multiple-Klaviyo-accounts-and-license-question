<?php
/*
Plugin Name: Gravity Forms Klaviyo Add-On
Plugin URI: http://www.gravityforms.com
Description: Integrates Gravity Forms with Klaviyo allowing form submissions to be automatically sent to your Klaviyo account.
Version: 1.8.7
Author: Gravity Extra
Author URI: https://gravityextra.com/
*/

define('GF_KLAVIYO_API_VERSION', '1.8.7');

add_action('gform_loaded', array('GF_KLAVIYO_API', 'load'), 5);
class GF_KLAVIYO_API {
	public static function load() {
		if (!method_exists('GFForms', 'include_feed_addon_framework')) {
			return;
		}
		require_once('class-gfklaviyofeedaddon.php');
		GFAddOn::register('GFKlaviyoAPI');
	}

	public static function is_gf_version_min_2_5() {
		if (class_exists('GFForms')) {
			if (version_compare(GFCommon::$version, '2.5', '<')) {
				// Plugin version is less than 2.5
				return true;
			} else {
				// Plugin version is 2.5 or greater
				return false;
			}
		}
		return false;
	}
}

function gf_klaviyo_api_feed() {
	return GFKlaviyoAPI::get_instance();
}
