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
	}
}
