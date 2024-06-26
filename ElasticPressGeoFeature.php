<?php

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
		add_filter( 'ep_config_mapping', [ $this, 'ep_geo_config_mapping' ], 10, 2 );
		add_filter( 'ep_post_sync_args', [ $this, 'ep_geo_post_sync_args' ], 10, 2 );
		add_filter( 'ep_formatted_args', [ $this, 'ep_geo_formatted_args' ], 10, 2 );
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

		if ( isset( $post_args['meta'] ) && isset( $post_args['meta']['latitude'] ) ) {
			$meta = $post_args['meta'];

			if ( ! empty( $meta['latitude'][0]['double'] ) ) {
				$geo_point['location']['lat'] =  (float) number_format( $meta['latitude'][0]['double'], 6, '.', '' );
			}

			if ( ! empty( $meta['longitude'][0]['double'] ) ) {
				$geo_point['location']['lon'] = (float) number_format( $meta['longitude'][0]['double'], 6, '.', '' );
			}
		} elseif ( isset( $post_args['post_meta'] ) && isset( $post_args['post_meta']['latitude'] ) ) {
			// Handle legacy post_meta property, for older versions of elasticpress.
			$post_meta = $post_args['post_meta'];

			if ( isset( $post_meta['latitude'][0] ) ) {
				$geo_point['location']['lat'] = (float) number_format( $post_meta['latitude'][0], 6, '.', '' );
			}

			if ( isset( $post_meta['longitude'][0] ) ) {
				$geo_point['location']['lon'] = (float) number_format( $post_meta['longitude'][0], 6, '.', '' );
			}
		} elseif ( !empty( get_post_meta( $post_id, 'latitude', true ) ) ) {

			$lat = (float) number_format( get_post_meta( $post_id, 'latitude', true ), 6, '.', '' );
			if ( ! empty( $lat ) ) {
				$geo_point['location']['lat'] = $lat;
				$post_args['meta']['latitude'][0]['double'] = $lat;
			}

			$lon = (float) number_format( get_post_meta( $post_id, 'longitude', true ), 6, '.', '' );
			if ( ! empty( $lon ) ) {
				$geo_point['location']['lon'] = $lon;
				$post_args['meta']['longitude'][0]['double'] = $lon;
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