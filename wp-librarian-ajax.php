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
	// If user does not have a proper Library role, disallows access
	if ( !wp_lib_is_librarian() )
		wp_lib_stop_ajax( false, 112 );
	
	// Sets dash page to nothing if unspecified
	$dash_page = isset( $_POST['dash_page'] ) ? $_POST['dash_page'] : null;
	
	// Calls relevant function to load requested page
	switch( $_POST['dash_page'] ) {
		case 'dashboard':
			wp_lib_page_dashboard();
		break;
		
		case 'manage-item':
			wp_lib_page_manage_item();
		break;
		
		case 'manage-member':
			wp_lib_page_manage_member();
		break;
		
		case 'manage-loan':
			wp_lib_page_manage_loan();
		break;
		
		case 'manage-fine':
			wp_lib_page_manage_fine();
		break;
		
		case 'scan-item':
			wp_lib_page_scan_item();
		break;
		
		case 'scheduling-page':
			wp_lib_page_scheduling_page();
		break;
		
		case 'return-past':
			wp_lib_page_return_past();
		break;
		
		case 'resolve-loan':
			wp_lib_page_resolution_page();
		break;
		
		case 'object-deletion':
			wp_lib_page_confirm_deletion();
		break;
		
		default:
			wp_lib_stop_ajax( false, 502 );
		break;
	}
});

	/* -- Dashboard Actions -- */
	/* AJAX requests to modify the Library */
add_action( 'wp_ajax_wp_lib_action', function() {
	// If user does not have a proper Library role, disallows access
	if ( !wp_lib_is_librarian() )
		wp_lib_stop_ajax( false, 112 );
	
	switch( $_POST['dash_action'] ) {
		case 'loan':
			// Fetches params from AJAX request
			$item_id = $_POST['item_id'];
			$member_id = $_POST['member_id'];
			$loan_length = $_POST['loan_length'];
			
			// If item or member ID fail to validate, return false (errors are handled by the validation functions)
			if ( !wp_lib_valid_item_id( $item_id ) || !wp_lib_valid_member_id( $member_id ) )
				wp_lib_stop_ajax( false );
			
			// If nonce fails to validate, return false (errors are handled by the validation functions)
			if ( !wp_lib_verify_nonce( 'Managing Item: ' . $item_id ) )
				wp_lib_stop_ajax( false );
			
			// Attempts to loan item
			$success = wp_lib_loan_item( $item_id, $member_id, $loan_length );
			
			// Kills execution, returning if loan succeeded
			wp_lib_stop_ajax( $success );
		break;
		
		case 'schedule':
			// Fetches params from AJAX request
			$item_id = $_POST['item_id'];
			$member_id = $_POST['member_id'];
			$start_date = $_POST['start_date'];
			$end_date = $_POST['end_date'];
			
			// If item or member ID fail to validate, return false (errors are handled by the validation functions)
			if ( !wp_lib_valid_item_id( $item_id ) || !wp_lib_valid_member_id( $member_id ) ) {
				wp_lib_stop_ajax( false );
			}

			// Checks if nonce is valid
			if ( !wp_lib_verify_nonce( 'Managing Item: ' . $item_id ) )
				wp_lib_stop_ajax( false );
			
			// Attempts to convert given dates to Unix timestamps
			wp_lib_convert_date( $start_date );
			wp_lib_convert_date( $end_date );
				
			// Checks if dates failed to convert, return false and call error
			if ( !$start_date || !$end_date )
				wp_lib_stop_ajax( false, 312 );
			
			// If loan starts before it sends or ends before current time, calls an error and The Doctor
			if ( $start_date > $end_date || $end_date < current_time( 'timestamp' ) ) {
				wp_lib_error( 307 );
				return false;
			}
			
			// Schedules loan of item. If function returns new loan's ID then scheduling succeeded
			$result = ( is_numeric( wp_lib_schedule_loan( $item_id, $member_id, $start_date, $end_date ) ) ? true : false );
			
			// If scheduling succeeded, notifies user of successful loan
			if ( $result )
				wp_lib_add_notification( 'A loan of ' . get_the_title( $item_id ) . ' has been scheduled' );
			
			// Returns result (boolean)
			wp_lib_stop_ajax( $result );
		break;
		
		case 'return-item':
			// Fetches params from AJAX request
			$item_id = $_POST['item_id'];
			$end_date = $_POST['end_date'];
			
			// If item ID fails to validate, return false
			if ( !wp_lib_valid_item_id( $item_id ) )
				wp_lib_stop_ajax( false );
			
			/* The nonce needed to validate will be different depending on if the function
			 * was called from the Item management page or the return past page
			 * the_more_you_know.jpeg
			 */
			
			// If the date is given (item is not being returned currently)
			if ( $end_date ) {
				// Checks if nonce is valid
				if ( !wp_lib_verify_nonce( 'Past returning: ' . $item_id ) )
					wp_lib_stop_ajax( false );
				
				// Attempts to converts formatted date to Unix timestamp e.g. 12/08/2013 -> 1386460800
				wp_lib_convert_date( $end_date );
				
				// If date failed to convert
				if ( !$end_date )
					wp_lib_stop_ajax( false, 310 );
			} else {
				// Checks if nonce is valid
				if ( !wp_lib_verify_nonce( 'Managing Item: ' . $item_id ) )
					wp_lib_stop_ajax( false );
				
				$end_date = false;
			}
			
			// Converts 'no fine' to boolean
			if ( $_POST['no_fine'] === 'true' )
				$no_fine = true;
			else
				$no_fine = false;
			
			// Attempts to return item, returning success/failure
			wp_lib_stop_ajax( wp_lib_return_item( $item_id, $end_date, $no_fine ) );
		break;
		
		case 'fine-member':
			// Fetches params from AJAX request
			$item_id = $_POST['item_id'];
			$end_date = $_POST['end_date'];
			
			// If item ID fails to validate, return false
			if ( !wp_lib_valid_item_id( $item_id ) )
				wp_lib_stop_ajax( false );
			
			// Checks if nonce is valid
			if ( !wp_lib_verify_nonce( 'Resolution of item ' . $item_id . ' for loan '. get_post_meta( $item_id, 'wp_lib_loan', true ) ) )
				wp_lib_stop_ajax( false );
			
			// If the date is given (item is not being returned currently)
			if ( $end_date ) {
				// Attempts to converts formatted date to Unix timestamp e.g. 12/08/2013 -> 1386460800
				wp_lib_convert_date( $end_date );
				
				// If date failed to convert
				if ( !$end_date )
					wp_lib_stop_ajax( false, 310 );
			}
			
			// Fines member and returns item. Returns result
			wp_lib_stop_ajax( wp_lib_create_fine( $item_id, $end_date ) );
		break;
		
		case 'cancel-fine':
			// Fetches params from AJAX request
			$fine_id = $_POST['fine_id'];
			
			// Checks if fine ID is valid
			wp_lib_check_fine_id( $fine_id );
			
			// Checks if nonce is valid
			if ( !wp_lib_verify_nonce( 'Managing Fine: ' . $fine_id ) )
				wp_lib_stop_ajax( false );
			
			// Attempts to cancel fine
			$success = wp_lib_cancel_fine( $fine_id );
			
			// Returns success/failure as boolean
			wp_lib_stop_ajax( $success );
		break;
		
		case 'delete-object':
			// Fetches library object ID
			$post_id = $_POST['post_id'];
			
			// Validates ID of Library object
			if ( !wp_lib_get_object_type( $post_id ) )
				wp_lib_stop_ajax( false, 317 );
			
			// Checks if nonce is valid
			if ( !wp_lib_verify_nonce( 'Deleting object: ' . $post_id ) )
				wp_lib_stop_ajax( false );
			
			// Placeholder
			wp_lib_add_notification( "Object not deleted" );
			wp_lib_stop_ajax( true );
		break;
		
		case 'clean-item':
			$item_id = $_POST['item_id'];
			
			// If item ID fails to validate, return false
			if ( !wp_lib_valid_item_id( $item_id ) )
				wp_lib_stop_ajax( false );
			
			// Strips any loan or member currently attached to item
			if ( wp_lib_clean_item( $item_id ) )
				wp_lib_stop_ajax( true );
			else
				wp_lib_stop_ajax( false );
		break;
		
		case 'scan-barcode':
			// Fetches barcode
			$barcode = $_POST['code'];

			// If barcode is zero, invalid barcode was given
			if ( !ctype_digit( $barcode ) )
				wp_lib_stop_ajax( false, 318 );
				
			// Sets up meta query arguments
			$args = array(
				'post_type'	=> 'wp_lib_items',
				'post_status'	=> 'publish',
				'meta_query'	=> array(
					array(
						'key'	=> 'wp_lib_item_barcode',
						'value'	=> $barcode,
						'compare'	=> 'IN'
					)
				)
			);
			
			// Looks for post(s) with barcode
			$query = new WP_Query( $args );
			
			// Checks number of posts found
			$posts_found = $query->found_posts;
			
			// If an item was found
			if ( $posts_found == 1 ) {
				$query->the_post();
				
				// Return item ID
				echo json_encode( get_the_ID() );
				
				wp_lib_stop_ajax();
			} elseif ( $posts_found > 1 ) {
				// If multiple items have said barcode, call error
				wp_lib_stop_ajax( false, 204 );
			} else {
				// If no items were found, call error
				wp_lib_stop_ajax( false, 319 );
			}
		break;
		
		default:
			wp_lib_stop_ajax( false, 500 );
		break;
	}
	
	// Fail-safe
	wp_lib_stop_ajax( false, 500 );
});

// Fetches all buffered notifications, this includes errors
add_action( 'wp_ajax_wp_lib_fetch_notifications', 'wp_lib_fetch_notifications' );

	/* -- Misc AJAX Functions -- */
	/* Useful functions used for AJAX requests */

// Starts PHP session
function wp_lib_start_session() {
	session_name( 'wp_lib_session' );
	session_start();
}

// Adds notification to session buffer
function wp_lib_add_notification( $notification, $error_code = 0 ) {
	// If session is not active, start session
	if ( !isset($_SESSION) )
		wp_lib_start_session();
	
	// If existing notification count has reached limit, do not add notification
	if ( $_SESSION['notifications'] && count( $_SESSION['notifications'] ) > 9 )
		return;

	// Adds notification to buffer. If notification doesn't have an error code, zero is used
	$_SESSION['notifications'][] = array( $error_code, $notification );
}

function wp_lib_fetch_notifications() {
	// Starts session
	wp_lib_start_session();
	
	// Fetches notifications from buffer
	$notifications = $_SESSION['notifications'];
	
	// If there are notifications to fetch, return them
	if ( $_SESSION['notifications'] )
		echo json_encode( $_SESSION['notifications'] );
	// Otherwise return false
	else
		echo json_encode( false );
	
	// Clears buffer
	unset( $_SESSION['notifications'] );
	
	wp_lib_stop_ajax();
}

// Performs any necessary actions before AJAX request returns data
function wp_lib_stop_ajax( $output = '', $error_code = false, $params = false ) {
	// If specified, calls error with code provided
	if ( $error_code )
		wp_lib_error( $error_code, false, $params );

	// If an output has been specified, render
	if ( is_bool( $output ) || is_array( $output ) )
		echo json_encode( $output );
	
	// Closes PHP session
	session_write_close();
	
	// Kills execution
	die();
}

// Encodes given parameters as an array for client-side JavaScript to render as HTML elements
function wp_lib_send_page( $page_title, $tab_title, $content = false, $form = false, $page_scripts = false ) {
	// Creates buffer to be encoded and adds parameters
	$buffer = array(
		'pageTitle'	=> $page_title,
		'title'	=> $tab_title
	);
	
	// Checks if no content has been specified
	if ( !is_array( $content ) && !is_array( $form ) )
		wp_lib_stop_ajax( false, 501 );
	
	// If content has been specified, add to array
	if ( $content )
		$buffer['content'] = $content;
	
	// If a form has been specified, add to array
	if ( $form )
		$buffer['form'] = $form;
		
	if ( $page_scripts ) {
		$buffer['scripts'] = $page_scripts;
	}
	
	wp_lib_stop_ajax( $buffer );
}

	/* -- AJAX Pages -- */
	/* Renders then returns Dashboard pages */

// Displays Library Dashboard
function wp_lib_page_dashboard() {
	// Dashboard icons
	$buttons = array(
		array(
			'title'	=> 'Scan Item',
			'icon'	=> 'default',
			'link'	=> 'dash-page',
			'value'	=> 'scan-item'
		),
		array(
			'title'	=> 'Manage Items',
			'icon'	=> 'default',
			'link'	=> 'post-type',
			'pType'	=> 'wp_lib_items'
		),
		array(
			'title'	=> 'Manage Members',
			'icon'	=> 'default',
			'link'	=> 'post-type',
			'pType'	=> 'wp_lib_members'
		),
		array(
			'title'	=> 'Manage Fines',
			'icon'	=> 'default',
			'link'	=> 'post-type',
			'pType'	=> 'wp_lib_fines'
		),
		array(
			'title'	=> 'Settings',
			'icon'	=> 'default',
			'link'	=> 'admin-url',
			'url'	=> 'edit.php?post_type=wp_lib_items&page=wp-lib-settings'
		),
		array(
			'title'	=> 'Help',
			'icon'	=> 'default',
			'link'	=> 'url',
			'url'	=> 'http://sci1.co.uk/wp-librarian/'
		)
	);

	// Adds element type to each button, so it will be rendered correctly client-side
	foreach ( $buttons as $key => $value ) {
		$buttons[$key]['type'] = 'dash-button';
	}
	
	// Prepares Dashboard content
	$page = array(
		array(
			'type'		=> 'paras',
			'content'	=> array( 'Use the options below to manage your Library' )
		),
		array(
			'type'		=> 'div',
			'classes'	=> 'dashboard-buttons-wrap',
			'inner'		=> $buttons
		)
	);
	
	// Sets page title and browser page title
	$page_title = 'Library Dashboard';

	// Sends page to client
	wp_lib_send_page( $page_title, $page_title, $page );
}

function wp_lib_page_manage_item() {
	// Fetches item ID from AJAX request
	$item_id = $_POST['item_id'];
	
	// Checks if item ID is valid
	wp_lib_check_item_id( $item_id );
	
	// Prepares the management header
	$header = wp_lib_prep_item_management_header( $item_id );
	
	// Adds page nonce
	$form[] = wp_lib_prep_nonce( 'Managing Item: ' . $item_id );
	
	// Adds item ID to form
	$form[] = array(
		'type'	=> 'hidden',
		'name'	=> 'item_id',
		'value'	=> $item_id
	);
	
	// If item is currently on loan
	if ( wp_lib_on_loan( $item_id ) ) {
		// Fetches loan ID from Item meta
		$loan_id = wp_lib_fetch_loan_id( $item_id );
		
		// Regardless of lateness, provides link to return item at a past date
		$form[] = array(
			'type'	=> 'button',
			'link'	=> 'page',
			'html'	=> 'Return at a past date',
			'value'	=> 'return-past'
		);
		
		// If item is late
		if ( wp_lib_item_late( $loan_id ) ) {
			// Provides link to resolve late item
			$form[] = array(
				'type'	=> 'button',
				'link'	=> 'page',
				'html'	=> 'Resolve',
				'value'	=> 'resolve'
			);
		
		} else {
			// Provides link to return item today
			$form[] = array(
				'type'	=> 'button',
				'link'	=> 'action',
				'html'	=> 'Return',
				'value'	=> 'return-item'
			);
		}
	}
	elseif ( wp_lib_loanable( $item_id ) ) {
		$options = wp_lib_prep_member_options();
		
		// Adds loan item title
		$form[] = array(
			'type'	=> 'header',
			'size'	=> 4,
			'html'	=> 'Loan Item:'
		);
		
		// Adds dropdown menu using members options created above
		$form[] = array(
			'type'			=> 'select',
			'options'		=> $options,
			'optionClass'	=> 'member-choice-option',
			'classes'		=> array( 'member-choice' ),
			'name'			=> 'member_id'
		);
		
		// Creates options for loan length
		$length_options[] = array(
			'value'	=> '',
			'html'	=> '0 Days'
		);
		
		// Creates loan length options from 1-12
		for ($i = 1; $i < 13; $i++){
			$length_options[] = array(
				'value'	=> $i,
				'html'	=> $i . ' Days'
			);
		}
		
		// Adds dropdown menu for loan length
		$form[] = array(
			'type'			=> 'select',
			'options'		=> $length_options,
			'optionClass'	=> 'loan-length-option',
			'classes'		=> array( 'loan-length' ),
			'name'			=> 'loan_length'
		);
		
		// Button to loan item
		$form[] = array(
			'type'	=> 'button',
			'link'	=> 'action',
			'value'	=> 'loan',
			'html'	=> 'Loan Item'
		);
		
		// Button to schedule a loan in the future
		$form[] = array(
			'type'	=> 'button',
			'link'	=> 'page',
			'html'	=> 'Schedule Future Loan',
			'value'	=> 'scheduling-page'
		);

	}
	
	// Button to edit item
	$form[] = array(
		'type'	=> 'button',
		'link'	=> 'edit',
		'html'	=> 'Edit',
	);
	
	// Button to delete item
	$form[] = array(
		'type'	=> 'button',
		'link'	=> 'page',
		'value'	=> 'object-deletion',
		'html'	=> 'Delete'
	);
	
	// Sets up loan history query arguments
	$args = array(
		'post_type' 	=> 'wp_lib_loans',
		'post_status'	=> 'publish',
		'meta_query'	=> array(
			array(
				'key'		=> 'wp_lib_item',
				'value'		=> $item_id,
				'compare'	=> 'IN'
			)
		)
	);
	
	// Creates query of all loans of this item
	$loan_query = new WP_Query( $args );
	
	// Checks for any loans in query
	if ( $loan_query->have_posts() ){
		// Initialises loans array
		$loans = array();
		
		// Title of Loans list
		$form[] = array(
			'type'	=> 'header',
			'size'	=> '4',
			'html'	=> 'Loans:'
		);
		
		// Iterates through loans
		while ( $loan_query->have_posts() ) {
			// Selects current post (loan)
			$loan_query->the_post();
			
			// Fetches loan ID
			$loan_id = get_the_ID();
			
			// Fetches all loan's meta
			$meta = get_post_meta( $loan_id );
			
			// Gets member ID from loan meta
			$member_id = $meta['wp_lib_member'][0];
			
			$loan_status = wp_lib_format_loan_status( $meta['wp_lib_status'][0] );
			
			// If loan incurred fine, change loan status to include a link to manage said fine
			if ( $meta['wp_lib_status'][0] == 4 ) {
				$loan_status = array( $loan_status, wp_lib_manage_fine_url( $meta['wp_lib_fine'][0] ) );
			}
			
			$loans[] = array(
				'loan'		=> array( '#' . get_the_ID(), wp_lib_manage_loan_url( $loan_id ) ),
				'member'	=> array( get_the_title( $member_id ), wp_lib_manage_member_url( $member_id ) ),
				'status'	=> $loan_status,
				'loaned'	=> wp_lib_format_unix_timestamp( $meta['wp_lib_start_date'][0] ),
				'expected'	=> wp_lib_format_unix_timestamp( $meta['wp_lib_end_date'][0] ),
				'returned'	=> wp_lib_format_unix_timestamp( $meta['wp_lib_returned_date'][0] )
			);
		}
		
		// Adds loans (rows) to table
		$form[] = array(
			'type'		=> 'dtable',
			'id'		=> 'member-loans',
			'headers'	=> array(
				'Loan',
				'Member',
				'Status',
				'Loaned',
				'Expected',
				'Returned'
			),
			'data'		=> $loans,
			'labels'	=> array(
				'records'	=> 'loans'
			)
		);
	} else {
		$form[] = array(
			'type'		=> 'paras',
			'content'	=> array( 'No loans to display' )
		);
	}
	
	// Creates titles for page and browser tab
	$page_title = 'Managing: ' . get_the_title( $item_id );
	$tab_title = 'Managing Item #' . $item_id;
	
	// Encodes page as an array to be rendered client-side
	wp_lib_send_page( $page_title, $tab_title, $header, $form );
}

// Displays member's details and loan history
function wp_lib_page_manage_member() {
	// Fetches member ID from AJAX request
	$member_id = $_POST['member_id'];
	
	// Checks ID
	wp_lib_check_member_id( $member_id );
	
	// Renders management header
	$header = wp_lib_prep_member_management_header( $member_id );
	
	// Adds nonce to form
	$content[] = wp_lib_prep_nonce( 'Managing Member ' . $member_id );
	
	// Adds member ID to form
	$content[] = array(
		'type'	=> 'hidden',
		'name'	=> 'member_id',
		'value'	=> $member_id
	);
	
	// Button to edit member
	$content[] = array(
		'type'	=> 'button',
		'link'	=> 'edit',
		'html'	=> 'Edit',
	);
	
	// Button to delete member
	$content[] = array(
		'type'	=> 'button',
		'link'	=> 'page',
		'value'	=> 'object-deletion',
		'html'	=> 'Delete'
	);
	
	// Sets up loan history query arguments
	$args = array(
		'post_type' 	=> 'wp_lib_loans',
		'post_status'	=> 'publish',
		'meta_query'	=> array(
			array(
				'key'		=> 'wp_lib_member',
				'value'		=> $member_id,
				'compare'	=> 'IN'
			)
		)
	);
	
	// Creates query of all loans attached to this member
	$loan_query = new WP_Query( $args );
	
	// Checks for any loans attached to member
	if ( $loan_query->have_posts() ){
		// Initialises loans array
		$loans = array();
		
		$content[] = array(
			'type'	=> 'header',
			'size'	=> '4',
			'html'	=> 'Loans:'
		);
		
		// Iterates through loans
		while ( $loan_query->have_posts() ) {
			// Selects current post (loan)
			$loan_query->the_post();
			
			// Fetches loan ID
			$loan_id = get_the_ID();
			
			// Fetches all loan's meta
			$meta = get_post_meta( $loan_id );
			
			// Gets item ID from loan meta
			$item_id = $meta['wp_lib_item'][0];
			
			$loan_status = wp_lib_format_loan_status( $meta['wp_lib_status'][0] );
			
			// If loan incurred fine, change loan status to include a link to manage said fine
			if ( $meta['wp_lib_status'][0] == 4 ) {
				$loan_status = array( $loan_status, wp_lib_manage_fine_url( $meta['wp_lib_fine'][0] ) );
			}
			
			$loans[] = array(
				'loan'		=> array( '#' . get_the_ID(), wp_lib_manage_loan_url( $loan_id ) ),
				'item'		=> array( get_the_title( $item_id ), wp_lib_manage_item_url( $item_id ) ),
				'status'	=> $loan_status,
				'loaned'	=> wp_lib_format_unix_timestamp( $meta['wp_lib_start_date'][0] ),
				'expected'	=> wp_lib_format_unix_timestamp( $meta['wp_lib_end_date'][0] ),
				'returned'	=> wp_lib_format_unix_timestamp( $meta['wp_lib_returned_date'][0] )
			);
		}
		
		// Adds loans (rows) to table
		$content[] = array(
			'type'		=> 'dtable',
			'id'		=> 'member-loans',
			'headers'	=> array(
				'Loan',
				'Item',
				'Status',
				'Loaned',
				'Expected',
				'Returned'
			),
			'data'		=> $loans,
			'labels'	=> array(
				'records'	=> 'loans'
			)
		);
	} else {
		$content[] = array(
			'type'		=> 'paras',
			'content'	=> array( 'No loans to display' )
		);
	}

	$page_title = 'Managing: ' . get_the_title( $member_id );
	
	$tab_title = 'Managing Member #' . $member_id;
	
	wp_lib_send_page( $page_title, $tab_title, array_merge( $header, $content ) );
}

// Displays lack of loan management page
function wp_lib_page_manage_loan() {
	// Fetches loan ID from AJAX request
	$loan_id = $_POST['loan_id'];
	
	// Checks if loan ID is valid
	wp_lib_check_loan_id( $loan_id );
	
	// Returns error
	wp_lib_stop_ajax( false, 202 );
}

// Displays fine details and provides options to modify the fine
function wp_lib_page_manage_fine() {
	// Fetches fine ID from AJAX request
	$fine_id = $_POST['fine_id'];
	
	// Checks if fine ID is valid
	wp_lib_check_fine_id( $fine_id );

	$header = wp_lib_prep_fine_management_header( $fine_id );
	
	// Fetches fine status
	$fine_status = get_post_meta( $fine_id, 'wp_lib_status', true );
	
	// Adds nonce to form
	$content[] = wp_lib_prep_nonce( 'Managing Fine: ' . $fine_id );
	
	// Adds fine ID to form
	$form[] = array(
		'type'	=> 'hidden',
		'name'	=> 'fine_id',
		'value'	=> $fine_id
	);
	
	// If fine has not already been cancelled, allows fine to be cancelled
	if ( $fine_status != 2 ) {
		$form[] = array(
			'type'	=> 'button',
			'link'	=> 'action',
			'value'	=> 'cancel-fine',
			'html'	=> 'Cancel Fine'
		);
	}
	
	// Creates browser window and page title
	$title = 'Managing Fine #' . $fine_id;
	
	// Sends entire page to be encoded in JSON
	wp_lib_send_form( $title, $title, $header, $form );
}

// Page for looking up an item by its barcode
function wp_lib_page_scan_item() {
	// Enqueues barcode page script
	$scripts[] = plugins_url( '/scripts/admin-barcode-scanner.js', __FILE__ );
	
	$form = array(
		wp_lib_prep_nonce( 'Lookup Item Barcode' ),
		array(
			'type'		=> 'paras',
			'content'	=> array( 'Once the barcode is scanned the item will be retried automatically' )
		),
		array(
			'type'		=> 'text',
			'id'		=> 'barcode-input',
			'name'		=> 'item_barcode',
			'autofocus'	=> true
		),
		array(
			'type'	=> 'button',
			'id'	=> 'barcode-submit',
			'link'	=> 'action',
			'value'	=> 'scan-barcode',
			'html'	=> 'Scan'
		)
	);
	
	// Sets item title
	$title = 'Scan Item Barcode';
	
	// Sends form to client to be rendered
	wp_lib_send_page( $title, $title, false, $form, $scripts );
}

// Allows user to schedule a loan to happen in the future, to be fulfilled when the time comes
function wp_lib_page_scheduling_page() {
	// Fetches item ID from AJAX request
	$item_id = $_POST['item_id'];
	
	// Checks if item ID is valid
	wp_lib_check_item_id( $item_id );
	
	// Displays the management header
	$header = wp_lib_prep_item_management_header( $item_id );
	
	$member_options = wp_lib_prep_member_options();
	
	// Formats placeholder loan start date (current date)
	$start_date = Date( 'Y-m-d' );
	
	// Formats placeholder loan end date (current date + default loan length)
	$end_date = Date( 'Y-m-d', current_time( 'timestamp' ) + ( get_option( 'wp_lib_loan_length', 12 ) * 24 * 60 * 60) );
	
	$form = array(
		wp_lib_prep_nonce( 'Scheduling Item: ' . $item_id ),
		array(
			'type'	=> 'hidden',
			'name'	=> 'item_id',
			'value'	=> $item_id
		),
		array(
			'type'		=> 'div',
			'classes'	=> array( 'member-select', 'manage-item' ),
			'inner'		=> array(
				array(
					'type'	=> 'strong',
					'html'	=> 'Member:',
					'label'	=> 'member-select'
				),
				array(
					'type'			=> 'select',
					'id'			=> 'member-select',
					'name'			=> 'member_id',
					'options'		=> $member_options,
					'optionClass'	=> 'member-select-option',
					'class'			=> 'member-select'
				)
			)
		),
		array(
			'type'	=> 'div',
			'inner'	=> array(
				array(
					'type'	=> 'strong',
					'html'	=> 'Start Date:',
					'label'	=> 'loan-start'
				),
				array(
					'type'	=> 'date',
					'name'	=> 'start_date',
					'id'	=> 'loan-start',
					'value'	=> $start_date
				)
			)
		),
		array(
			'type'	=> 'div',
			'inner'	=> array(
				array(
					'type'	=> 'strong',
					'html'	=> 'End Date:',
					'label'	=> 'loan-end'
				),
				array(
					'type'	=> 'date',
					'name'	=> 'end_date',
					'id'	=> 'loan-end',
					'value'	=> $end_date
				)
			)
		),
		array(
			'type'	=> 'button',
			'html'	=> 'Schedule Loan',
			'link'	=> 'action',
			'value'	=> 'schedule'
		)
	);
	
	$page_title = 'Scheduling loan of ' . get_the_title( $item_id );
	
	$tab_title = 'Scheduling loan of #' . $item_id;
	
	wp_lib_send_page( $page_title, $tab_title, $header, $form );
}

// Displays page for returning an item in the past
function wp_lib_page_return_past() {
	// Fetches item ID from AJAX request
	$item_id = $_POST['item_id'];
	
	// Checks if item ID is valid
	wp_lib_check_item_id( $item_id );

	// Checks if item is on loan
	if ( !wp_lib_on_loan( $item_id ) )
		wp_lib_stop_ajax( false, 402 );
	
	// Renders the management header
	$header = wp_lib_prep_item_management_header( $item_id );
	
	$form = array(
		wp_lib_prep_nonce( 'Past returning: ' . $item_id ),
		array(
			'type'	=> 'hidden',
			'name'	=> 'item_id',
			'value'	=> $item_id
		),
		array(
			'type'	=> 'div',
			'inner'	=> array(
				array(
					'type'	=> 'strong',
					'html'	=> 'Date:',
					'label'	=> 'loan-end-date'
				),
				array(
					'type'	=> 'date',
					'name'	=> 'end_date',
					'id'	=> 'loan-end-date',
					'value'	=> Date( 'Y-m-d' )
				)
			)
		),
		array(
			'type'	=> 'button',
			'link'	=> 'action',
			'value'	=> 'return-item',
			'html'	=> 'Return'
		)
	);
	
	$page_title = 'Returning: ' . get_the_title( $item_id );
	
	$tab_title = 'Returning item #' . $item_id;
	
	wp_lib_send_page( $page_title, $tab_title, $header, $form );
}

// Informs librarian of details of item lateness and provides options to resolve the issue
function wp_lib_page_resolution_page() {
	// Fetches item ID from AJAX request
	$item_id = $_POST['item_id'];
	
	// Checks if item ID is valid
	wp_lib_check_item_id( $item_id );

	// Fetches loan ID using item ID
	$loan_id = wp_lib_fetch_loan_id( $item_id );
	
	// Ensures item is actually late
	if ( !wp_lib_item_late( $loan_id ) )
		wp_lib_stop_ajax( false, 406 );
	
	// Renders item management header
	$header = wp_lib_prep_item_management_header( $item_id );
	
	// Useful variables:
	// Formatted string of item lateness
	$days_late = wp_lib_prep_item_due( $item_id, $date, array( 'late' => '\d day\p' ) );
	// Item's title
	$title = get_the_title( $item_id );
	// Librarian set charge for each day an item is late
	$fine_per_day = get_option( 'wp_lib_fine_daily' );
	// Days item is late
	$late = -wp_lib_cherry_pie( $loan_id, false );
	// Total fine member member is facing, if charged
	$fine = wp_lib_format_money( $fine_per_day * $late );
	// Fine per day formatted
	$fine_per_day_formatted = wp_lib_format_money( $fine_per_day );
	
	$form = array(
		wp_lib_prep_nonce( 'Resolution of item ' . $item_id . ' for loan '. $loan_id ),
		array(
			'type'		=> 'paras',
			'content'	=> array( "{$title} is late by {$days_late}. If fined, the member would incur a fine of {$fine} ({$fine_per_day_formatted} per day x {$days_late})" )
		),
		array(
			'type'	=> 'hidden',
			'name'	=> 'item_id',
			'value'	=> $item_id
		),
		array(
			'type'	=> 'button',
			'link'	=> 'action',
			'value'	=> 'fine-member',
			'html'	=> 'Fine'
		),
		array(
			'type'	=> 'button',
			'link'	=> 'action',
			'value'	=> 'return-item-no-fine',
			'html'	=> 'Return with no Fine'
		)
	);
	
	$page_title = 'Resolving Late Item: ' . $title;
	
	$tab_title = 'Resolving Item #' . $item_id;
	
	wp_lib_send_page( $page_title, $tab_title, $header, $form );
}

// Confirmation page to make sure the Librarian knows what deleting the item/loan/fine/member will do
// This page is not visited if the option wp_lib_mass_deletion is set to true
function wp_lib_page_confirm_deletion() {
	// Prepares deletion page based off given post type
	if ( isset( $_POST['item_id'] ) ) {
		$post_id = $_POST['item_id'];
		
		wp_lib_check_item_id( $post_id );
		
		$header = wp_lib_prep_item_management_header( $post_id );
		
		$page_title = 'Deleting: ' . get_the_title( $post_id );
		
		$tab_title = 'Deleting Item #' . $post_id;
		
		// Passes array to client-side JavaScript to render form
		$form = array(
			array(
				'type'	=> 'hidden',
				'name'	=> 'post_id',
				'value'	=> $post_id
			),
			array(
				'type'		=> 'paras',
				'content'	=> array(
					'Deleting items is a permanent action. Any loans or fines connected to this member will be deleted as well.',
					'If you want to delete items in bulk, without this prompt, allow bulk deletion via WP-Librarian\'s settings'
				)
			),
			array(
				'type'	=> 'button',
				'link'	=> 'action',
				'value'	=> 'delete-object',
				'html'	=> 'Delete Item'
			),
			array(
				'type'	=> 'button',
				'link'	=> 'page',
				'value'	=> '',
				'html'	=> 'Cancel'
			)
		);
		
	} elseif ( isset( $_POST['loan_id'] ) ) {
		
		$post_id = $_POST['loan_id'];
		
		wp_lib_check_loan_id( $post_id );
		
		$header = wp_lib_prep_item_management_header( $post_id );
		
		$page_title = 'Deleting: Loan #' . $post_id;
		
		$tab_title = 'Deleting Loan #' . $post_id;
		
		$form = array(
			array(
				'type'		=> 'paras',
				'content'	=> array(
					'Deleting a loan is a permanent action. Any fines connected to this loan will also be deleted.',
					'If you want to delete loans in bulk, without this prompt, allow bulk deletion via WP-Librarian\'s settings'
				)
			),
			array(
				'type'	=> 'button',
				'link'	=> 'action',
				'value'	=> 'delete-object',
				'html'	=> 'Delete Loan'
			),
			array(
				'type'	=> 'button',
				'link'	=> 'page',
				'value'	=> '',
				'html'	=> 'Cancel'
			)
		);
	} elseif ( isset( $_POST['fine_id'] ) ) {
		$post_id = $_POST['fine_id'];
		
		wp_lib_check_fine_id( $post_id );
		
		$header = wp_lib_prep_fine_management_header( $post_id );
		
		$page_title = 'Deleting: Fine #' . $post_id;
		
		$tab_title = 'Deleting Fine #' . $post_id;
		
		$form = array(
			array(
				'type'	=> 'hidden',
				'name'	=> 'post_id',
				'value'	=> $post_id
			),
			array(
				'type'		=> 'paras',
				'content'	=> array(
					'Deleting a fine is a permanent action and will result in the deletion of any loan connected to this fine',
					'If you want to delete fines in bulk, without this prompt, allow bulk deletion via WP-Librarian\'s settings'
				)
			),
			array(
				'type'	=> 'button',
				'link'	=> 'action',
				'value'	=> 'delete-object',
				'html'	=> 'Delete Fine'
			),
			array(
				'type'	=> 'button',
				'link'	=> 'page',
				'value'	=> '',
				'html'	=> 'Cancel'
			)
		);
	} elseif ( isset( $_POST['member_id'] ) ) {
		$post_id = $_POST['member_id'];
		
		wp_lib_check_member_id( $post_id );
		
		$header = wp_lib_prep_member_management_header( $post_id );
		
		$page_title = 'Deleting: ' . get_the_title( $post_id );
		
		$tab_title = 'Deleting Member #' . $post_id;
		
		$form = array(
			array(
				'type'	=> 'hidden',
				'name'	=> 'post_id',
				'value'	=> $post_id
			),
			array(
				'type'		=> 'paras',
				'content'	=> array(
					'Deleting a member is a permanent action. You can choose to also delete all loans/fines tied to this member',
					'If you want to delete members in bulk, without this prompt, allow bulk deletion via WP-Librarian\'s settings'
				)
			),
			array(
				'type'	=> 'button',
				'link'	=> 'action',
				'value'	=> 'delete-object',
				'html'	=> 'Delete Member'
			),
			array(
				'type'	=> 'button',
				'link'	=> 'page',
				'value'	=> '',
				'html'	=> 'Cancel'
			)
		);
	} else {
		wp_lib_stop_ajax( false, 315 );
	}
	
	// Adds post ID and nonce to beginning of form
	array_unshift( $form, 
		wp_lib_prep_nonce( 'Deleting object: ' . $post_id ),
		array(
			'type'	=> 'hidden',
			'name'	=> 'post_id',
			'value'	=> $post_id
		)
	);
	
	// Looks for all objects connected to the current one (e.g. loans by a member, or fines as a result of a loan)
	$connected_objects = wp_lib_fetch_dependant_objects( $post_id );
	
	if ( $connected_objects ) {
		$form[] = array(
			'type'	=> 'header',
			'size'	=> 4,
			'html'	=> wp_lib_plural( count( $connected_objects ), 'Connected object\p:' )
		);
		
		foreach ( $connected_objects as $connected_object ) {
			// Initialises table row
			$object_entry = array(
				'id'	=> $connected_object[0],
			);
			
			// Adds table row with post ID and link to manage loan/fine
			switch( $connected_object[1] ) {
				case 'wp_lib_loans':
					$objects[] = array(
						'id'	=> array( $connected_object[0], wp_lib_manage_loan_url( $connected_object[0] ) ),
						'type'	=> 'Loan'
					);
				break;
				
				case 'wp_lib_fines':
					$objects[] = array(
						'id'	=> array( $connected_object[0], wp_lib_manage_fine_url( $connected_object[0] ) ),
						'type'	=> 'Fine'
					);
				break;
			}
		}
		
		// Creates table using objects
		$form[] = array(
			'type'		=> 'dtable',
			'id'		=> 'connected-objects',
			'headers'	=> array(
				'ID',
				'Type'
			),
			'data'		=> $objects,
			'labels'	=> array(
				'records'	=> 'connected objects'
			)
		);
	} else {
		$form[] = array(
			'type'		=> 'paras',
			'content'	=> array( 'No other objects in the Library are connected to this object' )
		);
	}
	
	wp_lib_send_page( $page_title, $tab_title, $header, $form );
}
?>