<?php
/*
Plugin Name: Main Site Utilities
Version: 1.0.1
Author: Jim Barnes
Description: This is my plugin description.
*/
if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'UCF_MAIN_SITE_UTILITIES__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
<<<<<<< Updated upstream
	// Importer files
	require_once 'importers/tuition-fees-importer.php';

	// Converter files
	require_once 'converters/resource-converter.php';

	// Command files
	require_once 'includes/degrees.php';
	require_once 'includes/converters.php';

	// Add new commands
	WP_CLI::add_command( 'mainsite degrees', 'Degrees' );
	WP_CLI::add_command( 'mainsite converters', 'Converters' );
=======
	// Pull in the degree importer files.
	require_once UCF_MAIN_SITE_UTILITIES__PLUGIN_DIR . 'importers/research-importer.php';
	require_once UCF_MAIN_SITE_UTILITIES__PLUGIN_DIR . 'includes/commands.php';
>>>>>>> Stashed changes

	WP_CLI::add_command( 'research', 'UCF\MainSiteUtilities\Commands\ResearchCommands' );
}

?>
