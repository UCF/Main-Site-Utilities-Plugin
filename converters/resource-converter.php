<?php
require_once ABSPATH . 'wp-content/plugins/wordpress-importer/parsers.php';

/**
 * Converter for A-Z Index links to Resource links
 **/
class Resource_Converter {
	private
		$import_file_path,
		$posts_to_import=0,
		$posts_created=0,
		$posts_updated=0;

	/**
	 * Constructor
	 * @author Jim Barnes
	 * @since 1.0.1
	 * @param $import_file_path string | The path to the WordPress export file
	 * @return Resource_Converter
	 **/
	public function __construct( $import_file_path ) {
		$this->import_file_path = $import_file_path;
	}

	public function convert() {
		$parser = new WXR_Parser();
		$xml = $parser->parse( $this->import_file_path );

		$this->posts_to_import = count( $xml['posts'] );

		foreach( $xml['posts'] as $post ) {
			$this->import_post( $post );
		}
	}

	public function get_stats() {
		return
"

Total Processed : {$this->posts_to_import}

Created         : {$this->posts_created}
Updated         : {$this->posts_updated}

";
	}

	private function import_post( $post ) {
		$title = $post['post_title'];
		$url = '';

		$postmeta = array();

		foreach( $post['postmeta'] as $pm ) {
			$postmeta[$pm['key']] = $pm['value'];
		}

		if ( isset( $postmeta['azindexlink_url'] ) ) {
			$url = $postmeta['azindexlink_url'];
		}

		$postdata = array(
			'post_title'  => $title,
			'post_type'   => 'ucf_resource_link',
			'post_status' => 'publish',
			'meta_input'  => array(
				'ucf_resource_link_url' => $url
			)
		);

		// Check for existing
		$existing = get_page_by_title( array(
			$title,
			OBJECT,
			'ucf_resource_link'
		) );

		if ( $existing ) {
			$postdata['ID'] = $existing->ID;
			wp_update_post( $postdata );
			$this->posts_updated++;
		} else {
			wp_insert_post( $postdata );
			$this->posts_created++;
		}
	}
}
