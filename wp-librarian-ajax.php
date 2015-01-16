<?php
/*
 * WP-LIBRARIAN AJAX
 * Handles all of WP-Librarian's AJAX requests, calls the relevant functions to render pages or modify the Library
 * All functions prefixed 'page' return HTML pages, while all functions prefixed 'do' modify the Library
 * Note that, for simplicity, 'die()' will be referred to here as if it were 'return' within this file
 */

	/* -- Page Requests -- */
	/* Dynamically loaded pages */
add_action( 'wp_ajax_wp_lib_page', function() {
	wp_lib_load_helper( 'ajax' );
	
	// Creates AJAX page object
	$GLOBALS['wp_lib_ajax'] = new WP_LIB_AJAX_PAGE;
	
	// Triggers preparation of relevant Dash page
	$GLOBALS['wp_lib_ajax']->loadPage();
	
	// Fail-safe
	die(0);
});

	/* -- Dashboard Actions -- */
	/* AJAX requests to modify the Library */
add_action( 'wp_ajax_wp_lib_action', function() {
	wp_lib_load_helper( 'ajax' );
	
	// Creates AJAX page object
	$GLOBALS['wp_lib_ajax'] = new WP_LIB_AJAX_ACTION;
	
	// Triggers preparation of relevant Dash page
	$GLOBALS['wp_lib_ajax']->doAction();
	
	// Fail-safe
	die(0);
});

	/* -- Dashboard Parts -- */
	/* AJAX requests for parts of pages or specific information */

add_action( 'wp_ajax_wp_lib_api', function() {
	wp_lib_load_helper( 'ajax' );
	
	// Creates AJAX API request object
	$GLOBALS['wp_lib_ajax'] = new WP_LIB_AJAX_API;
	
	// Triggers preparation of requested data
	$GLOBALS['wp_lib_ajax']->doRequest();
	
	// Fail-safe
	die(0);
});
?>