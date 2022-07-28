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
			$this->import_research();
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
				'meta_key'       => 'person_type',
				'meta_value'     => 'faculty'
			) );

			$progress = \WP_CLI\Utils\make_progress_bar( "Deleting existing researchers...", count( $researchers ) );

			foreach( $researchers as $researcher ) {
				wp_delete_post( $researcher->ID, true );
				$this->researchers_deleted++;
				$progress->tick();
			}

			$progress->finish();
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
		 * @param array $items The array of JSON objects
		 * @return string
		 */
		public function format_citations_and_authors( $items, $author_post_id ) {
			return array_map( function( $item ) use ( $author_post_id ) {
				return array(
					'citation'                => $item->simple_citation_html,
					'additional_contributors' => $this->get_research_contributors( $item, $author_post_id )
				);
			}, $items );
		}

		/**
		 * Gets the UCF employees who contributed
		 * to this research project.
		 *
		 * @author Jim Barnes
		 * @since 3.0.1
		 * @param  object $obj The JSON object
		 * @param  int $author_post_id The post ID of the author being processed
		 * @return array The array of post_ids pointing to the researchers
		 */
		public function get_research_contributors( $obj, $author_post_id ) {
			$retval = array();

			foreach( $obj->researchers as $researcher ) {
				$post_id = isset( $this->post_ids_processed[$researcher->employee_id] ) ?
					$this->post_ids_processed[$researcher->employee_id] :
					null;

				if ( $post_id && $post_id !== $author_post_id ) {
					$retval[] = $post_id;
				}
			}

			return $retval;
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
			$retval = array();

			foreach( $array as $item ) {
				$term_data = wp_create_term( $item->name, $taxonomy_name );
				$retval[] = intval( $term_data['term_id'] );
			}

			return $retval;
		}

		/**
		 * Loops through the imported researchers
		 * and pulls their information and research
		 * @author Jim Barnes
		 * @since 2.0.0
		 * @return void
		 */
		private function import_researchers() {
			$progress = \WP_CLI\Utils\make_progress_bar( "Importing researchers...", count( $this->researchers ) );

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

				$post_meta = array(
					'person_employee_id' => $researcher->employee_record->ext_employee_id,
					'person_last_name'   => $researcher->employee_record->last_name,
					'person_titles'      => $job_titles,
					'person_email'       => $researcher->teledata_record ? $researcher->teledata_record->email : null,
					'person_phone'       => $researcher->teledata_record ? $researcher->teledata_record->phone : null,
					'person_degrees'     => $educational_info,
					'person_type'        => 'faculty',
				);

				if ( ! $existing || $this->force_template ) {
					$post_meta['_wp_page_template'] = 'template-faculty.php';
				}

				// Assign departments
				if ( $departments = $researcher->employee_record->departments ) {
					wp_set_post_terms( $post_id, $this->get_or_create_taxonomy_terms( $departments, 'departments' ), 'departments' );
				}
				// Assign colleges
				if ( $colleges = $researcher->employee_record->colleges ) {
					wp_set_post_terms( $post_id, $this->get_or_create_taxonomy_terms( $colleges, 'colleges' ), 'colleges' );
				}

				if ( $terms = $researcher->research_terms_featured ) {
					wp_set_post_terms( $post_id, $researcher->research_terms_featured, 'post_tag' );
				}

				foreach( $post_meta as $key => $val ) {
					\update_field( $key, $val, $post_id );
				}

				$this->post_ids_processed[$researcher->employee_record->ext_employee_id] = $post_id;
				$progress->tick();
			}

			$progress->finish();
		}

		/**
		 * Imports the research works for all the researchers
		 *
		 * @author Jim Barnes
		 * @since 3.0.1
		 * @return void
		 */
		private function import_research() {
			$progress = \WP_CLI\Utils\make_progress_bar( "Importing research...", count( $this->researchers ) );

			foreach( $this->researchers as $researcher ) {
				$post_id = isset( $this->post_ids_processed[$researcher->employee_record->ext_employee_id] ) ?
					$this->post_ids_processed[$researcher->employee_record->ext_employee_id] :
					null;

				if ( ! $post_id ) continue;

				$books_resp       = $this->fetch_json( $researcher->books );
				$articles_resp    = $this->fetch_json( $researcher->articles );
				$chapters_resp    = $this->fetch_json( $researcher->book_chapters );
				$proceedings_resp = $this->fetch_json( $researcher->conference_proceedings );
				$grants_resp      = $this->fetch_json( $researcher->grants );
				$awards_resp      = $this->fetch_json( $researcher->honorific_awards );
				$patents_resp     = $this->fetch_json( $researcher->patents );
				$trials_resp      = $this->fetch_json( $researcher->clinical_trials );


				$books       = $this->format_citations_and_authors( $books_resp->results, $post_id );
				$articles    = $this->format_citations_and_authors( $articles_resp->results, $post_id );
				$chapters    = $this->format_citations_and_authors( $chapters_resp->results, $post_id );
				$proceedings = $this->format_citations_and_authors( $proceedings_resp->results, $post_id );
				$grants      = $this->format_citations_and_authors( $grants_resp->results, $post_id );
				$awards      = $this->format_citations_and_authors( $awards_resp->results, $post_id );
				$patents     = $this->format_citations_and_authors( $patents_resp->results, $post_id );
				$trials      = $this->format_citations_and_authors( $trials_resp->results, $post_id );

				$post_meta = array(
					'person_books'       => $books,
					'person_articles'    => $articles,
					'person_chapters'    => $chapters,
					'person_proceedings' => $proceedings,
					'person_grants'      => $grants,
					'person_awards'      => $awards,
					'person_patents'     => $patents,
					'person_trials'      => $trials,
				);

				foreach( $post_meta as $key => $val ) {
					\update_field( $key, $val, $post_id );
				}

				$progress->tick();
			}

			$progress->finish();
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
				'post__not_in'   => array_values( $this->post_ids_processed ),
				'posts_per_page' => -1,
				'meta_key'       => 'person_type',
				'meta_value'     => 'faculty'
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
