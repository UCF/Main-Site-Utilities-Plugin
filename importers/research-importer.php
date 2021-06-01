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
			$researchers_skipped   = 0;

		/**
		 * Constructor
		 * @author Jim Barnes
		 * @since 2.0.0
		 * @param string $search_service_url The base url of the search service
		 * @param array $params Additional parameters to pass to the search service
		 * @param bool $force_template Forces the template value to be updated.
		 */
		public function __construct( $search_service_url, $params, $force_template=false ) {
			$this->api_url = \trailingslashit( $search_service_url );
			$this->params = $params;
			$this->force_template = $force_template;
			$this->researchers = array();
		}

		/**
		 * The import function
		 * @author Jim Barnes
		 * @since 2.0.0
		 */
		public function import() {
			$this->get_researchers();
			$this->import_researchers();
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
		 * Loops through the imported researchers
		 * and pulls their information and research
		 * @author Jim Barnes
		 * @since 2.0.0
		 * @return void
		 */
		private function import_researchers() {
			foreach( $this->researchers as $researcher ) {
				$this->researchers_processed++;

				# No research? SKIP!
				if ( $researcher->works_count === 0 ) {
					$this->researchers_skipped++;
					continue;
				}

				$existing = $this->get_researcher_record( $researcher->orcid_id );

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

				// Update the post meta
				$educational_info = array();

				// Capture all of the educational information
				foreach( $researcher->education as $edu ) {
					$educational_info[] = array(
						'insitution_name' => $edu->institution_name,
						'role_name'       => $edu->role_name,
						'start_date'      => $edu->start_date,
						'end_date'        => $edu->end_date,
						'department_date' => $edu->department_name
					);
				}

				$works = $this->fetch_json( $researcher->works );

				$books    = array();
				$articles = array();

				foreach( $works->results as $work ) {
					if ( ! empty( $work->citation ) ) {
						if ( $work->work_type === 'BOOK' ) {
							$books[] = array(
								'book_citation' => $work->citation
							);
						} else if ( $work->work_type === 'JOURNAL_ARTICLE' ) {
							$articles[] = array(
								'article_citation' => $work->citation
							);
						}
					}
				}

				$post_meta = array(
					'person_orcid_id'   => $researcher->orcid_id,
					'person_title'      => $researcher->teledata_record->job_position,
					'person_email'      => $researcher->teledata_record->email,
					'person_phone'      => $researcher->teledata_record->phone,
					'person_office'     => "{$researcher->teledata_record->bldg->abbr} {$researcher->teledata_record->room}",
					'person_department' => $researcher->teledata_record->dept->name,
					'person_degrees'    => $educational_info,
					'person_books'      => $books,
					'person_articles'   => $articles,
					'person_type'       => 'faculty',
				);

				if ( ! $existing || $this->force_template ) {
					$post_meta['_wp_page_template'] = 'template-faculty.php';
				}

				foreach( $post_meta as $key => $val ) {
					\update_field( $key, $val, $post_id );
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
		private function get_researcher_record( $orcid_id ) {
			$args = array(
				'meta_key'       => 'person_orcid_id',
				'meta_value'     => $orcid_id,
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
