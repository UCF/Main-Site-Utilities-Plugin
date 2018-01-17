<?php
/**
 * Commands for converting v2 Main Site Posts
 **/
class Converters extends WP_CLI_Command {
	/**
	 * Converts Main-Site-Theme azindexlinks to resource_links
	 *
	 * ## OPTIONS
	 *
	 * <file_path>
	 * : The path to the WordPress Export file to import
	 *
	 * ## EXAMPLES
	 *
	 * $ wp mainsite converters azindex
	 *
	 * @when after_wp_load
	 */
	public function azindex( $args ) {
		$import_file_path = $args[0];

		$convert = new Resource_Converter( $import_file_path );

		try {
			$convert->convert();
		}
		catch( Exception $e ) {
			WP_CLI::error( $e->getMessage(), $e->getCode() );
		}

		WP_CLI::success( $convert->get_stats() );
	}
}
