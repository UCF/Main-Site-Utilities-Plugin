<?php
/**
 * Returns whether or not the given URL looks like a valid degree website
 * value.
 *
 * @since 2.0.0
 * @author Jo Dickson
 * @param string $url Degree website URL to check against
 * @return boolean
 */
function mainsite_utils_degree_website_is_valid( $url ) {
	if ( substr_count( $url, '://' ) === 1 && preg_match( '/^http(s)?\:\/\//', $url ) && filter_var( $url, FILTER_VALIDATE_URL ) !== false ) {
		return true;
	}
	return false;
}

/**
 * Apply main site-specific meta data to degrees during the degree import
 * process.
 *
 * @since 2.0.0
 * @author Jo Dickson
 */
function mainsite_utils_degree_format_post_data( $post_array_item, $program ) {
	$post_array_item['post_meta']['degree_hours']       = $program->required_hours;
	$post_array_item['post_meta']['degree_website']     = mainsite_utils_degree_website_is_valid( $program->website ) ? $program->website : '';
	$post_array_item['post_meta']['degree_is_graduate'] = filter_var( $program->graduate, FILTER_VALIDATE_BOOLEAN );
	$post_array_item['post_meta']['page_header_height'] = 'header-media-default';

	return $post_array_item;
}

add_filter( 'ucf_degree_format_post_data', 'mainsite_utils_degree_format_post_data', 10, 2 );
