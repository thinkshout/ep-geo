# ep-geo
Geo query integration for ElasticPress

## Installation

1. Follow installation instructions for [ElasticPress](https://github.com/10up/ElasticPress#installation).
2. Install and activate this plugin (ep-geo) in WordPress.
3. Navigate to Admin > ElasticPress and activate "Geo".

## Usage

Important note: Your geolocation data must be stored in post meta fields named "latitude" and "longitude". They should be plain text fields with lat/lon represented as floats.

To find all posts within 30km of Portland, OR, USA:

```php
new WP_Query( array(
	'ep_integrate'   => true,
	'posts_per_page' => 100,
	'post_type'      => 'post',
	'geo_query'      => array(
		'lat'      => 45.5230622,
		'lon'      => - 122.67648159999999,
		'distance' => '30km',
		'order'    => 'asc',
	),
) );
```
