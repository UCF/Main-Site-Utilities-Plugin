<?php
/**
 * Handles all Jobs feed related code.
 **/

namespace UCF\MainSiteUtilities\Feeds;

/**
 * Retrieve the job listing data from the given feed url.
 *
 * @author Cadie Stockman
 * @since 2.1.0
 * @param array $args Arg array
 * @return object $result Job listings data
 **/
function retrieve_job_listing_data( $args ) {
	$feed_url = get_option( UCF_MAIN_SITE_UTILITIES__CUSTOMIZER_PREFIX . 'jobs_feed_url' );
	$post_args = array(
		'body' => wp_json_encode( array(
			// 'appliedFacets' => {}, // TODO: Add feature for defining job type filters
			'limit'      => $args['limit'],
			'offset'     => $args['offset'],
			'searchText' => ''
		) ),
		'headers' => array(
			'content-type' => 'application/json'
		)
	);

	$response      = wp_remote_post( $feed_url, $post_args );
	$response_code = wp_remote_retrieve_response_code( $response );
	$result        = false;

	if ( is_array( $response ) && is_int( $response_code ) && $response_code < 400 ) {
		$response_body = wp_remote_retrieve_body( $response );
		$result = validate_json( $response_body ) ? json_decode( $response_body ) : false;
	}

	return $result;
}


/**
 * Checks if the given string is valid JSON.
 *
 * @author Cadie Stockman
 * @since 3.0.0
 * @param string $str Given string
 * @return boolean Returns true if valid JSON.
 **/
function validate_json( $str ) {
	if ( is_string( $str ) ) {
		json_decode( $str );
		return ( json_last_error() === JSON_ERROR_NONE );
	}

	return false;
}
