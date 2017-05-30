<?php
/**
 * Implements the `mainsite` command
 **/
class Main_Site_Utilties extends WP_CLI_Command {
	/**
     * Main Site Utilities
     *
     * ## EXAMPLES
     * 
     * 	wp mainsite convert
     *
     * @when after_wp_load
     */
	public function convert( $args ) {
		$query_args = array(
			'post_type'    => 'degree',
			'numberposts'  => -1,
			'post_status'  => 'any'
		);

		$posts = get_posts( $query_args );

		foreach( $posts as $post ) {
			$this->convert_post_meta( $post );
		}

		WP_CLI::success('Successfully converted degrees...');
	}

	public function reset( $args ) {
		$count = 0;

		$query_args = array(
			'post_type'   => 'degree',
			'numberposts' => -1,
			'post_status' => 'any'
		);

		$posts = get_posts( $query_args );

		foreach( $posts as $post ) {
			wp_delete_post( $post->ID );
			$count++;
		}

		WP_CLI::success( 'Deleted ' . $count . ' degrees...' );
	}

	private function convert_post_meta( $post ) {
		$header_image = get_post_meta( $post->ID, 'degree_header_image', TRUE );

		if ( $header_image ) {
			if ( ! add_post_meta( $post->ID, 'page_header_image', $header_image ) ) {
				update_post_meta( $post->ID, 'page_header_image', $header_image );
			}
			delete_post_meta( $post->ID, 'degree_header_image' );
		}

		if ( ! add_post_meta( $post->ID, 'page_header_height', 'header-media-default' ) ) {
			update_post_meta( $post->ID, 'page_header_height', 'header-media-default' );
		}
	}
}
