<?php
/**
 * Plugin Name:     ElasticPress Geo
 * Plugin URI:      https://github.com/thinkshout/ep-geo
 * Description:     Geo query integration for ElasticPress
 * Author:          ThinkShout
 * Author URI:      https://thinkshout.com/
 * Text Domain:     ep-geo
 * Domain Path:     /languages
 * Version:         0.1.3
 *
 * @package         Ep_Geo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

function load_ep_geo() {
	if ( class_exists( '\ElasticPress\Features' ) ) {
		require 'ElasticPressGeoFeature.php';
		ElasticPress\Features::factory()->register_feature(
			new ElasticPressGeoFeature()
		);
	}
}
add_action( 'plugins_loaded', 'load_ep_geo', 11 );