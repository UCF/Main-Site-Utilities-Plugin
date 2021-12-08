<?php
/**
 * Imports thumbnails for researchers from CSV Exports
 * provided by the College of Arts and Humanities
 */
namespace UCF\MainSiteUtilities\Importers {
	class CSVThumbnailImporter {
		private
			$csv_data,
			$base_url,
			$matched_records,
			$media_base_url,
			$force_update = false,

			$processed            = 0,
			$skipped_missing_info = 0,
			$skipped_no_match     = 0,
			$matched              = 0,
			$thumbnails_exist     = 0,
			$thumbnails_added     = 0,
			$thumbnails_deleted   = 0,
			$thumbnails_errors     = 0;

		/**
		 * Constructor
		 * @author Jim Barnes
		 * @since 2.0.0
		 * @param string $filepath The path to the WordPress export XML file
		 * @param string $base_url The URL to the WordPress API to retrieve files from
		 */
		public function __construct( $filepath, $base_url, $force_update = false ) {
			$this->matched_records = array();
			$this->force_update = $force_update;
			$this->base_url = $base_url;

			try {
				$rows = array_map( 'str_getcsv', file( $filepath ) );
				$headers = array_shift( $rows );
				$this->csv_data = array();

				foreach( $rows as $row ) {
					$this->csv_data[] = array_combine( $headers, $row );
				}
			} catch (Exception $e) {
				throw $e;
			}

			$this->media_base_url = "{$this->base_url}/wp-json/wp/v2/media";
		}

		/**
		 * Attempts to match a person from the importer
		 * using the provided email addresses.
		 * @author Jim Barnes
		 * @since 2.0.0
		 * @param string $email The email address from the file
		 * @return int The post ID of the returned record
		 */
		private function match_person_by_email( $email ) {
			$args = array(
				'post_type'      => 'person',
				'posts_per_page' => 1,
				'meta_query'     => array(
					array(
						'key'     => 'person_email',
						'value'   => $email,
						'compare' => '='
					)
				)
			);

			$posts = get_posts( $args );

			if ( count( $posts ) > 0 ) {
				return $posts[0]->ID;
			}

			return null;
		}

		/**
		 * Helper function for retrieving JSON
		 * values from the WP remote API.
		 * @author Jim Barnes
		 * @since 2.0.0
		 * @param string $url The URL to fetch
		 * @return mixed JSON-decoded object or false on failure
		 */
		private function fetch_json( $url ) {
			$response      = wp_remote_get( $url, array( 'timeout' => 10 ) );
			$response_code = wp_remote_retrieve_response_code( $response );
			$result        = false;

			if ( is_array( $response ) && is_int( $response_code ) && $response_code < 400 ) {
				$result = json_decode( wp_remote_retrieve_body( $response ) );
			}

			return $result;
		}

		/**
		 * Gets the image data for the person
		 * @author Jim Barnes
		 * @since 2.0.0
		 * @param int $id The remote ID of the person
		 * @return mixed
		 */
		private function get_person_image( $id ) {
			$url = "{$this->media_base_url}/{$id}";
			$media_data = $this->fetch_json( $url );

			if ( $media_data ) {
				$retval = $media_data->source_url;
				if ( strpos( $retval, strval( $this->base_url ) ) === false ) {
					$retval = $this->base_url . $retval;
				}

				return $retval;
			}

			return false;
		}

		/**
		 * Parses the CAH provided CSV file
		 * @author Jim Barnes
		 * @since 2.0.0
		 */
		private function parse_file() {
			foreach( $this->csv_data as $key => $item ) {
				$this->processed++;

				// Start person loop
				$person_email        = $item['email'];
				$person_thumbnail    = strpos( $item['rawPhotoUrl'], 'http' ) !== -1 ? $item['rawPhotoUrl'] : null;

				if ( $person_email === null || $person_thumbnail === null ) {
					$this->skipped_missing_info++;
					continue;
				}

				$match = $this->match_person_by_email( strtolower( $person_email ) );

				if ( ! $match ) {
					$this->skipped_no_match++;
					continue;
				}

				$this->matched_records[] = array(
					'post_id'  => $match,
					'photo_url' => $person_thumbnail
				);

				$this->matched++;
			}
		}

		/**
		 * Fetches and assigns the thumbnail images
		 * to the person records
		 * @author Jim Barnes
		 * @since 2.0.0
		 */
		private function fetch_images() {
			foreach( $this->matched_records as $record ) {
				$post_id = $record['post_id'];

				// If we're forcing updates, we need to delete any existing
				// thumbnails and set the attachment to null.
				if ( has_post_thumbnail( $post_id ) && $this->force_update ) {
					$p_attachment_id = get_post_thumbnail_id( $post_id );
					set_post_thumbnail( $post_id, null );

					if ( $p_attachment_id ) {
						wp_delete_attachment( $p_attachment_id, true );
						$this->thumbnails_deleted++;
					}
				} else if ( has_post_thumbnail( $post_id ) ) {
					$this->thumbnails_exist++;
					continue;
				}

				$image_url = $record['photo_url'];
				$filename = basename( $image_url );
				$tmp_file = download_url( $image_url, 30 );

				$upload_file = wp_upload_bits( $filename, null, @file_get_contents( $tmp_file ) );
				if ( ! $upload_file['error'] ) {
					$filetype = wp_check_filetype( $filename, null );

					$wp_upload_dir = wp_upload_dir();

					$attachment = array(
						'post_mime_type' => $filetype['type'],
						'post_title'     => preg_replace( '/\.[^.]+$/', '', $filename ),
						'post_content'   => '',
						'post_status'    => 'inherit',
						'post_parent'    => $record['post_id']
					);

					$attachment_id = wp_insert_attachment( $attachment, $upload_file['file'], $post_id );

					if ( ! is_wp_error( $attachment_id ) ) {
						$attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload_file['file'] );
						wp_update_attachment_metadata( $attachment_id, $attachment_data );
						set_post_thumbnail( $post_id, $attachment_id );

						$this->thumbnails_added++;
					}
				} else {
					$this->thumbnails_errors++;
				}
			}
		}

		/**
		 * Primary import function.
		 * @author Jim Barnes
		 * @since 2.0.0
		 */
		public function import() {
			$this->parse_file();
			$this->fetch_images();
		}

		/**
		 * Prints the stats of the import
		 * @author Jim Barnes
		 * @since 2.0.0
		 */
		public function print_stats() {
			return "
Records
======================================
Records Processed : {$this->processed}
Records Matched   : {$this->matched}
Skipped (Missing) : {$this->skipped_missing_info}
Skipped (No Match): {$this->skipped_no_match}

Thumbnails
=====================================
Added     : {$this->thumbnails_added}
Unchanged : {$this->thumbnails_exist}
Deleted   : {$this->thumbnails_deleted}
Errors    : {$this->thumbnails_errors}
			";
		}
	}
}
