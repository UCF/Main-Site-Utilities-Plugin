<?php
/**
 * Research and Researcher importer
 */
namespace UCF\MainSiteUtilities\Importers {
	class ResearchImporter {
		private
			$api_url,
			$params,
			$researchers,

			// Researcher counts
			$researchers_processed = 0,
			$researchers_created   = 0,
			$researchers_updated   = 0,
			$researchers_skipped   = 0,
			$researchers_deleted   = 0,

			// Let's hold onto these
			$post_ids_processed    = [];

		/**
		 * Constructor
		 * @author Jim Barnes
		 * @since 2.0.0
		 * @param string $search_service_url The base url of the search service
		 * @param array $params Additional parameters to pass to the search service
		 * @param bool $force_template Forces the template value to be updated.
		 * @param bool $force_update Deletes all existing records before importing
		 */
		public function __construct( $search_service_url, $params, $force_template=false, $force_update=false ) {
			$this->api_url = \trailingslashit( $search_service_url );
			$this->params = $params;
			$this->force_template = $force_template;
			$this->force_update = $force_update;
			$this->researchers = array();
		}

		/**
		 * The import function
		 * @author Jim Barnes
		 * @since 2.0.0
		 */
		public function import() {
			if ($this->force_update) {
				$this->delete_existing_researchers();
			}

			$this->get_researchers();
			$this->import_researchers();
			$this->delete_stale_records();
		}

		/**
		 * Returns the formatted string of
		 * stats from the import
		 * @author Jim Barnes
		 * @since 2.0.0
		 * @return string
		 */
		public function get_stats() {
			return "
Researchers
-----------

Processed: {$this->researchers_processed}
Created  : {$this->researchers_created}
Updated  : {$this->researchers_updated}
Skipped  : {$this->researchers_skipped}
Deleted  : {$this->researchers_deleted}

			";
		}

		/**
		 * Helper function that retrieves JSON
		 * results from a URL
		 * @author Jim Barnes
		 * @since 2.0.0
		 * @param string $url The URL to fetch the results from
		 * @param mixed Returns an object if parsed, false if failed
		 */
		private function fetch_json( $url ) {
			$response      = \wp_remote_get( $url, array( 'timeout' => 10 ) );
			$response_code = \wp_remote_retrieve_response_code( $response );
			$result        = false;

			if ( is_array( $response ) && is_int( $response_code ) && $response_code < 400 ) {
				$result = json_decode( \wp_remote_retrieve_body( $response ) );
			}

			return $result;
		}

		/**
		 * Deletes all existing researcher records
		 * @author Jim Barnes
		 * @since 2.0.0
		 * @return void
		 */
		private function delete_existing_researchers() {
			$researchers = get_posts( array(
				'post_type'      => 'person',
				'posts_per_page' => -1,
				'meta_key'       => '_wp_page_template',
				'meta_value'     => 'template-faculty.php'
			) );

			foreach( $researchers as $researcher ) {
				wp_delete_post( $researcher->ID, true );
				$this->researchers_deleted++;
			}
		}

		/**
		 * Gets all the researcher records from
		 * the search service.
		 * @author Jim Barnes
		 * @since 2.0.0
		 * @return void
		 */
		private function get_researchers() {
			$url = "{$this->api_url}research/researchers/";

			if ( ! empty( $this->params ) ) {
				$url .= "?" . http_build_query( $this->params );
			}

			$next_url = $url;

			while ( $next_url ) {
				$response = $this->fetch_json( $next_url );

				if ( ! $response || ! isset( $response->results ) ) {
					throw new \Exception( 'Failed to parse the Search Service JSON.' );
				}

				if ( count( $response->results ) === 0 ) {
					throw new \Exception( 'No results found.' );
				}

				$next_url = $response->next ?? false;

				$this->researchers = array_merge( $this->researchers, $response->results );
			}
		}

		/**
		 * Helper function used by the array_map
		 * when retrieving research citations
		 * @author Jim Barnes
		 * @since 2.0.0
		 * @param object $obj The JSON object
		 * @return string
		 */
		public function get_simple_citation_html( $obj ) {
			return array(
				'citation' => $obj->simple_citation_html
			);
		}

		/**
		 * Helper function used to return or create a
		 * WP_Term object for the given taxonomy.
		 * @author Cadie Stockman
		 * @since 2.0.0
		 * @param array $array The array
		 * @param string $taxonomy_name The taxonomy name to create/assign terms to
		 * @return string
		 */
		public function get_or_create_taxonomy_terms( $array, $taxonomy_name ) {
			foreach( $array as $item ) {
				$term_id = null;
				$term_data = get_term_by( 'name', $item->name, $taxonomy_name, ARRAY_A );

				if ( ! $term_data ) {
					$term_data = wp_insert_term( $item->name, $taxonomy_name );
				}

				if ( isset( $term_data['term_id'] ) ) {
					$term_id = $term_data['term_id'];
				}

				return $term_id;
			}
		}

		/**
		 * Loops through the imported researchers
		 * and pulls their information and research
		 * @author Jim Barnes
		 * @since 2.0.0
		 * @return void
		 */
		private function import_researchers() {
			foreach( $this->researchers as $researcher ) {
				$this->researchers_processed++;

				$existing = $this->get_researcher_record( $researcher->employee_record->ext_employee_id );

				$post_data = array(
					'post_title'        => $researcher->name_formatted_title,
					'post_name'         => sanitize_title( $researcher->name_formatted_no_title ),
					'post_status'       => 'publish',
					'post_author'       => 1,
					'post_type'         => 'person'
				);

				$post_id = null;

				if ( $existing ) {
					$post_data['ID']          = $existing->ID;
					$post_id                  = $existing->ID;
					$post_data['post_status'] = $existing->post_status;

					\wp_update_post( $post_data );

					$this->researchers_updated++;
				} else {
					$post_id = \wp_insert_post( $post_data );
					$this->researchers_created ++;
				}

				$this->post_ids_processed[] = $post_id;

				// Capture all of the job titles
				$job_titles = array();

				foreach( $researcher->employee_record->job_titles as $job ) {
					$job_titles[] = array(
						'job_title' => $job->name
					);
				}

				// Update the post meta
				$educational_info = array();

				// Capture all of the educational information
				foreach( $researcher->education as $edu ) {
					$educational_info[] = array(
						'institution_name' => $edu->institution_name,
						'role_name'        => $edu->role_name,
						'start_date'       => $edu->start_date,
						'end_date'         => $edu->end_date,
						'department_name'  => $edu->department_name
					);
				}

				$books_resp       = $this->fetch_json( $researcher->books );
				$articles_resp    = $this->fetch_json( $researcher->articles );
				$chapters_resp    = $this->fetch_json( $researcher->book_chapters );
				$proceedings_resp = $this->fetch_json( $researcher->conference_proceedings );
				$grants_resp      = $this->fetch_json( $researcher->grants );
				$awards_resp      = $this->fetch_json( $researcher->honorific_awards );
				$patents_resp     = $this->fetch_json( $researcher->patents );
				$trials_resp      = $this->fetch_json( $researcher->clinical_trials );

				$books       = array_map( array($this, 'get_simple_citation_html'), $books_resp->results );
				$articles    = array_map( array($this, 'get_simple_citation_html'), $articles_resp->results );
				$chapters    = array_map( array($this, 'get_simple_citation_html'), $chapters_resp->results );
				$proceedings = array_map( array($this, 'get_simple_citation_html'), $proceedings_resp->results );
				$grants      = array_map( array($this, 'get_simple_citation_html'), $grants_resp->results );
				$awards      = array_map( array($this, 'get_simple_citation_html'), $awards_resp->results );
				$patents     = array_map( array($this, 'get_simple_citation_html'), $patents_resp->results );
				$trials      = array_map( array($this, 'get_simple_citation_html'), $trials_resp->results );

				$post_meta = array(
					'person_employee_id' => $researcher->employee_record->ext_employee_id,
					'person_last_name'   => $researcher->employee_record->last_name,
					'person_titles'      => $job_titles,
					'person_email'       => $researcher->teledata_record->email,
					'person_phone'       => $researcher->teledata_record->phone,
					'person_degrees'     => $educational_info,
					'person_books'       => $books,
					'person_articles'    => $articles,
					'person_chapters'    => $chapters,
					'person_proceedings' => $proceedings,
					'person_grants'      => $grants,
					'person_awards'      => $awards,
					'person_patents'     => $patents,
					'person_trials'      => $trials,
					'person_type'        => 'faculty',
				);

				if ( ! $existing || $this->force_template ) {
					$post_meta['_wp_page_template'] = 'template-faculty.php';
				}

				// Assign departments
				wp_set_post_terms( $post_id, array( $this->get_or_create_taxonomy_terms( $researcher->employee_record->departments, 'departments' ) ), 'departments' );
				// Assign colleges
				wp_set_post_terms( $post_id, array( $this->get_or_create_taxonomy_terms( $researcher->employee_record->colleges, 'colleges' ) ), 'colleges' );

				foreach( $post_meta as $key => $val ) {
					\update_field( $key, $val, $post_id );
				}
			}
		}

		/**
		 * Gets all posts that were not processed
		 * and removed them.
		 * @author Jim Barnes
		 * @since 2.0.0
		 */
		private function delete_stale_records() {
			$args = array(
				'post_type'      => 'person',
				'post__not_in'   => $this->post_ids_processed,
				'posts_per_page' => -1
			);

			$stale_posts = get_posts( $args );

			foreach( $stale_posts as $post ) {
				$post = wp_delete_post( $post->ID, true );

				if ( $post ) {
					$this->researchers_deleted++;
				}
			}
		}

		/**
		 * Returns a WP_Post researcher object if
		 * one exists. Returns false if not.
		 * @author Jim Barnes
		 * @since 2.0.0
		 * @param string $orcid_id
		 * @return mixed WP_Post if found, false if not
		 */
		private function get_researcher_record( $empl_id ) {
			$args = array(
				'meta_key'       => 'person_employee_id',
				'meta_value'     => $empl_id,
				'post_type'      => 'person',
				'posts_per_page' => 1,
			);

			$posts = get_posts( $args );

			if ( count( $posts ) > 0 ) {
				return $posts[0];
			}

			return false;
		}
	}
}
