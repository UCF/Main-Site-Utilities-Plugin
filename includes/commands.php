<?php
/**
 * Commands for importing researchers and research
 */
namespace UCF\MainSiteUtilities\Commands {

	use UCF\MainSiteUtilities\Importers;

	class ResearchCommands extends \WP_CLI_Command {
		public function import( $args, $assoc_args ) {
			$search_url = $assoc_args['search-service-url'] ?? null;
			$params = $assoc_args['additional-params'] ?? null;

			$importer = new Importers\ResearchImporter( $search_url, $params );

			try {
				$importer->import();
				\WP_CLI::success( $importer->get_stats() );
			} catch( Exception $e ) {
				\WP_CLI::error( $e->message );
			}
		}
	}
}
