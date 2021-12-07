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
	// Pull in the degree importer files.
	require_once UCF_MAIN_SITE_UTILITIES__PLUGIN_DIR . 'importers/research-importer.php';
	require_once UCF_MAIN_SITE_UTILITIES__PLUGIN_DIR . 'importers/research-media-importer.php';
	require_once UCF_MAIN_SITE_UTILITIES__PLUGIN_DIR . 'includes/commands.php';

	WP_CLI::add_command( 'research', 'UCF\MainSiteUtilities\Commands\ResearchCommands' );
}
