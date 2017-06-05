<?php
/**
 * Handles importing tuition and fee information
 **/
class Tuition_Fees_Importer {
	private
		$api, // The url to get tuition and fee data
		$data, // The tuition and fee data
		$degrees, // Degrees currently in the main site
		$mapped_total = 0,
		$updated_total = 0,
		$skipped_total = 0,
		$degree_count = 0,
		$mapping = array(
			"Doctor of Physical Therapy"                         => "DPT",
			"Doctor of Medicine"                                 => "MD",
			"Florida Interactive Entertainment Academy"          => "FIEA",
			"Executive MBA"                                      => "EMBA",
			"Professional MBA"                                   => "PMBA",
			"Professional MS in Management/Human Resource Track" => "PMSM",
			"Professional Master of Science in Real Estate"      => "PMRE",
			"MS in Health Sci/Online Exec Health Svcs Admin Trk" => "EHSA",
			"Master of Research Administration"                  => "MRA",
			"Master of Nonprofit Management/Non-Res Cohort Trk"  => "MNM",
			"Graduate Cert in Research Administration"           => "GCRA",
			"MS in Healthcare Informatics"                       => "MHI",
			"Graduate Cert in Health Information Administration" => "GCIA",
			"Online Master of Social Work"                       => "OMSW",
			"MS in Industrial Engr/Healthcare Systems Engr Trk"  => "MHSE",
			"Professional MS in Engineering Management"          => "MSEM",
			"Professional MS in Management/Business Analytics"   => "MSAN",
			"Master of Science in Data Analytics"                => "MSDA"
		); // Mapping of codes to names


	/**
	 * Constructor
	 * @author Jim Barnes
	 * @since 1.0.0
	 * @param $api string | The url of the tuition and fees feed
	 * @return Tuition_Feed_Importer
	 **/
	public function __construct( $api ) {
		$this->api = $api;
		$this->data = array();
		$this->degrees = array();
	}

	/**
	 * Imports tuition and fee data into degrees.
	 * @author Jim Barnes
	 * @since 1.0.0
	 **/
	public function import() {
		// Creates each program in the data array
		$this->set_fee_schedules();
		$this->set_fee_data();
		$this->get_degrees();
		$this->update_degrees();
	}

	/**
	 * Returns the current success stats
	 * @author Jim Barnes
	 * @since 1.0.0
	 * @return string
	 **/
	public function get_stats() {
		$success_percentage = round( $this->updated_total / $this->degree_count * 100 );

		return
"

Successfully update tuition data.

Updated    : {$this->updated_total}
Exceptions : {$this->mapped_total}
Skipped    : {$this->skipped_total}

Success %  : {$success_percentage}%

";
	}

	/**
	 * Get Fee Schedule
	 * @author Jim Barnes
	 * @since 1.0.0
	 **/
	private function set_fee_schedules() {
		$query = array(
			'schoolYear' => 'current',
			'feeName'    => 'Tuition'
		);

		$url = $this->api . '?' . http_build_query( $query );

		$args = array(
			'timeout' => 15
		);

		$response = wp_remote_get( $url, $args );

		$schedules = array();

		if ( is_array( $response ) ) {
			$response_body = wp_remote_retrieve_body( $response );

			$schedules = json_decode( $response_body );

			if ( ! $schedules ) {
				throw new Exception(
					'Unable to retrieve fee schedules',
					2
				);
			}
		} else {
			throw new Exception(
				'Failed to connect to the tuition and fees feed.',
				1
			);
		}

		if ( count( $schedules ) === 0 ) {
			throw new Exception(
				'No results found from the tuition and fees feed.',
				3
			);
		}

		foreach( $schedules as $schedule ) {
			if ( ! isset( $this->data[$schedule->Program] ) ) {
				$this->data[$schedule->Program] = array(
					'code'   => $schedule->Program,
					'type'   => $schedule->FeeType,
					'res'    => '',
					'nonres' => ''
				);
			}
		}
	}

	/**
	 * Sets the tuition data variable
	 * @author Jim Barnes
	 * @since 1.0.0
	 **/
	private function set_fee_data() {
		foreach( $this->data as $schedule ) {
			$code = $schedule['code'];
			$type = $schedule['type'];

			$query = array(
				'schoolYear' => 'current',
				'program'    => $code,
				'feeType'    => $type
			);

			$url = $this->api . '?' . http_build_query( $query );

			$args = array(
				'timeout' => 15
			);

			$response = wp_remote_get( $url, $args );

			if ( is_array( $response ) ) {
				$response_body = wp_remote_retrieve_body( $response );

				$fees = json_decode( $response_body );

				if ( ! $fees ) {
					throw new Exception(
						'Unabled to retrieve fee schedules',
						2
					);
				}
			} else {
				throw new Exception(
					'Failed to connect to the tuition and fees feed.',
					1
				);
			}

			if ( count( $fees ) === 0 ) {
				continue;
			}

			$resident_total = 0;
			$non_resident_total = 0;

			foreach( $fees as $fee ) {
				// Make sure this isn't an "Other" fee
				if ( stripos( $fee->FeeName, '(Per Hour)' ) === false &&
					 stripos( $fee->FeeName, '(Per Term)' ) === false &&
					 stripos( $fee->FeeName, '(Annual)' ) === false )
				{
					$resident_total += $fee->MaxResidentFee;
					$non_resident_total += $fee->MaxNonResidentFee;
				}
			}

			$per_unit = '';

			switch( $type ) {
				case 'SCH':
					$per_unit = ' per credit hour';
					break;
				case 'TRM':
					$per_unit = ' per term';
					break;
				case 'ANN':
					$per_unit = ' per year';
					break;
			}

			$this->data[$code]['res'] = '$' . number_format( $resident_total, 2 ) . $per_unit;
			$this->data[$code]['nonres'] = '$' . number_format( $non_resident_total, 2 ) . $per_unit;
		}
	}

	/**
	 * Retrieves all published degrees
	 * @author Jim Barnes
	 * @since 1.0.0
	 **/
	private function get_degrees() {
		$args = array(
			'post_type'      => 'degree',
			'posts_per_page' => -1
		);

		$this->degrees = get_posts( $args );

		$this->degree_count = count( $this->degrees );
	}

	/**
	 * Loops through degrees and updates them
	 * with tuition data
	 * @author Jim Barnes
	 * @since 1.0.0
	 **/
	private function update_degrees() {
		foreach( $this->degrees as $degree ) {
			$program_types = wp_get_post_terms( $degree->ID, 'program_types' );
			$program_type = is_array( $program_types ) ? $program_types[0] : false;

			// If no program type, skip it
			if ( ! $program_type ) { $this->skipped_total; continue; }

			$schedule_code = $this->get_schedule_code( $program_type->name, $degree->post_title );

			// If we can't determine the program code, skip it
			if ( ! $schedule_code ) { $this->skipped_total++; continue; }

			$resident_total = $this->data[$schedule_code]['res'];
			$non_resident_total = $this->data[$schedule_code]['nonres'];

			if ( ! add_post_meta( $degree->ID, 'degree_resident_tuition', $resident_total, true ) ) {
				update_post_meta( $degree->ID, 'degree_resident_tuition', $resident_total );
			}

			if ( ! add_post_meta( $degree->ID, 'degree_nonresident_tuition', $non_resident_total, true ) ) {
				update_post_meta( $degree->ID, 'degree_nonresident_tuition', $non_resident_total );
			}

			$this->updated_total++;
		}
	}

	private function get_schedule_code( $program_type, $name ) {
		if ( in_array( $program_type, array( 'Undergraduate Degree', 'Minor' ) ) ) {
			return 'UnderGrad';
		}

		// Loop through the mapping variable and look for a match
		// This should handle exceptions for masters degrees
		foreach( $this->mapping as $key => $val ) {
			if ( stripos( $name, $key ) !== false ||
				 stripos( $name, $val ) ) {
				$this->mapped_count++;
				return $val;
			}
		}

		// Unless we have a mapping for it, skip these program types
		if ( in_array( $program_type, array( 'Accelerated Program', 'Articulated Program', 'Certificate' ) ) ) {
			return null;
		}

		// Everything else is a graduate degree
		return 'Grad';
	}
}
