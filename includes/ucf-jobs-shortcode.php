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
	 * @since 3.0.0
	 */
	public static function register_shortcode() {
		add_shortcode( 'ucf-jobs', array( __NAMESPACE__ . '\Jobs_Shortcode', 'sc_ucf_jobs' ) );
	}

	/**
	 * Generates the `ucf-jobs` markup.
	 *
	 * @author Cadie Stockman
	 * @since 3.0.0
	 * @param array $attr The parsed attribute array
	 * @param string $content Content passed into the shortcode
	 * @return string HTML markup for displaying job listings
	 */
	public static function sc_ucf_jobs( $attr, $content='' ) {
		$attr = shortcode_atts( array(
			'limit'      => 10,
			'offset'     => 0,
			'reset_cache'=> 0,
			'ul_classes' => '',
			'li_classes' => '',
			'a_classes'  => ''
		), $attr );

		$args = array(
			'limit'    => $attr['limit'] ? (int) $attr['limit'] : 10,
			'offset'   => $attr['offset'] ? (int) $attr['offset'] : 0,
			'reset_cache' => $attr['reset_cache'] == 1 ? 1 : 0
		);

		$items = Feeds\retrieve_job_listing_data( $args );

		ob_start();

		if ( $items && $items->jobPostings ) {
			echo Jobs_Shortcode::sc_ucf_jobs_display_jobs_list( $items->jobPostings, $attr, $args );
		} else {
			echo 'No job listings to display.';
		}

		return ob_get_clean();
	}

	/**
	 * Returns the HTML markup for the job postings
	 * in an unordered list.
	 *
	 * @since 3.0.0
	 * @param array $job_postings The array of job postings from the Jobs feed
	 * @param array $attr Array of given shortcode attributes
	 * @return string HTML list markup
	 **/
	public static function sc_ucf_jobs_display_jobs_list( $job_postings, $attr, $args ) {
		$ul_classes = $attr['ul_classes'];
		$li_classes = $attr['li_classes'];
		$a_classes  = $attr['a_classes'];
		$limit = $args['limit'];
		$offset = $args['offset'];

		# Filter/Slice the array by adding the offset and limit
		$filtered_jobs = array_slice($job_postings, $offset, $limit);

		ob_start();

		if ( is_array( $filtered_jobs ) ) :
	?>
		<ul <?php echo ( ! empty( $ul_classes ) ) ? 'class="' . $ul_classes . '"' : ''; ?>>

	<?php
		foreach ( $filtered_jobs as $job ) :
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



