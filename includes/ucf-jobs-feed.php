<?php
/**
 * Handles all Jobs feed related code.
 **/

namespace UCF\MainSiteUtilities\Feeds;

/**
 * Retrieve the job listing data from the given feed url.
 *
 * @author Cadie Stockman
 * @since 3.0.0
 * @param array $args Arg array
 * @return object|false $result Job listings object data or false if JSON data cannot be retrieved
 **/
function retrieve_job_listing_data( $args ) {
	$feed_url = get_option( UCF_MAIN_SITE_UTILITIES__CUSTOMIZER_PREFIX . 'jobs_feed_url' );

	$feed_url = add_query_arg( array(
		'limit'      => $args['limit'],
		'offset'     => $args['offset'],
		'reset_cache'=> $args['reset_cache']
	), $feed_url );

	var_dump($feed_url);
	$response = wp_remote_get( $feed_url );
	$response_code = wp_remote_retrieve_response_code( $response );
	$result        = false;

	if ( is_array( $response ) && is_int( $response_code ) && $response_code < 400 ) {
		$result = json_decode( wp_remote_retrieve_body( $response ) );
	}

	return ( ! is_null( $result ) ) ? $result : false;
}
