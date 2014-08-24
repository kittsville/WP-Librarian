<?php
$action = $_GET['item_action']; // The action to be performed (e.g. loan an item)
$page = $_GET['item_page']; // The requested page ( e.g. fine management page )
$item_id = $_GET['item_id']; // The item's ID
$member_id = $_GET['member_id']; // The ID of the member loaning/returning the item
$fine_id = $_GET['fine_id']; // The ID of the fine being managed
$start_date = $_GET['loan_start_date']; // When an item is to start being loaned
$end_date = $_GET['loan_end_date']; // When a loan of an item should have ended by
$length = $_GET['loan_length']; // The length of the loan

?>
<script>var GetVars = <?php echo json_encode( $_GET ); ?>;</script>
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
<?php
	/* -- Date Sanitization -- */
	/* Converts dates to Unix timestamps if specified */

if ( isset( $start_date ) )
	wp_lib_convert_date( $start_date );

if ( isset( $end_date ) )
	wp_lib_convert_date( $end_date );

?>
</div>
</div>