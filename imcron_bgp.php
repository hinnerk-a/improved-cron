<?php

if ( !class_exists( 'imcron_bgp' ) ) {
	class imcron_bgp {

		private $cfg_file;
		private $logging = FALSE;
		private $error_logging = FALSE;

		public function __construct( $error_logging = FALSE ) {
			$this->error_logging = $error_logging;
			$this->cfg_file = dirname( __FILE__ ) . '/bgp.cfg';
		}

		public function __destruct( ) {
		}


		public function run( $once = false ) {

			extract( $this->get_cfg( ) );
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

					extract( $this->get_cfg( ) ); // Overwrite by default
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

		private function get_cfg( ) {
			$contents = explode( ',', file_get_contents( $this->cfg_file ) );
			$cfg['interval'] = $contents[0];
			$cfg['site_address'] = $contents[1];
			$cfg['bgp_folder'] = $contents[2];
			$cfg['logfile'] = $cfg['bgp_folder'] . '/' . $contents[3];
			return $cfg;
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
			if ( $this->error_logging ) error_log( var_export( file_exists( $logfile ), TRUE ) );
		}

		private function ping_site( $url ) {
			wp_remote_get( $url );
		}

	}
}

?>
