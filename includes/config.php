<?php
/**
 * Custom configuration settings
 */

namespace UCF\MainSiteUtilities\Includes\Config;


define( 'UCF_MAIN_SITE_UTILITIES__CUSTOMIZER_PREFIX', defined( 'THEME_CUSTOMIZER_PREFIX' ) ? THEME_CUSTOMIZER_PREFIX : 'ucf_main_site_' );

define( 'UCF_MAIN_SITE_UTILITIES__CUSTOMIZER_DEFAULTS', serialize( array(
	'jobs_base_url' => 'https://jobs.ucf.edu/jobs/search/',
	'jobs_feed_url' => 'http://search.cm.ucf.edu/api/v1/positions/'
) ) );


/**
 * Returns a plugin option's default value.
 *
 * @since 3.0.0
 * @param string $option_name The name of the option
 * @return mixed Option default value, or false if a default is not set
 */
function get_option_default( $option_name ) {
	$defaults = unserialize( UCF_MAIN_SITE_UTILITIES__CUSTOMIZER_DEFAULTS );
	if ( $defaults && isset( $defaults[$option_name] ) ) {
		return $defaults[$option_name];
	}
	return false;
}


/**
 * Initialization functions to be fired early when WordPress loads the plugin.
 *
 * @since 3.0.0
 * @return void
 */
function init() {
	// Enforce default option values when `get_option()` is called.
	$options = unserialize( UCF_MAIN_SITE_UTILITIES__CUSTOMIZER_DEFAULTS );

	foreach ( $options as $option_name => $option_default ) {
		// Apply our plugin prefix to the option name:
		$option_name = UCF_MAIN_SITE_UTILITIES__CUSTOMIZER_PREFIX . $option_name;

		// Enforce a default value for options we've defined defaults for:
		add_filter( "default_option_$option_name", function( $get_option_default, $option, $passed_default ) use ( $option_default ) {
			// If get_option() was passed a unique default value, prioritize it
			if ( $passed_default ) {
				return $get_option_default;
			}
			return $option_default;
		}, 10, 3 );

		// Enforce typecasting of returned option values,
		// based on the types of the defaults we've defined.
		// NOTE: Forces option defaults to return when empty
		// option values are retrieved.
		add_filter( "option_$option_name", function( $value, $option ) use ( $option_default ) {
			switch ( $type = gettype( $option_default ) ) {
				case 'integer':
					// Assume 0 should be "empty" here:
					$value = intval( $value );
					break;
				case 'string':
				default:
					break;
			}

			if ( empty( $value ) ) {
				$value = $option_default;
			}

			return $value;
		}, 10, 2 );
	}
}

add_action( 'init', __NAMESPACE__ . '\init' );


/**
 * Defines sections used in the WordPress Customizer.
 *
 * @since 3.0.0
 * @author Cadie Stockman
 */
function define_customizer_sections( $wp_customize ) {
	$wp_customize->add_section(
		UCF_MAIN_SITE_UTILITIES__CUSTOMIZER_PREFIX . 'jobs',
		array(
			'title' => 'Jobs'
		)
	);
}

add_action( 'customize_register', __NAMESPACE__ . '\define_customizer_sections' );


/**
 * Defines settings and controls used in the WordPress Customizer.
 *
 * @since 3.0.0
 * @author Cadie Stockman
 */
function define_customizer_fields( $wp_customize ) {
	$wp_customize->add_setting(
		UCF_MAIN_SITE_UTILITIES__CUSTOMIZER_PREFIX . 'jobs_feed_url',
		array(
			'default' => get_option_default( 'jobs_feed_url' ),
			'type'    => 'option'
		)
	);

	$wp_customize->add_control(
		UCF_MAIN_SITE_UTILITIES__CUSTOMIZER_PREFIX . 'jobs_feed_url',
		array(
			'type'        => 'url',
			'label'       => 'Job Listing Feed URL',
			'description' => 'URL to the JSON feed for UCF job listings.',
			'section'     => UCF_MAIN_SITE_UTILITIES__CUSTOMIZER_PREFIX . 'jobs'
		)
	);

	$wp_customize->add_setting(
		UCF_MAIN_SITE_UTILITIES__CUSTOMIZER_PREFIX . 'jobs_base_url',
		array(
			'default' => get_option_default( 'jobs_base_url' ),
			'type'    => 'option'
		)
	);

	$wp_customize->add_control(
		UCF_MAIN_SITE_UTILITIES__CUSTOMIZER_PREFIX . 'jobs_base_url',
		array(
			'type'        => 'url',
			'label'       => 'Job Site Base URL',
			'description' => 'URL for UCF\'s job site.',
			'section'     => UCF_MAIN_SITE_UTILITIES__CUSTOMIZER_PREFIX . 'jobs'
		)
	);
}

add_action( 'customize_register', __NAMESPACE__ . '\define_customizer_fields' );
