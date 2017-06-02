<?php
/**
 * Defines the Degree_Importer_Exception class
 **/
if ( ! class_exists( 'Search_Service_Exception' ) ) {
	class Degree_Importer_Exception extends Exception {
		/**
		 * Creates a new instance of Degree_Importer_Exception
		 * @author Jim Barnes
		 * @since 1.0.0
		 * @param $message string | The exception message
		 * @param $code int | Default to 0
		 * @param $previous Exception | The previous exception
		 **/
		public function __construct( $message, $code=0, Exception $previous=null ) {
			parent::__construct( $message, $code, $previous );
		}

		public function __toString() {
			return __CLASS__ . " : [{$this->code}]: {$this->message}\n";
		}
	}
}
