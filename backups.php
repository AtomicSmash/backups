<?php
/*
Plugin Name: Backups
Plugin URI: http://www.atomicsmash.co.uk
Description: Backup your site to Amazon S3
Version: 0.0.1
Author: Atomic Smash
Author URI: https://www.atomicsmash.co.uk
*/

namespace BACKUPS;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

if (!defined('ABSPATH'))exit; //Exit if accessed directly

if ( defined( 'WP_CLI' ) && WP_CLI && !class_exists( 'Backups_Commands' ) ) {

/**
 * Backup your WordPress site
 *
 * <message>
 * : An awesome message to display
 *
 * --append=<message>
 * : An awesome message to append to the original message.
 *
 * @when before_wp_load
 */
class Backups_Commands extends \WP_CLI_Command {

    // public function __construct() {
    //     if( $this->check_config_details_exist() == true ){
    //     }
	// }

	private function connect_to_s3(){

        $s3 = new S3Client([
            'version'     => 'latest',
            'region'      => BACKUPS_S3_REGION,
            'credentials' => [
                'key'    => BACKUPS_S3_ACCESS_KEY_ID,
                'secret' => BACKUPS_S3_SECRET_ACCESS_KEY,
            ],
        ]);

        return $s3;
    }

	private function check_config_details_exist(){
        if ( !defined('BACKUPS_S3_ACCESS_KEY_ID') || !defined('BACKUPS_S3_SECRET_ACCESS_KEY') || !defined('BACKUPS_S3_REGION') || BACKUPS_S3_ACCESS_KEY_ID == "" || BACKUPS_S3_SECRET_ACCESS_KEY == "" || BACKUPS_S3_REGION == "" ) {

            echo WP_CLI::colorize( "%rS3 access details don't currently exist in your config files 😓!%n\n" );

            // Add config details
            echo WP_CLI::colorize( "%YAdd these new config details to your wp-config file:%n\n");
            echo WP_CLI::colorize( "%Ydefine('BACKUPS_S3_REGION','eu-west-2'); // eu-west-2 is London%n\n");
            echo WP_CLI::colorize( "%Ydefine('BACKUPS_S3_ACCESS_KEY_ID','');%n\n");
            echo WP_CLI::colorize( "%Ydefine('BACKUPS_S3_SECRET_ACCESS_KEY','');%n\n");
            echo WP_CLI::colorize( "%YOnce these are in place, re-run %n");
            echo WP_CLI::colorize( "%r'wp backups create_bucket'%n\n\n");

            echo WP_CLI::colorize( "%YIf you need help, visit https://github.com/AtomicSmash/backups/wiki/Getting-AWS-credentials to learn how to create an IAM user.%n\n");

            return false;
        }else{
            return true;
        }
    }

    /**
     * Setup Backups bucket and create lifecycle policy.
     *
     * ## OPTIONS
     *
     * <bucket_name>
     * : Name of bucket to create
     *
     * ## EXAMPLES
     *
     *     $ wp option wordpress.dev 40
     *     Success: This is will setup a new bucket and add a lifecycle policy of
     *     40 days for the SQL folder.
     */
    public function create_bucket( $args, $assoc_args ){

        // Get bucket name
        $bucket_name = $args[0];

        \WP_CLI::confirm( 'Create bucket?', $assoc_args = array( 'continue' => 'yes' ) );

        // If 'Y' create backup bucket
        if( isset( $assoc_args['continue'] )){
            $s3 = $this->connect_to_s3();

            $creation_success = true;

            // Create standard backup bucket
            try {
                $result = $s3->createBucket([
                    'Bucket' => $bucket_name . ".backups"
                ]);
            } catch (\Aws\S3\Exception\S3Exception $e) {
                echo \WP_CLI::colorize( "%rThere was a problem creating the bucket. It might already exist 🤔%n\n");
                $creation_success = false;
            }

        }

        if( $creation_success == true ){

            update_option( 'backups_s3_selected_bucket', $bucket_name . '.backups', 0 );
            \WP_CLI::success( "Backups bucket created and selected 👌");

        }
    }


    /**
     * Check to see if there are working AWS creds
     */
    public function check_credentials(){

        if( $this->check_config_details_exist() == false ){
			return WP_CLI::error( "Config details missing" );
		}

        $s3 = $this->connect_to_s3();

    	try {
            $result = $s3->listBuckets(array());
        } catch(Aws\S3\Exception\S3Exception $e) {
            echo \WP_CLI::warning( "There was an error connecting to S3 😣\n\nThis was the error:\n" );
            echo $e->getAwsErrorCode()."\n";
            return false;
        };

        return \WP_CLI::success( "Connection to S3 was successfull 😄");

    }

	public function select_bucket( $args ){

		$connected_to_S3 = true;
		$selected_bucket_check = 0;
        //ASTODO This get_option could be a helper
		$selected_s3_bucket = get_option('backups_s3_selected_bucket');


		if( $this->check_config_details_exist() == false ){
			return WP_CLI::error( "Config details missing" );
		}

		// Check to see if there user is trying to set a specific bucket
		if( isset( $args[0] )){
			$selected_s3_bucket = $args[0];
			update_option( 'backups_s3_selected_bucket', $selected_s3_bucket, 0 );
			\WP_CLI::success( "Selected bucket updated" );
		}

		// Test if bucket has not yet been selected
		if( $selected_s3_bucket == "" ){
			echo \WP_CLI::colorize( "%YNo bucket is currently selected.%n\n");
		}

		echo \WP_CLI::colorize( "%YAvailable buckets:%n\n");

        $s3 = $this->connect_to_s3();


        //ASTODO this should be the built in config_check function
		try {
			$result = $s3->listBuckets( array() );
		}

		//catch S3 exception
		catch( Aws\S3\Exception\S3Exception $e ) {
			$connected_to_S3 = false;
			// echo 'Message: ' .$e->getMessage();
		};
        //ASTODO END replace

		if( $connected_to_S3 == true ){

			foreach ($result['Buckets'] as $bucket) {

				echo $bucket['Name'];

				if( $bucket['Name'] == $selected_s3_bucket ){
					$selected_bucket_check = 1;
					echo \WP_CLI::colorize( "%r - currently selected%n");
				};

				echo "\n";
			}

		}else{
			return \WP_CLI::error( "Error connecting to Amazon S3, please check your credentials." );
		}

		if( $selected_bucket_check == 0 && $selected_s3_bucket != "" ){
			return \WP_CLI::error( "There is a selected bucket (". $selected_s3_bucket ."), but it doesn't seem to exits on S3?" );
		}

	}

	/**
     * Copy files to S3. You can choose whether to sync the DB, files or both.
     *
     * ## OPTIONS
     *
     * [--type=<type>]
     * : What would you likw to sync? All,database or media.
     *
     * ## EXAMPLES
     *
	 *     $ wp backups backup
     *     Success: Will sync media and database to S3
     *
	 *     $ wp backups backup --type=all
	 *     Success: Will sync media and database to S3
     *
     *     $ wp backups backup --type=database
	 *     Success: Will sync the database to S3
     *
     *     $ wp backups backup --type=media
	 *     Success: Will sync media to S3
     *
     */
	public function backup( $args, $assoc_args ){

		if( empty( $assoc_args ) ){
			\WP_CLI::line( "You haven't defined what to sync. So using backing up media and database 🤓" );
			$assoc_args['type'] = "all";
		}

		if( $assoc_args['type'] == "all" || $assoc_args['type'] == "database" ){
			$this->backup_database( $args, $assoc_args );
		}

		if( $assoc_args['type'] == "all" || $assoc_args['type'] == "media" ){
			$this->backup_media( $args, $assoc_args );
		}

	}

    /**
     * Sync files to S3. You can also just sync in one direction, this is good for backups
     *
     * ## OPTIONS
     *
     * [--direction=<up-or-down>]
     * : Sync up to S3, or pull down from S3
     *
     * ## EXAMPLES
     *
     *     $ wp sync
     *     Success: Will sync all uploads to S3
     *
     */
	private function backup_media( $args, $assoc_args ) {

		$selected_s3_bucket = get_option('backups_s3_selected_bucket');
		$wp_upload_dir = wp_upload_dir();

		// if( $this->check_config_details_exist() == false ){
		// 	return WP_CLI::error( "Config details missing" );
		// }

		//ASTODO move this to to main backup command
        if( $selected_s3_bucket == "" ){
            echo WP_CLI::colorize( "%YNo bucket is currently selected. Run %n");
            echo WP_CLI::colorize( "%r'wp backups create_bucket'%n");
            echo WP_CLI::colorize( "%Y%n or ");
            echo WP_CLI::colorize( "%r'wp backups select_bucket'%n");
            echo WP_CLI::colorize( "%Y%n\n");
            return false;
        }

		\WP_CLI::line( \WP_CLI::colorize( "%YStarting to sync media files%n" ));

		$missing_files = $this->find_files_to_sync();

	    $direction = 'both';


        //ASTODO This isn't needed!
		$s3 = new S3Client([
			'version'     => 'latest',
			'region'      => BACKUPS_S3_REGION,
			'credentials' => [
				'key'    => BACKUPS_S3_ACCESS_KEY_ID,
				'secret' => BACKUPS_S3_SECRET_ACCESS_KEY,
			],
		]);

		try {

			$keyPrefix = '';
			$options = array(
				// 'params'      => array('ACL' => 'public-read'),
				'concurrency' => 20,
				'debug'       => true
			);

			//ASTODO check count
			//ASTODO Don't sync sql-backup folder
			// Upload missing files
			foreach($missing_files['display'] as $file){

                if( $direction == 'both' || $direction == 'down' ){
    				if( $file['location'] == 'remote'){

    					//Check to see if the missing $file is actually a folder
    					$ext = pathinfo($file['file'], PATHINFO_EXTENSION);

    					//Check to see if the directory exists
    					if (!file_exists(dirname($wp_upload_dir['basedir']."/".$file['file']))) {
    						mkdir(dirname($wp_upload_dir['basedir']."/".$file['file']),0755, true);
    					};

    					if($ext != ""){
    						$result = $s3->getObject([
    						   'Bucket' => $selected_s3_bucket,
    						   'Key'    => $file['file'],
    						   'SaveAs' => $wp_upload_dir['basedir']."/".$file['file']
    						]);
    					}
    					$results['files'][] = $file['file'];

                        \WP_CLI::log( \WP_CLI::colorize( "%gSynced: ".$file['file'] . "%n%y - ⬇ downloaded from S3%n" ));
    				}
                }

                if( $direction == 'both' || $direction == 'up' ){
    				if( $file['location'] == 'local'){
    					$result = $s3->putObject(array(
    						'Bucket' => $selected_s3_bucket,
    						'Key'    => $file['file'],
    						'SourceFile' => $wp_upload_dir['basedir']."/".$file['file']
    					));
    					$results['files'][] = $file['file'];

                        \WP_CLI::line( \WP_CLI::colorize( "%gSynced: ".$file['file']."%n%y - ⬆ uploaded to S3%n" ));
    				}
                }

			}

		} catch (Aws\S3\Exception\S3Exception $e) {
			echo "There was an error uploading the file.<br><br> Exception: $e";
		}

		return \WP_CLI::success( "Media library sync complete! ✅" );

	}

	private function find_files_to_sync(){

		// These need to be reduced
		$selected_s3_bucket = get_option('backups_s3_selected_bucket');

		$ignore = array("DS_Store","htaccess");

		// Instantiate an Amazon S3 client.
        //ASTODO This isn't needed!
		$s3 = new S3Client([
			'version'     => 'latest',
			'region'      => BACKUPS_S3_REGION,
			'credentials' => [
				'key'    => BACKUPS_S3_ACCESS_KEY_ID,
				'secret' => BACKUPS_S3_SECRET_ACCESS_KEY,
			],
		]);


		$iterator = $s3->getIterator('ListObjects', array(
			'Bucket' => $selected_s3_bucket
		));


		$found_files_remotely = array();

		if( count( $iterator ) > 0 ){
			foreach ($iterator as $object) {

				if ( strpos( $object['Key'], 'sql-backup' ) === false ) {
					$found_files_remotely[] = $object['Key'];
				}

			}
		}


		$wp_upload_dir = wp_upload_dir();

		$iter = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($wp_upload_dir['basedir'], \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::SELF_FIRST,
			\RecursiveIteratorIterator::CATCH_GET_CHILD // Ignore "Permission denied"
		);

		// $paths = array($wp_upload_dir['basedir']);
		$found_files_locally = array();

		foreach ($iter as $path => $dir) {
			// if ($dir->isDir()) {

			$filetype = pathinfo($dir);

			//This would be nicer to have this in the RecursiveIteratorIterator
			if (isset($filetype['extension']) && !in_array($filetype['extension'], $ignore)) {
				$found_files_locally[] = str_replace($wp_upload_dir['basedir'].'/','',$path);
			}

		}


		$missing_locally = array_diff( $found_files_remotely, $found_files_locally );


		$missing_display = array();

		if( count( $missing_locally ) > 0 ){
			foreach( $missing_locally as $missing_file ){
				$missing_display[] = array(
					'file' => $missing_file,
					'location' => 'remote'
				);
			}
		}


		$missing_remotely = array_diff( $found_files_locally, $found_files_remotely );

		if( count( $missing_remotely ) > 0 ){
			foreach( $missing_remotely as $missing_file ){
				$missing_display[] = array(
					'file' => $missing_file,
					'location' => 'local'
				);
			}
		}

		// reset array keys
		$missing_locally = array_values($missing_locally);
		$missing_remotely = array_values($missing_remotely);

		$missing_files = array();
		$missing_files['missing_locally'] = $missing_locally;
		$missing_files['missing_remotely'] = $missing_remotely;
		$missing_files['display'] = $missing_display;

		return $missing_files;

	}

    /*
     * Backup a WordPress website
     */
    private function backup_database( $args, $assoc_args){

		\WP_CLI::line( \WP_CLI::colorize( "%YStarted database backup%n" ));

        $wp_upload_dir = wp_upload_dir();

        // Check to see if the backup folder exists
        if (!file_exists( $wp_upload_dir['basedir'] . "/database-backups/" )) {
            mkdir( $wp_upload_dir['basedir'] . "/database-backups/" ,0755 );
            echo \WP_CLI::colorize( "%yThe directory 'wp-content/uploads/database-backups/' was successfully created.%n\n");
        };

        // generate a hash based on the date and a random number
        $hashed_filename = hash( 'ripemd160', date('ymd-h:i:s') . rand( 1, 99999 ) ) . ".sql";

		\WP_CLI::line( " > Backing up database to '/database-backups/" . $hashed_filename . "' 💾" );

        // Create a backup with a file name involving the datestamp and a rand number to make it harder to
        // guess the backup filenames and reduce the risk of being able to download backups
        $output = shell_exec( "wp db export " . $wp_upload_dir['basedir'] . "/database-backups/" . $hashed_filename . " --allow-root --path=".ABSPATH);

        $s3 = $this->connect_to_s3();

        //ASTODO centralise this get option, once it's centalised there will be a way of overriding it via config
        $selected_s3_bucket = get_option('backups_s3_selected_bucket');

		\WP_CLI::line( " > Sending SQL file to S3 📡" );

        //ASTODO check to see if backup actually worked
        if( $selected_s3_bucket != "" ){

            // Transfer the file to S3
            $success = false;

            try {

                $result = $s3->putObject(array(
                    'Bucket' => $selected_s3_bucket,
                    'Key'    => "sql-backups/".date('d-m-Y--h:i:s').".sql",
                    'SourceFile' =>  $wp_upload_dir['basedir'] . "/database-backups/" . $hashed_filename
                ));

                $success = true;

            } catch (Aws\S3\Exception\S3Exception $e) {
    			echo " > There was an error uploading the backup database 😕";
    		}

            // If successfully transfered, delete local copy

        }

        $output = shell_exec( "rm -rf  " . $wp_upload_dir['basedir'] . "/database-backups/" . $hashed_filename );

		\WP_CLI::line( " > Deleting local copy of DB 🗑" );


	    if( $success == true ){
            return \WP_CLI::success( "DB backup complete! ✅" );
        }else{
            return \WP_CLI::error( "There was an issue backing up the database" );
        }


    }

    /**
     * Add life cycle policy to the SQL folder. This will help reduce file build up
     *
     * ## OPTIONS
     *
     * <number_of_days>
     * : Name of bucket to create
     *
     * ## EXAMPLES
     *
     *     $ wp add_lifecycle
     *     Success: Will sync all uploads to S3
     *
     */
    private function add_lifecycle( $args, $assoc_args ){

        $selected_s3_bucket = get_option('backups_s3_selected_bucket');

        if( $selected_s3_bucket == "" ){
            echo WP_CLI::colorize( "%YNo bucket is currently selected. Run %n");
            echo WP_CLI::colorize( "%r'wp backups create_bucket'%n");
            echo WP_CLI::colorize( "%Y%n\n");
            return false;
        }

        // Get expirty time in number of days
        $backup_life = $args[0];

        $s3 = $this->connect_to_s3();

        // Setup lifecycle policy for DB backups
        $result = $s3->putBucketLifecycleConfiguration([
            'Bucket' => $selected_s3_bucket,
            'LifecycleConfiguration' => [
                'Rules' => [[
                    'Expiration' => [
                        // 'Date' => <integer || string || DateTime>,
                        'Days' => $backup_life,
                        // 'ExpiredObjectDeleteMarker' => true || false,
                    ],
                    'ID' => "SQL backups",
                    'Filter' => [
                        'Prefix' => 'sql-backups'
                    ],
                    'Status' => 'Enabled'
                ]]
            ]
        ]);

        echo WP_CLI::success( "Autodelete lifecycle added");

    }

}

/**
 * Add life cycle policy to the SQL folder. This will help reduce file build up
 *
 * ## OPTIONS
 *
 * <number_of_days>
 * : Name of bucket to create
 *
 * ## EXAMPLES
 *
 *     $ wp add_lifecycle
 *     Success: Will sync all uploads to S3
 *
 */
\WP_CLI::add_command( 'backups', '\BACKUPS\Backups_Commands' );

};
