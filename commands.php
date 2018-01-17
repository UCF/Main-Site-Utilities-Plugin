<?php
/*
Plugin Name: Main Site Utilities
Version: 1.0.0
Author: Jim Barnes
Description: This is my plugin description.
*/
if ( ! defined( 'WPINC' ) ) {
    die;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	// Pull in the degree importer files.
	require_once 'importers/tuition-fees-importer.php';

	require_once 'includes/degrees.php';
	require_once 'includes/degree-import-overrides.php';

	WP_CLI::add_command( 'mainsite degrees', 'Degrees' );

}

?>
