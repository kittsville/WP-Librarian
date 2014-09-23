<?php
/* 
 * WP-LIBRARIAN DASHBOARD TEMPLATE
 * This file renders the template for the Dashboard.
 * The template is then populated and modified dynamically by JavaScript
 */

wp_enqueue_style( 'wp_lib_datepicker' );
wp_enqueue_style( 'wp_lib_datepicker_mellon' );

?>
<div class="wrap">
	<div id="title-wrap">
		<h2>
			<div id="page-title"></div>
		</h2>
	</div>
	<!-- Filled with any notifications waiting in a session -->
	<div id="notifications-holder"></div>
	<div id="library-workspace">
		<strong>Loading...</strong>
	</div>
</div>