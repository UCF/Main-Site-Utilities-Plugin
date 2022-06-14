<?php
/**
 * Handles the registration and display of the UCF Jobs Shortcode
 **/

namespace UCF\MainSiteUtilities\Shortcodes;

use UCF\MainSiteUtilities\Feeds;

class Jobs_Shortcode {
	/**
	 * Registers the `ucf-jobs` shortcode.
	 *
	 * @author Cadie Stockman
	 * @since 2.1.0
	 */
	public static function register_shortcode() {
		add_shortcode( 'ucf-jobs', array( __NAMESPACE__ . '\Jobs_Shortcode', 'sc_ucf_jobs' ) );
	}

	/**
	 * Generates the `ucf-jobs` markup.
	 *
	 * @author Cadie Stockman
	 * @since 2.1.0
	 * @param array $attr The parsed attribute array
	 * @param string $content Content passed into the shortcode
	 * @return string
	 */
	public static function sc_ucf_jobs( $attr, $content='' ) {
		$attr = shortcode_atts( array(
			'limit'  => 10,
			'offset' => 0,
			'ul_classes' => '',
			'li_classes' => '',
			'a_classes' => ''
		), $attr );

		$args = array(
			'limit'    => $attr['limit'] ? (int) $attr['limit'] : 10,
			'offset'   => $attr['offset'] ? (int) $attr['offset'] : 0
		);

		$items = Feeds\retrieve_job_listing_data( $args );

		ob_start();

		if ( $items !== null && $items->jobPostings ) {
			echo Jobs_Shortcode::sc_ucf_jobs_display_jobs_list( $items->jobPostings, $attr['ul_classes'], $attr['li_classes'], $attr['a_classes'] );
		} else {
			echo 'No jobs listing data to display.';
		}

		return ob_get_clean();
	}

	/**
	 * Returns the HTML markup for the job postings
	 * in an unordered list.
	 *
	 * @since 2.1.0
	 * @param array $job_postings The array of job postings from the Jobs feed
	 * @param string $ul_classes String of classes to be placed on the HTML <ul> tag
	 * @param string $li_classes String of classes to be placed on the HTML <li> tags
	 * @param string $a_classes String of classes to be placed on the HTML <a> tags
	 * @return string HTML list markup
	 **/
	public static function sc_ucf_jobs_display_jobs_list( $job_postings, $ul_classes, $li_classes, $a_classes ) {
		// var_dump( $job_postings );

		ob_start();

		if ( is_array( $job_postings ) ) :
	?>
		<ul <?php echo ( ! empty( $ul_classes ) ) ? 'class="' . $ul_classes . '"' : ''; ?>>

	<?php
		foreach ( $job_postings as $job ) :
			$title    = ! empty( $job->title ) ? $job->title : '';
			$base_url = rtrim( get_option( UCF_MAIN_SITE_UTILITIES__CUSTOMIZER_PREFIX . 'jobs_base_url' ), "/" );
			$url      = ! empty( $job->externalPath ) ? $base_url . $job->externalPath : '';

			if ( $title && $url ) :
	?>
			<li <?php echo ( ! empty( $li_classes ) ) ? 'class="' . $li_classes . '"' : ''; ?>>
				<a <?php echo ( ! empty( $a_classes ) ) ? 'class="' . $a_classes . '" ' : ''; ?>href="<?php echo $url; ?>"><?php echo $title; ?></a>
			</li>

	<?php
			endif;

		endforeach;
	?>
		</ul>

	<?php
		endif;

		return ob_get_clean();
	}
}



