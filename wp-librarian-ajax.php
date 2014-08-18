<?php
// For simplicity, 'die()' will be referred to as if it were 'return' within this file


// Ensures only authorised users can access data via AJAX
if ( wp_lib_is_librarian() ) {
	add_action( 'wp_ajax_wp_lib_fetch_notifications', 'wp_lib_fetch_notifications' );


}

// Starts PHP session
function wp_lib_start_session() {
	session_name( 'wp_lib_session' );
	session_start();
}

// Adds notification to PHP session
function wp_lib_add_notification( $notification ) {
	wp_lib_start_session();
	
	$_SESSION['notifications'][] = $notification;
	
	session_write_close();
}

// Fetches any notifications
function wp_lib_ajax_prep_notifications() {
	wp_lib_start_session();
	
	$notifications = $_SESSION['notifications'];
	
	unset( $_SESSION['notifications'] );
	
	session_write_close();
	
	// If there are no notifications to display, return nothing
	if ( !$notifications || !is_array( $notifications ) )
		die();
	else {
		echo $notifications;
		die;
	}
}



?>