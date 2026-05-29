<?php
/**
 * Converts pages from the old article template to the new two-column article template.
 */
namespace UCF\MainSiteUtilities\Importers {

	class ArticlesConverter {
		private
			$post_ids,
			$dry_run,
			$log = array(),

			// Stats
			$found     = 0,
			$converted = 0;

		/**
		 * Constructor.
		 *
		 * @param array $post_ids Optional list of post IDs to restrict conversion to.
		 * @param bool  $dry_run  When true, no data is written.
		 */
		public function __construct( $post_ids = array(), $dry_run = false ) {
			$this->post_ids = $post_ids;
			$this->dry_run  = $dry_run;
		}

		/**
		 * Runs the conversion.
		 */
		public function convert() {
			$query_args = array(
				'post_type'      => 'page',
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'meta_query'     => array(
					array(
						'key'   => '_wp_page_template',
						'value' => 'template-article.php',
					),
				),
			);

			if ( ! empty( $this->post_ids ) ) {
				$query_args['post__in'] = $this->post_ids;
			}

			$pages = get_posts( $query_args );

			if ( empty( $pages ) ) {
				$this->log[] = 'No pages found using template-article.php.';
				return;
			}

			$this->found = count( $pages );
			$this->log[] = sprintf( 'Found %d page(s) to convert.', $this->found );

			foreach ( $pages as $page ) {
				$this->log[] = sprintf( 'Processing: [%d] %s', $page->ID, $page->post_title );
				$this->convert_page( $page );
			}
		}

		/**
		 * Converts a single page.
		 *
		 * @param \WP_Post $page
		 */
		private function convert_page( $page ) {
			$sidebar_html = $this->build_sidebar_html( $page->ID );
			$faqs         = $this->build_faqs( $page->ID );

			if ( $this->dry_run ) {
				$this->log[] = '  [dry-run] Would update _wp_page_template to template-article-two-col.php';

				if ( $sidebar_html ) {
					$this->log[] = sprintf( '  [dry-run] Would set sidebar_content (%d chars)', strlen( $sidebar_html ) );
				} else {
					$this->log[] = '  [dry-run] No sidebar fields to migrate.';
				}

				if ( $faqs ) {
					$this->log[] = sprintf( '  [dry-run] Would convert %d personal_response row(s) to faqs.', count( $faqs ) );
				} else {
					$this->log[] = '  [dry-run] No personal_response data to migrate.';
				}

				$this->converted++;
				return;
			}

			update_post_meta( $page->ID, '_wp_page_template', 'template-article-two-col.php' );

			if ( $sidebar_html ) {
				update_field( 'sidebar_content', $sidebar_html, $page->ID );
			}

			if ( $faqs ) {
				update_field( 'faqs', $faqs, $page->ID );
			}

			$this->log[]  = '  Converted successfully.';
			$this->converted++;
		}

		/**
		 * Builds the sidebar HTML from the old template's repeater fields.
		 *
		 * Mirrors the markup from template-article.php so existing pages render
		 * identically under the new template's sidebar_content WYSIWYG field.
		 *
		 * @param int $post_id
		 * @return string
		 */
		private function build_sidebar_html( $post_id ) {
			$html = '';

			// Authors + References → "Researchers in Focus" card
			$authors    = get_field( 'author', $post_id );
			$references = get_field( 'references', $post_id );

			if ( $authors || $references ) {
				$html .= '<aside class="card bg-faded mb-4"><div class="card-block">';
				$html .= '<h2 class="h4 heading-underline">Researchers in Focus</h2>';

				if ( $authors ) {
					$html .= '<h3 class="h6">Authors</h3>';
					foreach ( $authors as $author ) {
						$html .= '<div class="pl-2">';
						$html .= '<p class="h6">' . esc_html( $author['name'] ) . '</p>';
						$html .= '<p class="font-size-sm">' . wp_kses_post( $author['bio'] ) . '</p>';
						$html .= '</div>';
					}
				}

				if ( $references ) {
					$html .= '<h3 class="h6">References</h3>';
					$html .= '<ol class="references pl-4">';
					foreach ( $references as $reference ) {
						$html .= '<li class="list-item font-size-sm">' . wp_kses_post( $reference['reference'] ) . '</li>';
					}
					$html .= '</ol>';
				}

				$html .= '</div></aside>';
			}

			// Interview → separate cards for interviewees and interviewers
			$interview = get_field( 'interview', $post_id );

			if ( $interview ) {
				$interviewees = array_filter( $interview, function( $person ) {
					return $person['interviewer'] === false;
				} );

				$interviewers = array_filter( $interview, function( $person ) {
					return $person['interviewer'] === true;
				} );

				if ( $interviewees ) {
					$html .= '<aside class="card bg-faded mb-4"><div class="card-block">';
					foreach ( $interviewees as $person ) {
						$html .= '<div class="pl-2">';
						$html .= '<p class="h4">' . esc_html( $person['name'] ) . '</p>';
						$html .= '<p class="font-size-sm">' . wp_kses_post( $person['bio'] ) . '</p>';
						$html .= '</div>';
					}
					$html .= '</div></aside>';
				}

				if ( $interviewers ) {
					$html .= '<aside class="card bg-faded mb-4"><div class="card-block">';
					$html .= '<h2 class="h4 heading-underline">Interviewers</h2>';
					foreach ( $interviewers as $person ) {
						$html .= '<div class="pl-2">';
						$html .= '<p class="h6">' . esc_html( $person['name'] ) . '</p>';
						$html .= '<p class="font-size-sm">' . wp_kses_post( $person['bio'] ) . '</p>';
						$html .= '</div>';
					}
					$html .= '</div></aside>';
				}
			}

			// Co-Authors card
			$coauthors = get_field( 'co-authors', $post_id );

			if ( $coauthors ) {
				$html .= '<aside class="card bg-faded mb-4"><div class="card-block">';
				$html .= '<h2 class="h4 heading-underline">Co-Authors</h2>';
				$html .= '<ul class="list-unstyled">';
				foreach ( $coauthors as $coauthor ) {
					$html .= '<li class="list-item mb-4">' . wp_kses_post( $coauthor['co-author'] ) . '</li>';
				}
				$html .= '</ul>';
				$html .= '</div></aside>';
			}

			// Related Programs card
			$undergrad_programs = get_field( 'related_undergraduate_programs', $post_id );
			$grad_programs      = get_field( 'related_graduate_programs', $post_id );

			if ( $undergrad_programs || $grad_programs ) {
				$html .= '<aside class="card bg-faded mb-4"><div class="card-block">';
				$html .= '<h2 class="h4 heading-underline">Related Programs</h2>';

				if ( $undergrad_programs ) {
					$html .= '<h3 class="h6 text-uppercase">Undergraduate Programs</h3>';
					$html .= '<ul class="list-unstyled pl-4">';
					foreach ( $undergrad_programs as $program ) {
						$html .= '<li class="list-item"><a href="' . esc_url( get_the_permalink( $program->ID ) ) . '">' . esc_html( $program->post_title ) . '</a></li>';
					}
					$html .= '</ul>';
				}

				if ( $grad_programs ) {
					$html .= '<h3 class="h6 text-uppercase">Graduate Programs</h3>';
					$html .= '<ul class="list-unstyled pl-4">';
					foreach ( $grad_programs as $program ) {
						$html .= '<li class="list-item"><a href="' . esc_url( get_the_permalink( $program->ID ) ) . '">' . esc_html( $program->post_title ) . '</a></li>';
					}
					$html .= '</ul>';
				}

				$html .= '</div></aside>';
			}

			return $html;
		}

		/**
		 * Maps the old personal_response repeater to the new faqs repeater format.
		 *
		 * Both repeaters share the same question/answer subfield names, so this is
		 * a direct structural mapping.
		 *
		 * @param int $post_id
		 * @return array|null
		 */
		private function build_faqs( $post_id ) {
			$personal_responses = get_field( 'personal_response', $post_id );

			if ( empty( $personal_responses ) ) {
				return null;
			}

			$faqs = array();
			foreach ( $personal_responses as $response ) {
				$faqs[] = array(
					'question' => $response['question'],
					'answer'   => $response['answer'],
				);
			}

			return $faqs;
		}

		/**
		 * Returns all log messages collected during conversion.
		 *
		 * @return array
		 */
		public function get_log() {
			return $this->log;
		}

		/**
		 * Returns a summary of conversion statistics.
		 *
		 * @return string
		 */
		public function get_stats() {
			$label = $this->dry_run ? 'Would convert' : 'Converted';
			return "
Finished converting article pages!
-----------------------------------
Found    : {$this->found}
{$label}: {$this->converted}
			";
		}
	}
}
