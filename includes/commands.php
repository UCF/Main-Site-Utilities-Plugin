<?php
/**
 * Commands for importing researchers and research
 */
namespace UCF\MainSiteUtilities\Commands {

	use UCF\MainSiteUtilities\Importers;

	class ResearchCommands extends \WP_CLI_Command {

		/**
		 * Imports research from the UCF Search Service
		 *
		 * ## OPTIONS
		 *
		 * <search_url>
		 * : The URL of the UCF Search Service
		 *
		 * [--params=<params>]
		 * : Additional URL parameters to pass to the Search Service
		 *
		 * [--force-template=<bool>]
		 * : Whether or not to force update the WordPress template
		 *
		 * [--force-update=<bool>]
		 * : Whether or not all records will be deleted prior to import
		 *
		 * ## EXAMPLES
		 *
		 *     wp research import http://127.0.0.1:8000/api/v1/ --force-template=True
		 *
		 * @when after_wp_load
		 */
		public function import( $args, $assoc_args ) {
			list( $search_url ) = $args;
			$params = $assoc_args['params'] ?? null;
			$force_template = filter_var( $assoc_args['force-template'] ?? false, FILTER_VALIDATE_BOOLEAN );
			$force_update = filter_var( $assoc_args['force-update'] ?? false, FILTER_VALIDATE_BOOLEAN );

			$importer = new Importers\ResearchImporter( $search_url, $params, $force_template, $force_update );

			try {
				$importer->import();
				\WP_CLI::success( $importer->get_stats() );
			} catch( Exception $e ) {
				\WP_CLI::error( $e->message );
			}
		}

		/**
		 * Imports thumbnails for matched researchers
		 *
		 * ## OPTIONS
		 *
		 * <filepath>
		 * : The path to the WordPress XML file for special CSV for importing
		 *
		 * [--post-meta-key=<post_meta>]
		 * : The meta key to use to find email.
		 *
		 * [--base-url=<base_url>]
		 * : The WP-JSON base URL to use to retrieve thumbnails.
		 *
		 * [--force-update=<bool>]
		 * : Determines if existing thumbnails will be deleted.
		 *
		 * [--use-cah-importer=<bool>]
		 * : Whether or not to use the special CAH importer for their CSV file.
		 *
		 * ## EXAMPLES
		 *
		 *     wp research thumbnails wp-export.csv
		 *
		 * @when after_wp_load
		 */
		public function thumbnails( $args, $assoc_args ) {
			list( $filepath ) = $args;
			$base_url      = $assoc_args['base-url'] ?? null;
			$post_meta_key = $assoc_args['post-meta-key'] ?? 'person_email';
			$force_update  = filter_var( $assoc_args['force-update'] ?? false, FILTER_VALIDATE_BOOLEAN );
			$cah_importer  = filter_var( $assoc_args['use-cah-importer'] ?? false, FILTER_VALIDATE_BOOLEAN );

			if ( $cah_importer ) {
				$importer = new Importers\CSVThumbnailImporter( $filepath, $base_url, $force_update );
			} else {
				$importer = new Importers\WordPressThumbnailImporter( $filepath, $base_url, $post_meta_key, $force_update );
			}

			try {
				$importer->import();
				\WP_CLI::success( $importer->print_stats() );
			} catch ( Exception $e ) {
				\WP_CLI::error( $e->message );
			}
		}
	}

	class ExpertCommands extends \WP_CLI_Command {
		/**
		 * Imports experts from a remote CSV file
		 *
		 * ## OPTIONS
		 *
		 *	<csv_file>
		 * : The URL of the CSV file to import
		 *
		 * [--force-template=<bool>]
		 * : Whether or not to force update the WordPress template
		 *
		 * [--force-update=<bool>]
		 * : Whether or not all records will be deleted prior to import
		 *
		 * ## EXAMPLES
		 *
		 *     wp expert import http://127.0.0.1:8000/file.csv --force-template=True
		 *
		 * @when after_wp_load
		 */
		public function import( $args, $assoc_args ) {
			list( $csv_url ) = $args;
			$force_template = filter_var( $assoc_args['force-template'] ?? false, FILTER_VALIDATE_BOOLEAN );
			$force_update = filter_var( $assoc_args['force-update'] ?? false, FILTER_VALIDATE_BOOLEAN );

			$importer = new Importers\ExpertImporter( $csv_url, $force_template, $force_update );

			try {
				$importer->import();
				\WP_CLI::success( $importer->get_stats() );
			} catch( Exception $e ) {
				\WP_CLI::error( $e->message );
			}
		}
	}
}
