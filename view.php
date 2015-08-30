<?php

class imcron_view {
	function page( $page_name, $options ) {
		extract( $options ); // Variabalise for easier access in the view
		$inner_page = "${page_name}_view.php";
		require_once( 'layout.php' );
	}

}

?>
