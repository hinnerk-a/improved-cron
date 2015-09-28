<?php

! defined( 'ABSPATH' ) and exit;

if ( !class_exists( 'imcron_bgp' ) ) {
	class imcron_bgp {

		private $logging = FALSE;
		private $error_logging = FALSE;

		public function __construct( $error_logging = FALSE ) {
			$this->error_logging = $error_logging;
		}

		public function __destruct( ) {
		}


		public function run( $once = false ) {

			$interval     = $this->get_setting( 'interval' );
			$site_address = $this->get_setting( 'site_address' );
			$bgp_folder   = $this->get_setting( 'bgp_folder' );
			$logfile      = $this->get_setting( 'logfile' );

			$lockfile = $bgp_folder . '/' . time() . '.lck';

			$this->write_log( "Started", $logfile );

			// Check that a process isn't already running
			if ( !$this->already_running( $bgp_folder ) || $once ) {
				$this->write_log( time( ), $lockfile, true, false, false );
				if ( $this->error_logging ) error_log( "Created $lockfile" );

				// Main loop
				while ( file_exists( $lockfile ) ) { // Deleting the lockfile should cause the process to die
					if ( $this->error_logging ) error_log( "Found $lockfile" );
					$this->write_log( 'Running (' . $site_address . ')', $logfile );
					$this->ping_site( $site_address );

					if ( $once ) {
						break;  // Exit while loop here if only running once
					}
					sleep( $interval );

					if ( file_exists( $lockfile ) ) {
						$this->write_log( time( ), $lockfile, true, false, true );
					} else {
						if ( $this->error_logging ) error_log( "File '$lockfile' no longer exists!" );
					}

					if ( !$interval ) { // Interval = 0: background process should die
						if ( file_exists( $lockfile ) ) {
							if ( $this->error_logging ) error_log( "Interval = 0, Deleting $lockfile" );
							unlink( $lockfile );
						}
					}
				}
				// Delete lock file so a future instance can start
				if ( file_exists( $lockfile ) ) {
					if ( $this->error_logging ) error_log( "Deleting $lockfile" );
					unlink( $lockfile );
				}
			} else {
				$this->write_log( 'Unable to start.  Already running!', $logfile );
			}
		}

		public function already_running( $bgp_folder ) {
			$result = false;
			if ( $handle = opendir( $bgp_folder ) ) {
				while ( ($file = readdir( $handle ) ) !== false ) {
					if ( substr( $file, -4, 4 ) == '.lck' ) {
						$result = true;
					}
				}
				closedir( $handle );
			} else {
				$result = true; // Folder couldn't be opened, stop new process from starting
			}
			return $result;
		}

		public function set_settings() {
			update_option( 'imcron_settings', $this->get_settings() );
		}

		public function get_setting( $key ) {
			$settings = $this->get_settings();
			if (!isset($settings[$key])) return false;
			return $settings[$key];
		}

		private function get_settings( ) {
			$schedules = wp_get_schedules();
			$interval_id = apply_filters( 'imcron_interval_id', 'every_minute' );
			$settings_defaults = array( // Defaults
				'site_address' => home_url() . '/wp-cron.php',
				'interval_id' => $interval_id,
				'bgp_folder' => dirname( __FILE__ ) . '/bgp',
				'logfile' => 'bgp.log'
			);

            $settings = wp_parse_args(
                get_option( 'imcron_settings', array() ),
                $settings_defaults
            );
            if ( ! is_dir( $settings['bgp_folder'] ) ) {
            	$settings['bgp_folder'] = dirname( __FILE__ ) . '/bgp';
            }
			$settings['interval'] = $schedules[$interval_id]['interval']; // in case of dynamically changing intervals
			return $settings;
		}

		private function write_log( $message, $logfile, $lockfile = false, $details = true, $overwrite = false ) {
			if ( $this->logging || $lockfile ) {
				$mem_usage = memory_get_usage( true );
				if ( !is_dir( dirname( $logfile ) ) ) {
					mkdir( dirname( $logfile ) );
				}

				$fh = ( $overwrite ) ? fopen( $logfile, 'w' ) : fopen( $logfile, 'a' );
				if ( $details ) {
					fwrite( $fh, date( 'H:i:s d-m-Y' ) . " ( mem:" . $mem_usage . " ): " . $message . "\n" );
				} else {
					fwrite( $fh, $message );
				}
				fclose( $fh );
			}
			if ( $this->error_logging ) error_log( 'logfile exists: ' . var_export( file_exists( $logfile ), TRUE ) );
		}

		private function ping_site( $url ) {
			wp_remote_get( $url );
		}

	}
}

?>
