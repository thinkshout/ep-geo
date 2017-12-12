# ep-geo
Geo query integration for ElasticPress

## Installation

1. Follow installation instructions for [ElasticPress](https://github.com/10up/ElasticPress#installation).
2. Install and activate this plugin (ep-geo) in WordPress.
3. Navigate to Admin > ElasticPress and activate "Geo".

## Usage

By default, this plugin looks in post meta fields named "latitude" and "longitude". They should be plain text fields with lat/lon represented as floats.

Find posts within 30mi of Portland, OR, USA, ordered by distance:

```php
new WP_Query( array(
    'ep_integrate'   => true,
    'posts_per_page' => 100,
    'post_type'      => 'post',
    'orderby'        => 'geo_distance',
    'order'          => 'asc',
    'geo_distance'   => array(
        'distance'           => '30mi',
        'geo_point.location' => array(
            'lat' => 45.5231,
            'lon' => -122.6765,
        ),
    ),
) );
```

If your latitude and longitude data is stored somewhere else, or if you need to calculate or preprocess the geo_point location, it's configurable with a hook:

```php
/**
 * Alter geo_point location to use my_lat/my_lon.
 */
add_filter( 'ep_geo_post_sync_geo_point', function ( $geo_point, $post_args, $post_id ) {
	$geo_point['location']['lat'] = get_field( 'my_lat', $post_id );
	$geo_point['location']['lon'] = get_field( 'my_lon', $post_id );

	return $geo_point;
}, 10, 3 );

```
