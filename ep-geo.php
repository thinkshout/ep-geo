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

if ( ! class_exists( 'ElasticPress\\Feature' ) ) {
	exit; // Exit if ElasticPress is not installed.
}

/**
 * ElasticPressGeoFeature class
 */
class ElasticPressGeoFeature extends \ElasticPress\Feature {

	/**
	 * Initialize feature settings.
	 */
	public function __construct() {
		$this->slug = 'ep_geo';

		$this->title = esc_html__( 'Geo', 'ep-geo' );

		$this->requires_install_reindex = true;

		parent::__construct();
	}

	/**
	 * Output feature box summary.
	 */
	public function output_feature_box_summary() {
		echo '<p>' . esc_html_e( 'Integrate geo location data with ElasticSearch, and enable geo queries.', 'ep-geo' ) . '</p>';
	}

	/**
	 * Output feature box long
	 */
	public function output_feature_box_long() {
		echo '<p>' . esc_html_e( 'By default, this plugin looks in post meta fields named "latitude" and "longitude". They should be plain text fields with lat/lon represented as floats.', 'ep-geo' ) . '</p>';
		echo '<p>' . esc_html_e( 'If your latitude and longitude data is stored somewhere else, or if you need to calculate or preprocess the geo_point location, it\'s configurable with a WordPress hook.', 'ep-geo' ) . '</p>';
	}

	/**
	 * Setup all feature filters
	 */
	public function setup() {
		add_filter( 'ep_config_mapping', [ $this, 'ep_geo_config_mapping' ] );
		add_filter( 'ep_post_sync_args', [ $this, 'ep_geo_post_sync_args' ] );
		add_filter( 'ep_formatted_args', [ $this, 'ep_geo_formatted_args' ] );
	}

	/**
	 * Alter ES index to add location property.
	 *
	 * @param array $mapping
	 *
	 * @return array
	 */
	public function ep_geo_config_mapping( $mapping ) {

		if ( !class_exists('ElasticPress\\Elasticsearch') || version_compare( \ElasticPress\Elasticsearch::factory()->get_elasticsearch_version(), '7.0', '<' ) ) {
		// Index geo_point:
			$mapping[ 'mappings' ]['post']['properties']['geo_point'] = array(
				'properties' => array(
					'location' => array(
						'type' => 'geo_point',
						'ignore_malformed' => true,
					),
				),
			);

			// Index geo_shape:
			$mapping[ 'mappings' ]['post']['properties']['geo_shape'] = array(
				'properties' => array(
					'location' => array(
						'type' => 'geo_shape',
						'ignore_malformed' => true,
					),
				),
			);
		} else {
			// Index geo_point:
			$mapping[ 'mappings' ]['properties']['geo_point'] = array(
				'properties' => array(
					'location' => array(
						'type' => 'geo_point',
						'ignore_malformed' => true,
					),
				),
			);

			// Index geo_shape:
			$mapping[ 'mappings' ]['properties']['geo_shape'] = array(
				'properties' => array(
					'location' => array(
						'type' => 'geo_shape',
						'ignore_malformed' => true,
					),
				),
			);
		}
		return $mapping;
	}

	/**
	 * Alter ES sync data to post geo_points.
	 *
	 * @param array $post_args
	 * @param int $post_id
	 *
	 * @return array
	 */
	public function ep_geo_post_sync_args( $post_args, $post_id ) {
		// Sync geo_point:
		$geo_point = [
			'location' => [],
		];

		if ( isset( $post_args['meta'] ) ) {
			$meta = $post_args['meta'];

			if ( ! empty( $meta['latitude'][0]['double'] ) ) {
				$geo_point['location']['lat'] = $meta['latitude'][0]['double'];
			}

			if ( ! empty( $meta['longitude'][0]['double'] ) ) {
				$geo_point['location']['lon'] = $meta['longitude'][0]['double'];
			}
		} elseif ( isset( $post_args['post_meta'] ) ) {
			// Handle legacy post_meta property, for older versions of elasticpress.
			$post_meta = $post_args['post_meta'];

			if ( isset( $post_meta['latitude'][0] ) ) {
				$geo_point['location']['lat'] = $post_meta['latitude'][0];
			}

			if ( isset( $post_meta['longitude'][0] ) ) {
				$geo_point['location']['lon'] = $post_meta['longitude'][0];
			}
		}

		$post_args['geo_point'] = apply_filters( 'ep_geo_post_sync_geo_point', $geo_point, $post_args, $post_id );

		// Sync geo_shape:
		$geo_shape = [
			'location' => [],
		];

		$post_args['geo_shape'] = apply_filters( 'ep_geo_post_sync_geo_shape', $geo_shape, $post_args, $post_id );

		return $post_args;
	}



	/**
	 * Alter formatted WP query args for geo filter.
	 *
	 * @param array $formatted_args
	 * @param array $args
	 *
	 * @return array
	 */
	public function ep_geo_formatted_args( $formatted_args, $args ) {
		if ( isset( $args['geo_shape'] ) ) {
			$formatted_args['post_filter']['bool']['filter']['geo_shape'] = $args['geo_shape'];
		} elseif ( isset( $args['geo_bounding_box'] ) ) {
			$formatted_args['post_filter']['bool']['filter']['geo_bounding_box'] = $args['geo_bounding_box'];
		} elseif ( isset( $args['geo_polygon'] ) ) {
			$formatted_args['post_filter']['bool']['filter']['geo_polygon'] = $args['geo_polygon'];
		} elseif ( isset( $args['geo_distance'] ) ) {
			$formatted_args['post_filter']['bool']['filter']['geo_distance'] = $args['geo_distance'];
		}

		if ( ! empty( $formatted_args['sort'] ) ) {
			foreach ( $formatted_args['sort'] as $key => &$sort ) {
				if ( isset( $sort['geo_distance'] ) ) {
					$sort['_geo_distance'] = $sort['geo_distance'];

					if ( isset( $args['geo_distance']['geo_point.location'] ) ) {
						$sort['_geo_distance']['geo_point.location'] = $args['geo_distance']['geo_point.location'];
					}

					unset( $sort['geo_distance'] );
				}
			}
		}

		// Legacy "geo_query" filter (deprecated):
		if ( isset( $args['geo_query'] ) ) {
			$geo_distance = array();

			if ( isset( $args['geo_query']['distance'] ) ) {
				$geo_distance['distance'] = $args['geo_query']['distance'];
			}

			if ( isset( $args['geo_query']['lat'] ) ) {
				$geo_distance['geo_point.location']['lat'] = $args['geo_query']['lat'];
			}

			if ( isset( $args['geo_query']['lon'] ) ) {
				$geo_distance['geo_point.location']['lon'] = $args['geo_query']['lon'];
			}

			$formatted_args['post_filter']['bool']['filter']['geo_distance'] = $geo_distance;

			if ( isset( $args['geo_query']['order'] ) ) {
				array_unshift( $formatted_args['sort'], array(
					'_geo_distance' => array(
						'geo_point.location' => $geo_distance['geo_point.location'],
						'order' => $args['geo_query']['order'],
					),
				) );
			}
		}

		return $formatted_args;
	}
}

ElasticPress\Features::factory()->register_feature(
	new EP_Geo()
);
