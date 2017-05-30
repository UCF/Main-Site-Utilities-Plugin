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
	require_once 'includes/utilties.php';
	WP_CLI::add_command( 'mainsite', 'Main_Site_Utilties' );
}

?>
