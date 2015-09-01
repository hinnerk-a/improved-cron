<?php
! defined( 'ABSPATH' ) and exit;

class imcron_view {

	function page( $page_name, $options ) {
		$hook_list        = $options['hook_list'];
		$schedule_details = $options['schedule_details'];
		$status           = $options['status'];
		$dformat          = $options['dformat'];
		$imcron_nonce     = $options['imcron_nonce'];
		$interval         = $options['interval'];

		$inner_page = "${page_name}_view.php";
		require_once( 'layout.php' );
	}

}

?>
