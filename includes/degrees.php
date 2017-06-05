<?php
/**
 * Commands for creating and updating degrees
 */
class Degrees extends WP_CLI_Command {
	/**
	 * Converts Main-Site-Themes degrees to v3 degrees.
	 *
	 * ## EXAMPLES
	 *
	 * $ wp mainsite degrees convert
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

	/**
	 * Removes all degrees
	 *
	 * ## EXAMPLES
	 *
	 * # Removes all degrees
	 * $ wp mainsite degrees reset
	 *
	 * @when after_wp_load
	 */
	public function reset( $args ) {
		WP_CLI::confirm("This will delete all degrees, colleges, program_types and departments. Would you like to proceed?");

		$degree_count = 0;

		$query_args = array(
			'post_type'   => 'degree',
			'numberposts' => -1,
			'post_status' => 'any'
		);

		$posts = get_posts( $query_args );

		foreach( $posts as $post ) {
			wp_delete_post( $post->ID );
			$degree_count++;
		}

		WP_CLI::log( 'Deleted ' . $degree_count . ' degrees...' );

		$taxonomies = array(
			'program_types' => get_terms( array(
					'taxonomy'   => 'program_types',
					'fields'     => 'ids',
					'hide_empty' => false
				)
			),
			'colleges' => get_terms( array(
					'taxonomy'   => 'colleges',
					'fields'     => 'ids',
					'hide_empty' => false
				)
			),
			'departments' => get_terms( array(
					'taxonomy'   => 'departments',
					'fields'     => 'ids',
					'hide_empty' => false
				)
			)
		);

		foreach( $taxonomies as $taxonomy => $terms ) {
			foreach( $terms as $term ) {
				wp_delete_term( $term, $taxonomy );
			}
		}

		WP_CLI::success('All done!');
	}

	/**
	 * Imports degrees from the search service.
	 *
	 * ## OPTIONS
	 *
	 * <search_url>
	 * : The url of the search service you want to pull from. (Required)
	 *
	 * <catalog_url>
	 * : The url of the undergraduate catalog. (Required)
	 *
	 * ## EXAMPLES
	 *
	 * # Imports degrees from the dev search service.
	 * $ wp mainsite degrees import https://searchdev.smca.ucf.edu
	 *
	 * @when after_wp_load
	 */
	public function import( $args, $assoc_args ) {
		$search_url  = $args[0];
		$catalog_url = $args[1];

		$import = new Degree_Importer( $search_url, $catalog_url );

		try {
			$import->import();
		}
		catch( Exception $e ) {
			WP_CLI::error( $e->getMessage(), $e->getCode() );
		}

		WP_CLI::success( $import->get_stats() );
	}

	/**
	 * Updates tuition and fee information on degrees
	 *
	 * ## OPTIONS
	 *
	 * <api>
	 * : The url of the tuition and fees feed you want to pull from. (Required)
	 *
	 * ## EXAMPLES
	 *
	 * # Imports tuition for main site degrees.
	 * $ wp mainsite degrees tuition http://www.studentaccounts.ucf.edu/feed/feed.cfm
	 *
	 * @when after_wp_load
	 */
	public function tuition( $args, $assoc_args ) {
		$api = $args[0];

		$import = new Tuition_Fees_Importer( $api );

		try {
			$import->import();
		}
		catch( Exception $e ) {
			WP_CLI::error( $e->getMessage(), $e->getCode() );
		}

		WP_CLI::success( $import->get_stats() );
	}

	/**
	 * Updates colleges with meta data from search service.
	 *
	 * ## OPTIONS
	 *
	 * <search_url>
	 * : The url of the search service. (Required)
	 *
	 * ## EXAMPLES
	 *
	 * # Updates meta data from the UCF Search service
	 * $ wp mainsite degrees colleges https://search.smca.ucf.edu/service.php
	 *
	 * @when after_wp_load
	 */
	public function colleges( $args, $assoc_args ) {
		$search_url = $args[0];

		$import = new Colleges_Importer( $search_url );

		try {
			$import->import();
		}
		catch( Exception $e ) {
			WP_CLI::error( $e->getMessage(), $e->getCode() );
		}

		WP_CLI::success( $import->get_stats() );
	}

	/**
	 * Converts post meta to the v3 format
	 **/
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
