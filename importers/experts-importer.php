<?php
/**
 * Imports experts
 */
namespace UCF\MainSiteUtilities\Importers {
    class ExpertImporter {
        private
            $csv_url,
            $force_template = false,
            $force_update = false,
            $expert_term = false,
            
            // Stats
            $processed = 0,
            $creates = 0,
            $updates = 0,
            $errors = 0,
            $expertise_created = 0,
            $tags_created = 0;

        /**
         * Constructs a new instance
         * of the ExpertImporter
         * 
         * @author Jim Barnes
         * @since 3.1.0
         * @param string $csv_url The URL of the CSV file to be imported
         * @param bool $force_template Forces the Person CPT template to be updated
         * @param bool $force_update Forces all records to be updated
         */
        public function __construct( $csv_url, $force_template=false, $force_update=false ) {
            $this->csv_url = $csv_url;
            $this->force_template = $force_template;
            $this->force_update = $force_update;
        }

        /**
         * The primary entry point for starting
         * the import.
         * 
         * @author Jim Barnes
         * @since 3.1.0
         */
        public function import() {
            $this->expert_term = $this->get_expert_term();
            $records = $this->get_csv_data();
            $this->import_records( $records );
        }

        /**
         * Returns the import statistics
         * so they can be written to the
         * screen.
         * 
         * @author Jim Barnes
         * @since 3.1.0
         * @return string
         */
        public function get_stats() {
            return "
Finished importing expert records!
----------------------------------
Processed: $this->processed
Created  : $this->creates
Updated  : $this->updates
Errors   : $this->errors

Expertise Created: $this->expertise_created
Tags Created     : $this->tags_created
            ";
        }


        /**
         * Gets or creates the expert
         * people_group term.
         * 
         * @author Jim Barnes
         * @since 3.1.0
         * @return WP_Term
         */
        private function get_expert_term() {
            $term = get_term_by(
                'name',
                'Expert',
                'people_group'
            );

            if ( ! $term ) {
                $term_id = wp_insert_term(
                    'Expert',
                    'people_group'
                );

                $term = get_term( $term_id );
            }

            return $term;
        }


        /**
         * Retrieves the CSV with the expert
         * records.
         * @author Jim Barnes
         * @since 3.1.0
         * @return array The array of records.
         */
        private function get_csv_data() {
            $response      = \wp_remote_get( $this->csv_url, array( 'timeout' => 10 ) );
            $response_code = \wp_remote_retrieve_response_code( $response );
            $result        = array();
            $fields        = array();

            if ( is_array( $response ) && is_int( $response_code ) && $response_code < 400 ) {
                $data = $this->remove_BOM_utf8(
                    \wp_remote_retrieve_body( $response )
                );

                $rows = explode( "\n", $data );
                
                // Get the fields
                foreach ( str_getcsv( $rows[0] ) as $index => $col_name ) {
                    $fields[$index] = utf8_encode( trim( $col_name ) );
                }

                foreach ( $rows as $row_index => $row ) {
                    // Skip the first row
                    if ( $row_index === 0 ) continue;

                    $row_data = str_getcsv( $row );
                    $record = array();

                    foreach ( $fields as $field_index => $field ) {
                        $record[$field] = $row_data[$field_index];
                    }

                    $result[] = (object) $record;
                }
            }

            return $result;
        }


        /**
         * Imports the records from the CSV into posts.
         * 
         * @author Jim Barnes
         * @since 3.1.0
         * @param array $records The array of records from the CSV
         */
        private function import_records( $records ) {
            foreach( $records as $idx => $record ) {
                $this->processed++;

                try {
                    $created_record = $this->import_record( $record );
                    if ( $created_record ) {
                        $this->creates++;
                    } else {
                        $this->updates++;
                    }
                }
                catch ( \Exception $e ) {
                    \WP_CLI::warning( "There was an error importing a record: {$e->getMessage()}" );
                    $this->errors++;
                }
            }
        }

        /**
         * Imports a single record as a post
         * 
         * @author Jim Barnes
         * @since 3.1.0
         * @param object $record The record to import
         * @return bool True if record created, false if updated.
         */
        private function import_record( $record ) {
            $created = false;
            $name = "$record->first_name $record->last_name";
            $slug = sanitize_title( $name );

            $args = array(
                'title'     => $name,
                'post_type' => 'person'
            );

            $found_posts = get_posts( $args );

            $post_id = null;

            if ( count( $found_posts ) > 0 ) {
                $post_id = $found_posts[0]->ID;
            } else {
                $args['post_title'] = $name;
                $args['post_name'] = $slug;
                $args['status'] = 'draft';

                $post_id = wp_insert_post( $args );
                $created = true;
            }

            if ( $this->force_template ) {
                update_post_meta( $post_id, '_wp_page_template', 'template-expert.php' );
            }

            if ( $this->expert_term ) {
                wp_set_object_terms( $post_id, $this->expert_term->term_id, 'people_group' );
            }

            $expertise = $this->get_or_create_expertise( $record->expertise );
            wp_set_object_terms( $post_id, $expertise, 'expertise' );

            $tags = $this->format_tags( $record->tags );
            wp_set_object_terms( $post_id, $tags, 'post_tag' );

            // Post meta time
            if ( ! empty( $record->first_name ) ) {
                update_field( 'expert_first_name', $record->first_name, $post_id );
            }

            if ( ! empty( $record->last_name ) ) {
                update_field( 'expert_last_name', $record->last_name, $post_id );
            }

            if ( ! empty( $record->title ) ) {
                update_field( 'expert_title', $record->title, $post_id );
            }

            if ( ! empty( $record->cluster ) ) {
                update_field( 'expert_institute', $record->cluster, $post_id );
            }

            if ( ! empty( $record->languages ) ) {
                $languages = array_map( function( $l ) {
                    return trim( $l );
                }, explode( ',', $record->languages ) );

                $value = array();

                foreach( $languages as $lang ) {
                    if ( ! in_array( $lang, array( 'Eng', 'No' ) ) ) {
                        $value[] = array(
                            'language' => $lang
                        );
                    }
                }

                if ( count( $value ) > 0 ) {
                    update_field( 'expert_bilingual', true, $post_id );
                    update_field( 'expert_languages', $value, $post_id );
                }
            }

            if ( ! empty( $record->association ) ) {
                update_field( 'expert_association_fellow', $record->association, $post_id );
            }

            if ( ! empty( $record->linkedin ) ) {
                update_field( 'expert_linkedin_url', $record->linkedin, $post_id );
            }

            if ( ! empty( $record->facebook ) ) {
                update_field( 'expert_facebook_url', $record->facebook, $post_id );
            }

            if ( ! empty( $record->twitter ) ) {
                update_field( 'expert_twitter_url', $record->twitter, $post_id );
            }

            if ( ! empty( $record->instagram ) ) {
                update_field( 'expert_instagram_url', $record->instagram, $post_id );
            }

            if ( ! empty( $record->other ) ) {
                update_field( 'expert_other_url', $record->other, $post_id );
            }

            if ( $created ) {
                wp_publish_post( $post_id );
            }
                
            return $created;
        }

        /**
         * Gets or creates the expertise
         * 
         * @author Jim Barnes
         * @since 3.1.0
         * @param string $expertise The expertise to get
         * @return array An array of expertise objects
         */
        private function get_or_create_expertise( $expertise ) {
            $exps = explode( ';', $expertise );
            $terms = array();
            $retval = array();

            foreach( $exps as $exp ) {
                $terms[] = trim( $exp );
            }

            foreach( $terms as $term ) {
                $term_obj = get_term_by(
                    'name',
                    $term,
                    'expertise'
                );
    
                if ( ! $term_obj ) {
                    $data = wp_insert_term(
                        $term,
                        'expertise'
                    );

                    if ( is_wp_error( $data ) ) {
                        \WP_CLI::warning( $data );
                        continue;
                    }
    
                    $term_obj = get_term( $data['term_id'] );

                    $this->expertise_created++;
                }

                $retval[] = $term_obj->term_id;
            }

            return $retval;
        }

        /**
         * Parses and formats the tags for
         * the expert.
         * 
         * @author Jim Barnes
         * @since 3.1.0
         * @param string $tags The comma delimited string of tags
         * @return array
         */
        private function format_tags( $tags ) {
            $tag_arr = explode( ',', $tags );
            $retval = array();

            foreach( $tag_arr as $tag ) {
                $retval[] = strtolower( trim( $tag ) );
            }

            return $retval;
        }

        /**
         * Handles removing the byte order markers
         * at the beginning of the csv file if they
         * are present.
         * 
         * @author Jim Barnes
         * @since 3.1.0
         * @param string $content The CSV string
         * @return string
         */
        private function remove_BOM_utf8( $content ){
            if ( substr( $content, 0 , 3) === chr( hexdec( 'EF' ) ).chr( hexdec( 'BB' ) ).chr( hexdec( 'BF' ) ) ) {
                return substr( $content, 3 );
            } else {
                return $content;
            }
        }
    }
}