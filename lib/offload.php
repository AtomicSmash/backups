<?php

if (!defined('ABSPATH')) exit; //Exit if accessed directly

// use \Monolog\Logger;
// use \Monolog\Handler\StreamHandler;

class MoveMediaLibraryToS3 {

    function __construct() {

        // Setup CLI commands
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            // if ( defined( '' ) && defined( '' ) ) {
            // }else{
            WP_CLI::add_command( 'move-to-s3 show-files-with-no-s3-meta-data', array( $this, 'show_media_with_no_meta_data' ) );
            WP_CLI::add_command( 'move-to-s3 add-meta-data-to-library', array( $this, 'add_meta_data_to_whole_library' ) );

            // }
        }

    }


    public function add_meta_data_to_whole_library( $args, $assoc_args ){

        // See if this is a dry run
        if( isset( $assoc_args['dry-run'] ) ){
            $dryrun = true;
            WP_CLI::warning( "This is a dry run, nothing will be saved to the database!" );

        }else{
            $dryrun = false;
        }

        // Create the logger
        // $logger = new Logger( 'move_wp_media_library_to_s3' );
        //
        // $uploads_directory = wp_upload_dir();
        // $logger->pushHandler(new StreamHandler( $uploads_directory['basedir'] .'/move_wp_media_library_to_s3.log', Logger::DEBUG));
        // $logger->info( '-------- Order placed --------' );
        // $logger->info( 'Order ID: ' . $order_id );
        // // $logger->info( 'Merge fields: ', $merge_fields );
        // $logger->info( 'Timestamp: '. $date->getTimestamp() );

        $files = $this->get_files_with_no_meta_data();

        // Get offload
        $offload_options = get_option('tantan_wordpress_s3');

        if( empty( $offload_options ) ){
            WP_CLI::error( "Options for 'wp-offload-s3' can't be found. Please make sure it's configured before trying again." );
        }

        if( $offload_options['region'] == "" ){
            WP_CLI::error( "No region selected." );
        }

        if( $offload_options['bucket'] == "" ){
            WP_CLI::error( "No bucket selected." );
        }

        // if( $options != null && $options['bucket'] != '' && $options['object-prefix'] != '' && $options['region'] != '' ){
        if( $files != false ){

            foreach( $files as $post ){

                $media_url = str_replace( get_bloginfo('url').'/', '', $post->guid );

                $data = array(
                    'region' => $offload_options['region'],
                    'bucket' => $offload_options['bucket'],
                    'key' => $media_url,
                );

                WP_CLI::success( "Added meta to attachment with ID: " . $post->ID  );

                if( $dryrun != true ){
                    update_post_meta( $post->ID, 'amazonS3_info', $data );
                }

            }

        }else{

            WP_CLI::success( "No files found! Everything is up to date 🙂" );

        }

    }

    public function show_media_with_no_meta_data( ){

        WP_CLI::line( "Finding files..."  );

        $files = $this->get_files_with_no_meta_data();

        if( $files != false ){

            WP_CLI::line( "A total of " . count($files) ." files were found without any S3 meta data."  );

            WP_CLI\Utils\format_items( 'table', $files, array( 'ID', 'post_title' ) );

        }else{

            WP_CLI::success( "No files found! Everything is up to date 🙂" );

        }


    }

    private function get_files_with_no_meta_data( ){

        $args = array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'meta_query'  => array(
                array(
                    'key' => 'amazonS3_info',
                    // 'compare' => 'EXISTS',
                    'compare' => 'NOT EXISTS',
                    'type' => 'STRING'
               )
            )
        );

        $query = new WP_Query( $args );

        if( $query->post_count > 0 ){
            return $query->posts;
        }else{
            return false;
        }

    }

}

$move_wp_media_library_to_s3 = new MoveMediaLibraryToS3;
