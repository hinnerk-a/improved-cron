<?php
/*
Plugin Name: Improved Cron
Plugin URI: http://cpkwebsolutions.com
Description: WP Cron requires a user to visit the site to trigger your job.  With Improved Cron, your WP Cron jobs will run when you want them to.
Version: 1.2
Author: CPK Web Solutions
Author URI: http://cpkwebsolutions.com/
*/

// Load classes/functions

require_once( 'imcron_bgp.php' );
require_once( 'view/view.php' );

if ( !class_exists( 'imcron_controller' ) ) {

	class imcron_controller {

		private $view;
		private $bgp;
		private $settings;
		private $logging = FALSE;

		public function __construct( ) {
			$this->bgp = new imcron_bgp( $this->logging );
			$this->view = new imcron_view( );

			$settings = array( // Defaults
				'site_address' => home_url() . '/wp-cron.php',
				'interval' => 60,
				'bgp_folder' => dirname( __FILE__ ) . '/bgp',
				'logfile' => 'bgp.log'
			);

			$this->save_settings( $settings );
		}

		public function save_settings( $settings ) {
			$this->settings = $settings;
			$cfg_file_name = dirname( __FILE__ ) . '/bgp.cfg';
			$new = array( $settings['interval'], $settings['site_address'], $settings['bgp_folder'], $settings	['logfile'] );
			$new = implode( ',', $new );
			if ( $handle = fopen( $cfg_file_name, 'w' ) ) {
				fwrite( $handle, $new );
				fclose( $handle );
			}
		}

		public function bgp_keep_alive( ) {
			if ( $this->logging ) error_log( 'Starting BGP Keep Alive Routine' );
			extract( $this->settings );
			$run = false;
			$lock_exists = false;
			if ( $handle = opendir( $bgp_folder ) ) {
				while ( ($file = readdir( $handle ) ) !== false ) {
					if ( substr( $file, -4, 4 ) == '.lck' ) {

						if ( $this->logging ) error_log( "Found $file" );
						$lock_exists = true;
						$t = file_get_contents( $file );
						$time_check = time( ) - $interval - 600;
						if ( $this->logging ) error_log( "$time_check > $t" );
						if ( $time_check > $t ) { // BGP might have died, so restart

							unlink( $bgp_folder . '/' . $file ); // Currently running process will end next time it wakes up
							$run = true;
						}
						break; // Doesn't matter if there's another lockfile, no new processes should start
					}
				}
				closedir( $handle );
			}

			if ( $run || !$lock_exists ) {
				if ( $this->logging ) error_log( 'Starting BGP' );
				$this->bgp->run( );
			}
		}

		public function start_bgp( ) {
			if ( $this->logging ) error_log( 'Scheduling BGP Start' );
			$interval = apply_filters( 'imcron_interval_id', 'every_minute' );
			wp_schedule_event( time( ) -1, $interval, 'imcron_bgp' );
		}

		public function stop_bgp( ) {
			$bgp_folder = dirname( __FILE__ ) . '/bgp';
			if ( $handle = opendir( $bgp_folder ) ) {
				while ( ($file = readdir( $handle ) ) !== false ) {
					if ( substr( $file, -4, 4 ) == '.lck' ) {
						unlink( $bgp_folder . '/' . $file );
					}
				}
				closedir( $handle );
			}
			$timestamp = wp_next_scheduled( 'imcron_bgp' );
			wp_unschedule_event( $timestamp, 'imcron_bgp' );
			wp_clear_scheduled_hook( 'imcron_bgp' );
		}

		public function destructor( ) {
			$this->stop_bgp( );
		}

		public function add_cron_schedules( $schedules ) {
			$schedules['every_minute'] = array( 'interval' => '60', 'display' => __('Every Minute') );
			return $schedules;
		}

		public function activation( ) {

		}

		public function deactivation( ) {
			$this->destructor( );
		}

		public function add_plugin_link( $links, $file ) {
			if( $file == 'improved-cron/imcron.php' ) {
				$settings_link = '<a href="tools.php?page=pws_imcron_manage">' . __('Manage') . '</a>';
				$links = array_merge( $links, array( $settings_link ) ); // after other links
			}
			return $links;
		}

		public function add_menus( ) {
			global $imcron_controller;
			if ( function_exists( 'add_submenu_page' ) ) {
				add_submenu_page( 'tools.php', 'Improved Cron', 'Improved Cron', 'administrator', 'pws_imcron_manage', array( $this, 'manage' ) );
			}
		}

		public function manage( ) {

			$imcron_nonce = wp_create_nonce( plugin_basename(__FILE__) );

			if ( isset( $_POST['start_bgp'] ) && wp_verify_nonce( $_POST['imcron_nonce'], plugin_basename(__FILE__) ) ) {
				$this->stop_bgp( );
				$this->start_bgp( );
				spawn_cron( );
			}

			if ( isset( $_POST['stop_bgp'] ) && wp_verify_nonce( $_POST['imcron_nonce'], plugin_basename(__FILE__) ) ) {
				$this->stop_bgp( );
			}

			$schedule_details = wp_get_schedules();
			$cron_array = _get_cron_array();
			$dformat = 'H:i:s \o\n d M Y';

			foreach( $cron_array as $timestamp => $hook_array ) {
				foreach( $hook_array as $hook_name => $hook_details ) {
					foreach( $hook_details as $hash => $detail ) {
						extract( $detail );
						$i18n_date = date_i18n( $dformat, $timestamp + ( get_option( 'gmt_offset' ) * 3600 ) );
						$schedule = $schedule_details[$schedule]['display'];
						$rows[] = array( $hook_name, $schedule, $i18n_date );
					}
				}
			}
			$status = $this->get_bgp_status( );
			$this->view->page( 'manage', array( 'hook_list' => $rows, 'schedule_details' => $schedule_details, 'status' => $status, 'dformat' => $dformat, 'imcron_nonce' => $imcron_nonce ) );
		}

		public function get_bgp_status( ) {

			if ( $this->logging ) error_log( 'Get BGP Status' );
			extract( $this->settings );
			$alive = false;
			$started = '';
			$last_run = '';
			$lock_exists = false;
			if ( $handle = opendir( $bgp_folder ) ) {
				while ( ($file = readdir( $handle ) ) !== false ) {
					if ( substr( $file, -4, 4 ) == '.lck' ) {
						if ( $this->logging ) error_log( "Found $file" );
						$started = substr( $file, 0, -4 );
						$last_run = file_get_contents( $bgp_folder . '/' . $file );
						if ( $this->logging ) error_log( "started: $started" );
						if ( $this->logging ) error_log( "last_run: $last_run" );
						if ( empty( $last_run ) ) $last_run = $started;
						$time_check = time( ) - $interval - 600;

						if ( $this->logging ) error_log( "$time_check $last_run" );
						if ( $time_check > $last_run ) { // BGP might have died
							unlink( $bgp_folder . '/' . $file ); // Will report died, so make sure it has
						} else {
							$alive = true;
						}
						break; // Doesn't matter if there's another lockfile, no new processes should start
					}
				}
				closedir( $handle );
			}
			return array( 'alive' => $alive, 'started' => $started, 'last_run' => $last_run );
		}
	}
}

// Main

if ( class_exists( 'imcron_controller' ) ) {
	$imcron_controller = new imcron_controller( );

	register_activation_hook( __FILE__, array( $imcron_controller, 'activation' ) );
	register_deactivation_hook( __FILE__, array( $imcron_controller, 'deactivation' ) );


	// Actions
	add_action( 'admin_menu', array( $imcron_controller, 'add_menus' ) );
	add_action( 'imcron_bgp', array( $imcron_controller, 'bgp_keep_alive' ) );

	// Filters
	add_filter( 'plugin_action_links', array( $imcron_controller, 'add_plugin_link' ), 10, 2 );
	add_filter( 'cron_schedules', array( $imcron_controller, 'add_cron_schedules' ) );
}

?>
