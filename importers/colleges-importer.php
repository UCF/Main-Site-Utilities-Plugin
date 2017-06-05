<?php
/**
 * Imports meta data for colleges
 **/
class Colleges_Importer {
	private
		$search_api,
		$colleges,
		$processed_count = 0,
		$updated_count = 0,
		$failure_count = 0;

	/**
	 * Constructor
	 * @author Jim Barnes
	 * @since 1.0.0
	 * @param $search_url string | The url of the search service
	 * @return Colleges_Importer
	 **/
	public function __construct( $search_url ) {
		$this->search_api = $search_url;
		$this->colleges = array();
	}

	/**
	 * Imports the colleges meta data into WordPress
	 * @author Jim Barnes
	 * @since 1.0.0
	 **/
	public function import() {
		// Fill the colleges array
		$this->colleges = $this->get_colleges();

		// Process the colleges array
		$this->update_colleges();
	}

	/**
	 * Returns the success statistics of the import
	 * @author Jim Barnes
	 * @since 1.0.0
	 * @return string | The success statistics as a string
	 **/
	public function get_stats() {
		return
		"
Finished processing colleges.

Total   : {$this->processed_total}

Updated : {$this->updated_count}
Failed  : {$this->failure_count}
		";
	}

	/**
	 * Gets the colleges currently in WordPress
	 * @author Jim Barnes
	 * @since 1.0.0
	 * @return array<WP_TERM> | An array of WP_Term obejcts
	 **/
	private function get_colleges() {
		$args = array(
			'taxonomy'   => 'colleges',
			'hide_empty' => false
		);

		$retval = get_terms( $args );

		if ( is_array( $retval ) ) {
			$this->processed_count = count( $retval );
			return $retval;
		} else {
			throw new Exception(
				"An error occurred when fetching colleges. " .
				"{$retval->get_error_message()}",
				$retval->get_error_code()
			);
		}
	}

	/**
	 * Loops through the colleges and sets the post meta.
	 * @author Jim Barnes
	 * @since 1.0.0
	 **/
	private function update_colleges() {
		foreach( $this->colleges as $college ) {
			$college_id = $this->get_college_id( $college->term_id );
			$search_data = $this->get_college_from_search( $college->name, $college_id );

			// If we have search data, update.
			if ( $search_data ) {
				$this->set_meta_data( $college->term_id, $search_data );
			}
		}
	}

	/**
	 * Tries to get the college_id from term_meta if set.
	 * @author Jim Barnes
	 * @since 1.0.0
	 * @param $term_id int | The term id
	 * @return int|null | Returns the id if it's set.
	 **/
	private function get_college_id( $term_id ) {
		$retval = get_term_meta( $term_id, 'colleges_search_id', true );

		return $retval;
	}

	/**
	 * Returns data from the search service for the college
	 * @author Jim Barnes
	 * @since 1.0.0
	 * @param $college_name string | The college's name
	 * @param $college_id int | The search service id of the college
	 * @return Object | Data from the search service.
	 **/
	private function get_college_from_search( $college_name, $college_id=null ) {
		$retval = null;

		$query = array(
			'in'  => 'organizations'
		);

		// If we have an id, search using that.
		$query['search'] = $college_id ? $college_id : $college_name;

		$url = $this->search_api . '?' . http_build_query( $query );

		$args = array(
			'timeout' => 15
		);

		$response = wp_remote_get( $url, $args );

		if ( is_array( $response ) ) {
			$response_body = wp_remote_retrieve_body( $response );
			$retval = json_decode( $response_body );

			if ( ! $retval ) {
				$this->failure_count++;
				return null;
			}
		} else {
			$this->failure_count++;
			return null;
		}

		if ( count( $retval->results ) === 0 ) {
			WP_CLI::log( 'No results found for ' . $college_name );
			$this->failure_count++;
			return null;
		}

		return $retval->results[0];
	}

	/**
	 * Set the meta data we get from the search service.
	 * @author Jim Barnes
	 * @since 1.0.0
	 * @param $term_id int | The term id
	 * @param $search_data Object | The search data
	 **/
	private function set_meta_data( $term_id, $search_data ) {
		if ( ! add_term_meta( $term_id, 'colleges_search_id', $search_data->id, true ) ) {
			update_term_meta( $term_id, 'colleges_search_id', $search_data->id );
		}

		$this->updated_count++;
	}
}
