<?php
/**
 * Commands for importing researchers and research
 */
namespace UCF\MainSiteUtilities\Commands {
	class ResearchCommands extends \WP_CLI_Command {
		public function import( $args, $assoc_args ) {
			\WP_CLI::success('Success');
		}
	}
}
