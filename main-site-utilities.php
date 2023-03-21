<?php
/*
Plugin Name: Main Site Utilities
Description: Utility plugin for UCF's main site.
Version: 3.0.2
Author: UCF Web Communications
*/

namespace UCF\MainSiteUtilities;

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'UCF_MAIN_SITE_UTILITIES__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once UCF_MAIN_SITE_UTILITIES__PLUGIN_DIR . 'includes/config.php';
require_once UCF_MAIN_SITE_UTILITIES__PLUGIN_DIR . 'includes/ucf-jobs-feed.php';
require_once UCF_MAIN_SITE_UTILITIES__PLUGIN_DIR . 'includes/ucf-jobs-shortcode.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	// Pull in the degree importer files.
	require_once UCF_MAIN_SITE_UTILITIES__PLUGIN_DIR . 'importers/research-importer.php';
	require_once UCF_MAIN_SITE_UTILITIES__PLUGIN_DIR . 'importers/research-media-importer.php';
	require_once UCF_MAIN_SITE_UTILITIES__PLUGIN_DIR . 'importers/research-media-csv-importer.php';
	require_once UCF_MAIN_SITE_UTILITIES__PLUGIN_DIR . 'includes/commands.php';

	\WP_CLI::add_command( 'research', 'UCF\MainSiteUtilities\Commands\ResearchCommands' );
}

add_action( 'init', array( __NAMESPACE__ . '\Shortcodes\Jobs_Shortcode', 'register_shortcode' ) );
