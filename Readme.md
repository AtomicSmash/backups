This plugin is for backing up a WordPress website to Amazon S3.

### Plugin features

- Database backup.
    - Saves a local database dump with an obscure file name.
    - Syncs local database with S3.
    - Removes local copy to reduce wasted HDD space.
- Media library backup.
	- Two way sync of uploads folder and Amazon S3.
	- This can be used for backing up live websites but also for syncing local development assets between developers.
- Offload S3 integration.
	- After media has been moving transferred, you can save the local meta data required for offload S3 to starting loading assets from S3.


## Installation

#### 1. Add the Wordpress plugin to your composer file by navigating to your project and running this inside a terminal:

```
composer require atomicsmash/backups "*"
```

Commit the changes to your local composer file.

#### 2. Add your S3 credentials to your wp-config file.

And these constants to your wp-config file:

```
define('BACKUPS_S3_REGION','eu-west-2'); //London
define('BACKUPS_S3_ACCESS_KEY_ID','');
define('BACKUPS_S3_SECRET_ACCESS_KEY','');
```

Commit the changes to your config.

#### 3. Activate 'Backups'

Now activate the plugin on whichever site you would like backup. This can be done via the admin interface, or by running:

```
wp plugin activate backups
```

If you want to backup a live website, chances are you will need to SSH into the server.

#### 4. Test S3 connection:

To test the connection to S3 run:

```
wp backups check_credentials
```

If there is an issue, please check your credentials are being loaded and are correct.

#### 5. If the check is successful, it's time to create a fresh bucket

```
wp backups create_bucket <bucket_name>
```

`bucket_name` is usually the address of the site you are currently working on ('website.local'). For example:

```
wp backups create_bucket mywebsite.co.uk
```

Running the above command will ask the question: `Create bucket? [y/n]` - supply 'y' to continue.

This will then create a bucket called "mywebsite.co.uk.backup" and select it ready for use.


#### 6. Perform a backup

To start the first backup manually,

```
wp backups backup
```

#### 7. Setup auto-deletion of SQL files

Depending on how often you backup your website, the SQL files will start to build up quickly. You can setup an S3 folder lifecycle to auto-delete files older than X number of days.

```
wp backups setup_autodelete_sql <number_of_days>
```

We usually usually retain backups for 30 days, so we would run:

```
wp backups setup_autodelete_sql 30
```

#### 8. Setting up auto-backup (cron job)

To get the backup command to run on a regular basis, you need to setup a cron job. Use something similar to this:

```
/usr/local/bin/wp backups backup --path=/path/to/www.website.co.uk/
```

If you are using composer in your project then your WordPress core files might be inside a subfolder, please modify the path to reflect this. The cron job would look like this:

```
/usr/local/bin/wp backups backup --path=/path/to/www.website.co.uk/wp
```

If you are using forge, then simply add to the server scheduling panel:

![forge-schedule](https://user-images.githubusercontent.com/1636310/46582964-1cd4d880-ca47-11e8-90f1-c80e0ba625d6.png)

Also, if you are using Capistrano, don't forget to add `current` to the

```
/usr/local/bin/wp backups backup --path=/path/to/www.website.co.uk/current/wp
```

## Functions

**wp backups backup [--direction=<up-or-down>]**
> This function runs `sync` **and** a DB backup.



### How 'Backups' talks to S3

The setup will ask you to add these constants to your wp-config.php file:

- BACKUPS_S3_REGION
- BACKUPS_S3_ACCESS_KEY_ID
- BACKUPS_S3_SECRET_ACCESS_KEY

You can obtain these details by creating an IAM user. Here is [our guide](https://github.com/AtomicSmash/backups/wiki/Getting-AWS-credentials) on how to setup an IAM Amazon user and get the access and secret key that you need.


## Troubleshooting

Are you receiving an error similar to `PHP Fatal error:  Uncaught Error: Class 'Aws\S3\S3Client' not found in /path/to/file`

Make sure you are requiring your autoload.php generated by composer. We usually add this to the top of our wp-config file:

```
require( dirname( __FILE__ ) . '/vendor/autoload.php' );
```

# Upcoming featured

- Backup stats
- Added 'restore' functionality
- Add countdown to upload and download lines

# Changelog

= 0.0.4 =
* Improved UX when creating a bucket
* FIXED issue with syncing DB backups back to local machine from S3
* Added .cap task

= 0.0.3 =
* Added offload S3 functionality
* Added setup guide
* Added lifecycle addition for SQL folder

= 0.0.2 =
* Simplified media transferring

= 0.0.1 =
* Project renamed from log-flume to 'Backups'
* Added database backup functionality
