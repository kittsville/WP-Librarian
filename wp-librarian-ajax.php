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
	
	// Trims client data
	array_filter( $_POST, function( &$raw ){ $value = trim( $value ); } );
	
	// Sets dash page to nothing if unspecified
	$dash_page = isset( $_POST['dash_page'] ) ? $_POST['dash_page'] : null;
	
	// Calls relevant function to load requested page
	switch( $_POST['dash_page'] ) {
		case 'dashboard':
			wp_lib_page_dashboard();
		break;
		
		case 'view-items':
			wp_lib_page_view_items();
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
		
		case 'pay-fines':
			wp_lib_page_pay_fines();
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
	
	// Trims client data
	array_filter( $_POST, function( &$raw ){ $value = trim( $value ); } );
	
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
			if ( !wp_lib_verify_nonce( 'Scheduling Item: ' . $item_id ) )
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
			
			// Attempts to validate the relevant nonce depending on wether the item is being returned at a past date, late or on time/early
			if ( $end_date ) {				
				// Checks if nonce is valid
				if ( !wp_lib_verify_nonce( 'Past returning: ' . $item_id ) )
					wp_lib_stop_ajax( false );
				
				// Attempts to converts formatted date to Unix timestamp e.g. 12/08/2013 -> 1386460800
				wp_lib_convert_date( $end_date );
				
				// If date failed to convert, call error
				if ( !$end_date )
					wp_lib_stop_ajax( false, 310 );
				
				// Attempts to return item at a past date
				wp_lib_stop_ajax( wp_lib_return_item( $item_id, $end_date ) );
			} elseif ( isset( $_POST['fine_member'] ) ) {
				// Checks if nonce is valid
				if ( !wp_lib_verify_nonce( 'Resolution of item ' . $item_id . ' for loan '. wp_lib_fetch_loan_id( $item_id ) ) )
					wp_lib_stop_ajax( false );
				
				// If member is to be fined for late return, fine member before returning item, otherwise return item suppressing fine
				if ( $_POST['fine_member'] === 'true' ) {
					wp_lib_stop_ajax( wp_lib_create_fine( $item_id ) );
				} else {
					wp_lib_stop_ajax( wp_lib_return_item( $item_id, false, false ) );
				}
			} else {
				// Checks if nonce is valid
				if ( !wp_lib_verify_nonce( 'Managing Item: ' . $item_id ) )
					wp_lib_stop_ajax( false );
				
				// Attempts to return item
				wp_lib_stop_ajax( wp_lib_return_item( $item_id ) );
			}
		break;
		
		// Debugging action
		case 'run-test-loan':
			// Fetches params from AJAX request
			$item_id = $_POST['item_id'];
			
			// If item ID fails to validate, return false
			if ( !wp_lib_valid_item_id( $item_id ) )
				wp_lib_stop_ajax( false );
				
			$start_date = current_time( 'timestamp' ) - ( 10 * 24 * 60 * 60 );
			$end_date = current_time( 'timestamp' ) - ( 3 * 24 * 60 * 60 );
			
			// If possible creates loan of item starting 10 days ago, due 3 days ago
			$loan_id = wp_lib_schedule_loan( $item_id, '127', $start_date, $end_date );
			
			if ( !is_numeric( $loan_id ) )
				wp_lib_stop_ajax( false );
			
			if ( wp_lib_give_item( $item_id, $loan_id, '127', current_time( 'timestamp' ) - ( 10 * 24 * 60 * 60 ) + 900 ) ) {
				wp_lib_add_notification( "Debugging loan created!" );
				wp_lib_stop_ajax( true );
			} else {
				wp_lib_add_notification( "Failure" );
				wp_lib_stop_ajax( false );
			}
		break;
		
		case 'cancel-fine':
			// Fetches params from AJAX request
			$fine_id = $_POST['fine_id'];
			
			// If fine fails to validate, calls error
			if ( !wp_lib_valid_fine_id( $fine_id ) )
				wp_lib_stop_ajax( false );
			
			// Checks if nonce is valid
			if ( !wp_lib_verify_nonce( 'Managing Fine: ' . $fine_id ) )
				wp_lib_stop_ajax( false );
			
			// Attempts to cancel fine, returning success/failure as boolean
			wp_lib_stop_ajax( wp_lib_cancel_fine( $fine_id ) );
		break;
		
		case 'pay-fine':
			// Fetches member ID and fine amount
			$member_id = $_POST['member_id'];
			$fine_payment = floatval( $_POST['fine_payment'] );
			
			// If member fails to validate, calls error
			if ( !wp_lib_valid_member_id( $member_id ) )
				wp_lib_stop_ajax( false );
			
			// Fetches member's current amount owed
			$owed = wp_lib_fetch_member_owed( $member_id );
			
			// If fine payment is negative or failed to validate (resulting in 0), call error
			if ( $fine_payment <= 0 )
				wp_lib_stop_ajax( false, 320 );
			// If proposed amount is greater than the amount that needs to be paid, call error
			elseif ( $fine_payment > $owed )
				wp_lib_stop_ajax( false, 321 );
			
			// Subtracts proposed amount from amount owed by member
			$owed = $owed - $fine_payment;
			
			// Updates member's amount owed
			update_post_meta( $member_id, 'wp_lib_owed', $owed );
			
			// Sets up notification for successful fine reduction
			$notification = wp_lib_format_money( $fine_payment ) . ' in fines has been paid by ' . get_the_title( $member_id ) . '.';
			
			// If money is still owed by the member, inform librarian
			if ( $owed != 0 )
				$notification .= ' ' . wp_lib_format_money( $owed ) . ' is still owed.';
			
			// Informs user of successful fine payment
			wp_lib_add_notification( $notification );
			
			wp_lib_stop_ajax( true );
		break;
		
		case 'delete-object':
			// Fetches library object ID
			$post_id = $_POST['post_id'];
			
			// Fetches object type and capitalises first letter
			$object_type = ucwords( wp_lib_get_object_type( $post_id ) );
			
			// Validates ID of Library object
			if ( !$object_type )
				wp_lib_stop_ajax( false );
			
			// Checks if nonce is valid
			if ( !wp_lib_verify_nonce( 'Deleting object: ' . $post_id ) )
				wp_lib_stop_ajax( false );
			
			// Fetches all objects connected to current object
			$connected_objects = wp_lib_fetch_dependant_objects( $post_id );
			
			// Counts objects to be deleted
			$object_count = count( $connected_objects );
			
			// Adds current object
			$connected_objects[] = array( $post_id );
			
			// Iterates over objects, checking for any items currently on loan
			// If an item is on loan then it is physically outside the Library and should not be deleted from Library records
			foreach ( $connected_objects as $object ) {
				switch( $object[1] ) {
						// Checks if item is on loan
						case 'wp_lib_items':
							if ( wp_lib_on_loan( $object[0] ) )
								wp_lib_stop_ajax( false, 205 );
						break;
						
						// Checks if loan is open, meaning item is outside the library
						case 'wp_lib_loans':
							if ( get_post_meta( $object[0], 'wp_lib_status', true ) == 1 )
								wp_lib_stop_ajax( false, 205 );
						break;
				}
			}
			
			// Adds objects authorised for deletion to user session
			wp_lib_start_session();
			$_SESSION['deletion_allowed'] = $connected_objects;
			
			// Iterates over objects, deleting them
			foreach( $connected_objects as $object ) {
				wp_delete_post( $object[0], true );
			}
			
			// Clears session
			$_SESSION['deletion_allowed'];
			
			// If connected objects existed, inform user of how many were deleted, otherwise just inform user object was deleted
			if ( $object_count != 0 ) 
				wp_lib_add_notification( wp_lib_plural( $object_count, $object_type . ' and \v connected object\p deleted' ) );
			else
				wp_lib_add_notification( $object_type . ' deleted' );
			
			// Finish 
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
		
		default:
			wp_lib_stop_ajax( false, 500 );
		break;
	}
});

	/* -- Dashboard Parts -- */
	/* AJAX requests for parts of pages or specific information */

add_action( 'wp_ajax_wp_lib_api', function() {
	// If user does not have a proper Library role, disallows access
	if ( !wp_lib_is_librarian() )
		wp_lib_stop_ajax( false, 112 );
	
	// Trims client data
	array_filter( $_POST, function( &$raw ){ $value = trim( $value ); } );
	
	// Performs action based on request
	switch( $_POST['api_request'] ) {
		case 'member-metabox':
			// Fetches member ID from AJAX request
			$member_id = $_POST['member_id'];
			
			// Checks if member ID is valid
			if ( !wp_lib_valid_member_id( $member_id ) )
				wp_lib_stop_ajax( false );
			
			// Fetches member meta
			$meta = get_post_meta( $member_id );
			
			// Sets up header's meta fields
			$meta_fields = array(
				array( 'Name', get_the_title( $member_id ) ),
				array( 'Email', $meta['wp_lib_member_email'][0] ),
				array( 'On Loan', wp_lib_prep_members_items_out( $member_id ) ),
				array( 'Owed', wp_lib_format_money( wp_lib_fetch_member_owed( $member_id ) ) )
			);
			
			// Finalises and returns management header
			wp_lib_stop_ajax( array(
				array(
					'type'		=> 'metabox',
					'title'		=> 'Member Details',
					'classes'	=> 'member-man',
					'fields'	=> $meta_fields
				)
			));
		break;
		
		case 'scan-barcode':
			// Fetches barcode
			$barcode = $_POST['code'];
			
			// Attempts to sanitize barcode as an ISBN
			$isbn = wp_lib_sanitize_isbn( $barcode );
			
			// If sanitization fails, assumes given value is a barcode
			if ( $isbn == '' ) {
				// If barcode is zero, invalid barcode was given
				if ( !ctype_digit( $barcode ) )
				wp_lib_stop_ajax( false, 318 );
				
				$meta_query = array(
					'key'	=> 'wp_lib_item_barcode',
					'value'	=> $barcode,
					'compare'	=> 'IN'
				);
			} else {
				$meta_query = array(
					'key'	=> 'wp_lib_item_isbn',
					'value'	=> $isbn,
					'compare'	=> 'IN'
				);
			}
				
			// Sets up meta query arguments
			$args = array(
				'post_type'	=> 'wp_lib_items',
				'post_status'	=> 'publish',
				'meta_query'	=> array(
					$meta_query
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
				wp_lib_stop_ajax( get_the_ID() );
				
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
			wp_lib_stop_ajax( false, 504 );
		break;
	}
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
	if ( is_numeric ( $error_code ) )
		wp_lib_error( $error_code, false, $params );
	
	// If an output has been specified, render
	if ( $output !== '' )
		echo json_encode( $output );
	
	// Closes PHP session
	session_write_close();
	
	// Kills execution
	die();
}

// Encodes given parameters as an array for client-side JavaScript to render as HTML elements
function wp_lib_send_page( $page_title, $tab_title, $header = false, $form = false, $table = false, $page_scripts = false ) {
	// Creates buffer to be encoded and adds parameters
	$buffer = array(
		'pageTitle'	=> $page_title,
		'title'	=> $tab_title
	);
	
	// Iterates over content fields, adding them to the content buffer if they were specified
	foreach ( ['header','form','table'] as $field ) {
		if ( isset( $$field ) )
			$buffer['content'][$field] = $$field;
	}
	
	// Checks if no content has been specified
	if ( !is_array( $buffer['content'] ) )
		wp_lib_stop_ajax( false, 501 );
	
	// If any scripts are needed by the page, adds their URLs
	if ( is_array( $page_scripts ) ) {
		$buffer['scripts'] = $page_scripts;
	}
	
	// Sends the page to be encoded
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
			'link'	=> 'dash-page',
			'value'	=> 'view-items'
		),
		array(
			'title'	=> 'Manage Members',
			'icon'	=> 'default',
			'link'	=> 'post-type',
			'pType'	=> 'wp_lib_members'
		),
		array(
			'title'	=> 'Manage Loans',
			'icon'	=> 'default',
			'link'	=> 'post-type',
			'pType'	=> 'wp_lib_loans'
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
			'url'	=> 'https://github.com/kittsville/WP-Librarian/wiki'
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

// Displays list of all current items in the library
function wp_lib_page_view_items() {
	$page_title = 'Library Items';
	
	$tab_title = 'All Library Items';
	
	// Initialises header and form
	$header = array();
	$form = array();
	
	// Prepares query parameters to lookup all Library items
	$args = array(
		'post_type' 	=> 'wp_lib_items',
		'post_status'	=> 'publish'
	);
	
	// Queries database for all valid library items
	$query = NEW WP_Query( $args );
	
	// Checks if any items were returned
	if ( $query->have_posts() ){
		$header[] = array(
			'type'		=> 'paras',
			'content'	=> array('Select an item to manage it.')
		);
		
		// Iterates through items
		while ( $query->have_posts() ) {
			$query->the_post();
			
			// Fetches item ID
			$item_id = get_the_ID();
			
			// Sets up basic item parameters
			$item = array(
				'title'	=> get_the_title( $item_id ),
				'link'	=> get_permalink( $item_id )
			);
			
			// If item has a cover image, fetch url and add to item array
			if ( has_post_thumbnail() )
				$item['cover'] = wp_get_attachment_image_src( get_post_thumbnail_id( $item_id ), array( 300, 160 ) );
			else
				$item['cover'] = false;
			
			// Fetches all authors of item
			$authors = get_the_terms( $item_id, 'wp_lib_author' );
			
			// If result contains authors
			if ( $authors && !is_wp_error( $authors ) ) {
				// Iterates over authors, adding their term names to the item's author meta
				foreach ( $authors as $author ) {
					$item['authors'][] = $author->name;
				}
			} else {
				$item['authors'] = false;
			}
			
			// Fetches various item details
			$item['status'] = wp_lib_prep_item_status( $item_id, true, true );
			$item['item_id'] = $item_id;
			$item['view'] = get_permalink();
			
			// If item is on loan, fetches if item is late
			if ( wp_lib_on_loan( $item_id ) ) {
				$item['late'] = wp_lib_item_late( wp_lib_fetch_loan_id( $item_id ) );
			}
			
			// Adds prepared item to array of all items
			$items[] = $item;
		}
		// Creates element that will hold all items
		$table[] = array(
			'type'		=> 'item-list',
			'data'		=> $items,
			'records'	=> 'items'
		);
	} else {
		$table[] = array(
			'type'		=> 'paras',
			'content'	=> array('No items found.')
		);
	}
	
	wp_lib_send_page( $page_title, $tab_title, $header, $form, $table );
}

// Displays an item's information and loan history
function wp_lib_page_manage_item() {
	// Fetches item ID from AJAX request
	$item_id = $_POST['item_id'];
	
	// Checks if item ID is valid
	wp_lib_check_item_id( $item_id );
	
	// Prepares the management header
	$header = wp_lib_prep_item_meta_box( $item_id );
	
	// Adds page nonce
	$form[] = wp_lib_prep_nonce( 'Managing Item: ' . $item_id );
	
	// Adds item ID to form
	$form[] = array(
		'type'	=> 'hidden',
		'name'	=> 'item_id',
		'value'	=> $item_id
	);
	
	// If debugging is enabled, add test loan creation button to every loan's page
	if ( WP_LIB_DEBUG_MODE ) {
		$form[] = array(
			'type'	=> 'button',
			'link'	=> 'action',
			'value'	=> 'run-test-loan',
			'html'	=> 'Create Debug Entry'
		);
	}
	
	// Fetches if item is currently on loan
	$on_loan = wp_lib_on_loan( $item_id );
	
	if ( $on_loan ) {
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
				'value'	=> 'resolve-loan'
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
		
		// Adds dropdown menu using members options created above
		$form[] = array(
			'type'			=> 'select',
			'options'		=> $options,
			'optionClass'	=> 'member-choice-option',
			'classes'		=> 'member-select',
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
	
	// Only show item deletion button if item isn't on loan
	if ( !$on_loan ) {
		$form[] = array(
			'type'	=> 'button',
			'link'	=> 'page',
			'value'	=> 'object-deletion',
			'html'	=> 'Delete'
		);
	}
	
	// Fetches list of loans of item
	$table = wp_lib_prep_loans_table( $item_id );
	
	// Lists additional scripts needed for Dash page
	$scripts = array( 'admin-dashboard-manage-item' );
	
	// Encodes page as an array to be rendered client-side
	wp_lib_send_page( 'Managing: ' . get_the_title( $item_id ), 'Managing Item #' . $item_id, $header, $form, $table, $scripts );
}

// Displays member's details and loan history
function wp_lib_page_manage_member() {
	// Fetches member ID from AJAX request
	$member_id = $_POST['member_id'];
	
	// Checks ID
	wp_lib_check_member_id( $member_id );
	
	// Renders management header
	$header = wp_lib_prep_member_meta_box( $member_id );
	
	// Adds nonce to form
	$form[] = wp_lib_prep_nonce( 'Managing Member ' . $member_id );
	
	// Adds member ID to form
	$form[] = array(
		'type'	=> 'hidden',
		'name'	=> 'member_id',
		'value'	=> $member_id
	);
	
	// Fetches amount owed by member to Library
	$owed = wp_lib_fetch_member_owed( $member_id );
	
	// If money is owed by the member
	if ( $owed > 0 ) {
		$form[] = array(
			'type'	=> 'button',
			'link'	=> 'page',
			'value'	=> 'pay-fines',
			'html'	=> 'Pay Fines'
		);
	}
	
	// Button to edit member
	$form[] = array(
		'type'	=> 'button',
		'link'	=> 'edit',
		'html'	=> 'Edit',
	);
	
	// Button to delete member
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
			
			// If loan incurred fine, change loan status to include a link to manage said fine
			if ( $meta['wp_lib_status'][0] == 4 ) {
				$loan_status = wp_lib_prep_dash_hyperlink( wp_lib_format_loan_status( $meta['wp_lib_status'][0] ), wp_lib_prep_manage_fine_params( $meta['wp_lib_fine'][0] ) );
			} else {
				$loan_status = wp_lib_format_loan_status( $meta['wp_lib_status'][0] );
			}
			
			$loans[] = array(
				'loan'		=> wp_lib_manage_loan_dash_hyperlink( $loan_id ),
				'item'		=> wp_lib_manage_item_dash_hyperlink( $item_id ),
				'status'	=> $loan_status,
				'loaned'	=> wp_lib_format_unix_timestamp( $meta['wp_lib_start_date'][0] ),
				'expected'	=> wp_lib_format_unix_timestamp( $meta['wp_lib_end_date'][0] ),
				'returned'	=> wp_lib_format_unix_timestamp( $meta['wp_lib_returned_date'][0] )
			);
		}
		
		// Adds loans (rows) to table
		$table[] = array(
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
		$table[] = array(
			'type'		=> 'paras',
			'content'	=> array( 'No loans to display' )
		);
	}
	
	wp_lib_send_page( 'Managing: ' . get_the_title( $member_id ), 'Managing Member #' . $member_id, $header, $form, $table );
}

// Displays lack of loan management page
function wp_lib_page_manage_loan() {
	// Fetches loan ID from AJAX request
	$loan_id = $_POST['loan_id'];
	
	// Checks if loan ID is valid
	wp_lib_check_loan_id( $loan_id );
	
	// Renders header with useful loan information
	$header = wp_lib_prep_loan_meta_box( $loan_id );
	
	// If loan is not open, displays delete button
	if ( get_post_meta( $loan_id, 'wp_lib_status', true ) != 1 ) {
		$form = array(
			array(
				'type'	=> 'hidden',
				'name'	=> 'post_id', // Saves time on object-deletion page. No need using member_id as there are no other buttons on the page
				'value'	=> $loan_id
			),
			array(
				'type'	=> 'button',
				'link'	=> 'page',
				'value'	=> 'object-deletion',
				'html'	=> 'Delete'
			)
		);
	}
	
	wp_lib_send_page( 'Managing: Loan #' . $loan_id, 'Managing Loan #' . $loan_id, $header, $form );
}

// Displays fine details and provides options to modify the fine
function wp_lib_page_manage_fine() {
	// Fetches fine ID from AJAX request
	$fine_id = $_POST['fine_id'];
	
	// Checks if fine ID is valid
	wp_lib_check_fine_id( $fine_id );
	
	$header = wp_lib_prep_fine_meta_box( $fine_id );
	
	// Fetches fine status
	$fine_status = get_post_meta( $fine_id, 'wp_lib_status', true );
	
	// Multiple columns to form
	$form = array(
		wp_lib_prep_nonce( 'Managing Fine: ' . $fine_id ),
		// Adds fine ID to form
		array(
		'type'	=> 'hidden',
		'name'	=> 'fine_id',
		'value'	=> $fine_id
		),
		array(
			'type'		=> 'paras',
			'content'	=> array( 'Fines can be paid from the member\'s management page.' )
		)
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
	
	// Adds deletion option
	$form[] = array(
		'type'	=> 'button',
		'link'	=> 'page',
		'value'	=> 'object-deletion',
		'html'	=> 'Delete'
	);
	
	// Sends entire page to be encoded in JSON
	wp_lib_send_page( 'Managing: Fine #' . $fine_id, 'Managing Fine #' . $fine_id, $header, $form );
}

// Page for looking up an item by its barcode
function wp_lib_page_scan_item() {
	// Enqueues barcode page script
	$scripts[] = 'admin-barcode-scanner';
	
	$form = array(
		wp_lib_prep_nonce( 'Lookup Item Barcode' ),
		array(
			'type'		=> 'paras',
			'content'	=> array( 'Once the barcode is scanned the item will be retried automatically' )
		),
		array(
			'type'		=> 'input',
			'id'		=> 'barcode-input',
			'name'		=> 'item_barcode',
			'attr'		=> array(
				'autofocus'	=> true,
				'type'		=> 'text'
			)
		),
		array(
			'type'	=> 'button',
			'link'	=> 'none',
			'id'	=> 'barcode-submit',
			'value'	=> 'scan-barcode',
			'html'	=> 'Scan'
		)
	);
	
	// Sets item title
	$title = 'Scan Item Barcode';
	
	// Sends form to client to be rendered
	wp_lib_send_page( $title, $title, false, $form, false, $scripts );
}

// Allows user to schedule a loan to happen in the future, to be fulfilled when the time comes
function wp_lib_page_scheduling_page() {
	// Fetches item ID from AJAX request
	$item_id = $_POST['item_id'];
	
	// Checks if item ID is valid
	wp_lib_check_item_id( $item_id );
	
	// Displays the management header
	$header = wp_lib_prep_item_meta_box( $item_id );
	
	$member_options = wp_lib_prep_member_options();
	
	// Formats placeholder loan start date (current date)
	$start_date = Date( 'Y-m-d', current_time( 'timestamp' ) );
	
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
					'name'			=> 'member_id',
					'options'		=> $member_options,
					'optionClass'	=> 'member-select-option',
					'classes'		=> 'member-select'
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
	
	// Fetches list of loans of item
	$table = wp_lib_prep_loans_table( $item_id );
	
	// Lists additional scripts needed for Dash page
	$scripts = array( 'admin-dashboard-manage-item' );
	
	wp_lib_send_page( 'Scheduling loan of ' . get_the_title( $item_id ), 'Scheduling loan of #' . $item_id, $header, $form, $table, $scripts );
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
	$header = wp_lib_prep_item_meta_box( $item_id );
	
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
					'value'	=> Date( 'Y-m-d', current_time( 'timestamp' ) )
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
	$header = wp_lib_prep_item_meta_box( $item_id );
	
	// Fetches current date
	$date = current_time( 'timestamp' );
	
	// Useful variables:
	// Formatted string of item lateness
	$days_late = wp_lib_prep_item_due( $item_id, $date, array( 'late' => '\d day\p' ) );
	// Item's title
	$title = get_the_title( $item_id );
	// Librarian set charge for each day an item is late
	$fine_per_day = get_option( 'wp_lib_fine_daily' );
	// Days item is late
	$late = -wp_lib_cherry_pie( $loan_id, $date );
	// Total fine member member is facing, if charged
	$fine = wp_lib_format_money( $fine_per_day * $late );
	// Fine per day formatted
	$fine_per_day_formatted = wp_lib_format_money( $fine_per_day );
	// Member's name
	$member_name = get_the_title( get_post_meta( $item_id, 'wp_lib_member', true ) );
	
	$form = array(
		wp_lib_prep_nonce( 'Resolution of item ' . $item_id . ' for loan '. $loan_id ),
		array(
			'type'	=> 'hidden',
			'name'	=> 'item_id',
			'value'	=> $item_id
		),
		array(
			'type'		=> 'paras',
			'content'	=> array( "{$title} is late by {$days_late}. If fined, {$member_name} would incur a fine of {$fine} ({$fine_per_day_formatted} per day x {$days_late})." )
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
		),
		array(
			'type'	=> 'button',
			'link'	=> 'page',
			'value'	=> 'manage-item',
			'html'	=> 'Cancel'
		)
	);
	
	// Fetches list of loans of item
	$table = wp_lib_prep_loans_table( $item_id );
	
	$page_title = 'Resolving Late Item: ' . $title;
	
	$tab_title = 'Resolving Item #' . $item_id;
	
	wp_lib_send_page( $page_title, $tab_title, $header, $form, $table );
}

// Allows Librarian to reduce money owed by a member for late returns
function wp_lib_page_pay_fines() {
	// Fetches member ID from AJAX request
	$member_id = $_POST['member_id'];
	
	// Checks ID
	wp_lib_check_member_id( $member_id );
	
	// Checks that there is actually money owed, stopping page load on failure
	if ( wp_lib_fetch_member_owed( $member_id ) == 0 )
		wp_lib_stop_ajax( false, 206 );
	
	// Renders management header
	$header = wp_lib_prep_member_meta_box( $member_id );
	
	$form = array(
		array(
			'type'		=> 'paras',
			'content'	=> array("Enter an amount to reduce the member's total owed to the Library")
		),
		array(
			'type'	=> 'hidden',
			'name'	=> 'member_id',
			'value'	=> $member_id
		),
		array(
			'type'			=> 'input',
			'name'			=> 'fine_payment',
			'attr'			=> array(
				'type'			=> 'number',
				'placeholder'	=> wp_lib_format_money( 0, false ),
				'step'			=> '0.05'
			)
		),
		array(
			'type'	=> 'button',
			'link'	=> 'action',
			'value'	=> 'pay-fine',
			'html'	=> 'Pay'
		)
	);
	
	wp_lib_send_page( 'Managing: ' . get_the_title( $member_id ), 'Managing Member #' . $member_id, $header, $form );
}

// Confirmation page to make sure the Librarian knows what deleting the item/loan/fine/member will do
// This page is not visited if the option wp_lib_mass_deletion is set to true
function wp_lib_page_confirm_deletion() {
	// If page is being visited from item/member/etc. management pages, fetch post ID from relevant POST field
	if ( !isset( $_POST['post_id'] ) ) {
		foreach( ['item','member','loan','fine'] as $key ) {
			$key .= '_id';
			// If field exists, fetch object ID from field
			if ( isset( $_POST[$key] ) ) {
				$post_id = $_POST[$key];
				break;
			}
		}
	} else {
		$post_id = $_POST['post_id'];
	}
	
	// Fetches library object type
	$object_type = wp_lib_get_object_type( $post_id );
	
	// If post ID doesn't belong to a valid library object, don't load page
	if ( !$object_type )
		wp_lib_page_dashboard();
	
	// Renders relevant page title and information based on object type
	switch( $object_type ) {
		case 'item':
			// If item is on loan, call error
			if ( wp_lib_on_loan( $post_id ) )
				wp_lib_stop_ajax( false, 205 );
			
			// Renders management header, displaying useful information about the item
			$header = wp_lib_prep_item_meta_box( $post_id );
			
			// Sets titles of Dash page and browser tab
			$page_title = 'Deleting: ' . get_the_title( $post_id );
			$tab_title = 'Deleting Item #' . $post_id;
			
			// Sets object type for use in button labels
			$object_type = 'Item';
			
			// Informs user of implications of deletion
			$form[] = array(
				'type'		=> 'paras',
				'content'	=> array(
					'Deleting items is a permanent action. Any loans or fines dependant on this member will be deleted as well.',
					'If you want to delete items in bulk, without this prompt, allow bulk deletion via WP-Librarian\'s settings'
				)
			);
		break;
		
		case 'loan':
			// If loan is open (item is outside Library), call error
			if ( get_post_meta( $post_id, 'wp_lib_status', true ) == 1 )
				wp_lib_stop_ajax( false, 205 );
			
			// Renders management header, displaying useful information about the loan
			$header = wp_lib_prep_loan_meta_box( $post_id );
			
			// Sets titles of Dash page and browser tab
			$page_title = 'Deleting: Loan #' . $post_id;
			$tab_title = 'Deleting Loan #' . $post_id;
			
			// Informs user of implications of deletion
			$form[] = array(
				'type'		=> 'paras',
				'content'	=> array(
					'Deleting a loan is a permanent action. Any fines dependant on this loan will also be deleted.',
					'If you want to delete loans in bulk, without this prompt, allow bulk deletion via WP-Librarian\'s settings'
				)
			);
		break;
		
		case 'fine':
			// Renders management header, displaying useful information about the fine
			$header = wp_lib_prep_fine_meta_box( $post_id );
			
			// Sets titles of Dash page and browser tab
			$page_title = 'Deleting: Fine #' . $post_id;
			$tab_title = 'Deleting Fine #' . $post_id;
			
			// Informs user of implications of deletion
			$form[] = array(
				'type'		=> 'paras',
				'content'	=> array(
					'Deleting a fine is a permanent action and will result in the deletion of any loan dependant on this fine',
					'To remove any money owed by the member because of this fine, cancel the fine first',
					'If you want to delete fines in bulk, without this prompt, allow bulk deletion via WP-Librarian\'s settings'
				)
			);
		break;
		
		case 'member':
			// Renders management header, displaying useful information about the member
			$header = wp_lib_prep_member_meta_box( $post_id );
			
			// Sets dash page title and tab title
			$page_title = 'Deleting: ' . get_the_title( $post_id );
			$tab_title = 'Deleting Member #' . $post_id;
			
			// Informs user of implications of deletion
			$form[] = array(
				'type'		=> 'paras',
				'content'	=> array(
					'Deleting a member is a permanent action. You can choose to also delete all loans/fines dependant on this member',
					'If you want to delete members in bulk, without this prompt, allow bulk deletion via WP-Librarian\'s settings'
				)
			);
		break;
	}
	
	// Adds page nonce, object ID and relevant buttons to form
	$form = array_merge(
		$form,
		array(
			wp_lib_prep_nonce( 'Deleting object: ' . $post_id ),
			array(
				'type'	=> 'hidden',
				'name'	=> 'post_id',
				'value'	=> $post_id
			),
			array(
				'type'	=> 'button',
				'link'	=> 'action',
				'value'	=> 'delete-object',
				'html'	=> 'Delete ' . ucwords( $object_type )
			),
			array(
				'type'	=> 'button',
				'link'	=> 'page',
				'html'	=> 'Cancel'
			)
		)
	);
	
	// Looks for all objects connected to the current one (e.g. loans by a member, or fines as a result of a loan)
	$connected_objects = wp_lib_fetch_dependant_objects( $post_id );
	
	if ( $connected_objects ) {
		$form[] = array(
			'type'	=> 'header',
			'size'	=> 4,
			'html'	=> wp_lib_plural( count( $connected_objects ), 'Dependant object\p:' )
		);
		
		// Iterates over connected objects, creating table rows for each object
		foreach ( $connected_objects as $connected_object ) {
			// Adds table row with post ID and link to manage loan/fine
			switch( $connected_object[1] ) {
				case 'wp_lib_loans':
					$objects[] = array(
						'id'	=> wp_lib_manage_loan_dash_hyperlink( $connected_object[0] ),
						'type'	=> 'Loan'
					);
				break;
				
				case 'wp_lib_fines':
					$objects[] = array(
						'id'	=> wp_lib_manage_fine_dash_hyperlink( $connected_object[0] ),
						'type'	=> 'Fine'
					);
				break;
			}
		}
		
		// Creates table using objects
		$table[] = array(
			'type'		=> 'dtable',
			'id'		=> 'connected-objects',
			'headers'	=> array(
				'ID',
				'Type'
			),
			'data'		=> $objects,
			'labels'	=> array(
				'records'	=> 'dependant objects'
			)
		);
	} else {
		$table[] = array(
			'type'		=> 'paras',
			'content'	=> array( 'No other objects in the Library are dependant on this object' )
		);
	}
	
	wp_lib_send_page( $page_title, $tab_title, $header, $form, $table );
}
?>