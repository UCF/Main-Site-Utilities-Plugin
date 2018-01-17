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

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	// Importer files
	require_once 'importers/tuition-fees-importer.php';

	// Import override files
	require_once 'includes/degree-import-overrides.php';

	// Converter files
	require_once 'converters/resource-converter.php';

	// Command files
	require_once 'includes/degrees.php';
	require_once 'includes/converters.php';

	// Add new commands
	WP_CLI::add_command( 'mainsite degrees', 'Degrees' );
	WP_CLI::add_command( 'mainsite converters', 'Converters' );

}

?>
