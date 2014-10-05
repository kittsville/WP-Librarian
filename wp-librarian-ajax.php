<?php
/*
 * WP-LIBRARIAN AJAX
 * Handles all of WP-Librarian's AJAX requests, calls the relevant functions to render pages or modify the Library
 * All functions prefixed 'page' return HTML pages, while all functions prefixed 'do' modify the Library
 * Note that, for simplicity, 'die()' will be referred to here as if it were 'return' within this file
 */

// Ensures only authorised users can access data via AJAX
if ( wp_lib_is_librarian() ) {
	/* Page Requests - Dynamically loaded pages */
	add_action( 'wp_ajax_wp_lib_dashboard', 'wp_lib_page_dashboard' );
	add_action( 'wp_ajax_wp_lib_manage_item', 'wp_lib_page_manage_item' );
	add_action( 'wp_ajax_wp_lib_manage_member', 'wp_lib_page_manage_member' );
	add_action( 'wp_ajax_wp_lib_manage_fine', 'wp_lib_page_manage_fine' );
	add_action( 'wp_ajax_wp_lib_manage_loan', 'wp_lib_page_manage_loan' );
	add_action( 'wp_ajax_wp_lib_scheduling_page', 'wp_lib_page_scheduling_page' );
	add_action( 'wp_ajax_wp_lib_return_past', 'wp_lib_page_return_past' );
	add_action( 'wp_ajax_wp_lib_resolution_page', 'wp_lib_page_resolution_page' );
	add_action( 'wp_ajax_wp_lib_scan_item', 'wp_lib_page_scan_item' );
	add_action( 'wp_ajax_wp_lib_confirm_deletion_page', 'wp_lib_page_confirm_deletion' );
	
	/* Library Actions - Loaning/returning items etc. */
	add_action( 'wp_ajax_wp_lib_loan_item', 'wp_lib_do_loan_item' );
	add_action( 'wp_ajax_wp_lib_schedule_loan', 'wp_lib_do_schedule_loan' );
	add_action( 'wp_ajax_wp_lib_return_item', 'wp_lib_do_return_item' );
	add_action( 'wp_ajax_wp_lib_fine_member', 'wp_lib_do_fine_member' );
	add_action( 'wp_ajax_wp_lib_modify_fine', 'wp_lib_do_modify_fine' );
	add_action( 'wp_ajax_wp_lib_delete_object', 'wp_lib_do_delete_object' );
	
	/* Misc */
	add_action( 'wp_ajax_wp_lib_clean_item', 'wp_lib_do_clean_item' );
	add_action( 'wp_ajax_wp_lib_unknown_action', 'wp_lib_do_unknown_action' );
	add_action( 'wp_ajax_wp_lib_fetch_notifications', 'wp_lib_fetch_notifications' );
	add_action( 'wp_ajax_wp_lib_lookup_barcode', 'wp_lib_fetch_item_by_barcode' );
}

	/* Misc AJAX Functions */
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

// Looks for item with given barcode, returns item ID on success and false on failure
function wp_lib_fetch_item_by_barcode() {
	// Converts barcode to an int
	$barcode = (int)$_POST['code'];

	// If barcode is zero, invalid barcode was given
	if ( $barcode == 0 )
		wp_lib_stop_ajax( false );
		
	// Sets up meta query arguments
	$args = array(
		'post_type'		=> 'wp_lib_items',
		'post_status'	=> 'publish',
		'meta_key'		=> 'wp_lib_item_barcode',
		'meta_query'	=> array(
			array(
				'key'		=> 'wp_lib_item_barcode',
				'value'		=> $barcode,
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
		wp_lib_stop_ajax( false );
	}
}

// Informs user that action requested does not exist
function wp_lib_do_unknown_action() {
	wp_lib_stop_ajax( '', 500, $_POST['given_action'] );
}

	/* Actions */
	/* Prepares data then modifies the library using given instruction */

// Schedules a loan commencing now, then marks the item has having been given to the member
function wp_lib_do_loan_item() {
	// Fetches params from AJAX request
	$item_id = $_POST['item_id'];
	$member_id = $_POST['member_id'];
	$loan_length = $_POST['loan_length'];
	
	// If item or member ID fail to validate, return false (errors are handled by the validation functions)
	if ( !wp_lib_valid_item_id( $item_id ) || !wp_lib_valid_member_id( $member_id ) ) {
		wp_lib_stop_ajax( false );
	}
	
	// Attempts to loan item
	$success = wp_lib_loan_item( $item_id, $member_id, $loan_length );
	
	// Kills execution, returning if loan succeeded
	wp_lib_stop_ajax( $success );
}

// Schedules a future loan
function wp_lib_do_schedule_loan() {
	// Fetches params from AJAX request
	$item_id = $_POST['item_id'];
	$member_id = $_POST['member_id'];
	$start_date = $_POST['start_date'];
	$end_date = $_POST['end_date'];
	
	// If item or member ID fail to validate, return false (errors are handled by the validation functions)
	if ( !wp_lib_valid_item_id( $item_id ) || !wp_lib_valid_member_id( $member_id ) ) {
		wp_lib_stop_ajax( false );
	}		
	
	// Attempts to convert given dates to Unix timestamps
	wp_lib_convert_date( $start_date );
	wp_lib_convert_date( $end_date );
		
	// Checks if dates failed to convert, return false and call error
	if ( !$start_date || !$end_date )
		wp_lib_stop_ajax( false, 312 );
	
	// Passes parameters to scheduling function
	$success = wp_lib_schedule_loan_wrapper( $item_id, $member_id, $start_date, $end_date );
	
	// Returns result (boolean)
	wp_lib_stop_ajax( $success );
}

// Returns an item currently on loan
function wp_lib_do_return_item() {
	// Fetches params from AJAX request
	$item_id = $_POST['item_id'];
	$end_date = $_POST['end_date'];
	
	// If item ID fails to validate, return false
	if ( !wp_lib_valid_item_id( $item_id ) )
		wp_lib_stop_ajax( false );	
	
	// If the date is given (item is not being returned currently)
	if ( $end_date ) {
		// Attempts to converts formatted date to Unix timestamp e.g. 12/08/2013 -> 1386460800
		wp_lib_convert_date( $end_date );
		
		// If date failed to convert
		if ( !$end_date )
			wp_lib_stop_ajax( false, 310 );
	}
	
	// Converts 'no fine' to boolean
	if ( $_POST['no_fine'] === 'true' )
		$no_fine = true;
	else
		$no_fine = false;
	
	// Attempts to return item, returning result
	wp_lib_stop_ajax( wp_lib_return_item( $item_id, $end_date, $no_fine ) );
}

// Charges a member a fine for returning an item late
function wp_lib_do_fine_member() {
	// Fetches params from AJAX request
	$item_id = $_POST['item_id'];
	$end_date = $_POST['end_date'];
	
	// If item ID fails to validate, return false
	if ( !wp_lib_valid_item_id( $item_id ) )
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
}

// Modifies fine by marking fine as paid/unpaid or cancelling fine
function wp_lib_do_modify_fine() {
	// Fetches params from AJAX request
	$fine_id = $_POST['fine_id'];
	$action = $_POST['fine_action'];
	
	// Checks if fine ID is valid
	wp_lib_check_fine_id( $fine_id );
	
	// Modifies fine based off requested action
	switch ( $action ) {
		// Marks fine as paid, returning success/failure
		case 'pay':
			$success = wp_lib_charge_fine( $fine_id );
		break;
		
		// Reverts fine status from paid to unpaid, returning success/failure
		case 'revert':
			$success = wp_lib_revert_fine( $fine_id );
		break;
		
		// Cancels fine, returning success/failure
		case 'cancel':
			$success = wp_lib_cancel_fine( $fine_id );
		break;
		
		default:
			wp_lib_stop_ajax( false, 314 );
		break;
	}
	
	// Returns success/failure as boolean
	wp_lib_stop_ajax( $success );
}

// Deletes Library object and if requested, associated objects (e.g. item and all loans of that item )
function wp_lib_do_delete_object() {
	// Fetches library object ID
	$post_id = $_POST['post_id'];
	
	// Validates ID of Library object
	if ( !wp_lib_get_object_type() )
		wp_lib_stop_ajax( false );
	
	// Deletes post, connected post deletion is handled on the pre_deletion hook 'before_delete_post'
	wp_delete_post( $post_id );
}

	/* Pages */
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
			'link'	=> 'dash-page',
			'value'	=> 'browse-members'
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
	
	// Fetches loan ID and member if item is on loan
	if ( wp_lib_on_loan( $item_id ) ) {
		$loan_id = wp_lib_fetch_loan( $item_id );
		$member = wp_get_post_terms( $item_id, 'wp_lib_member' )[0];
		$late = wp_lib_item_late( $loan_id );
	}
	else
		$loan_id = false;
		$loanable = wp_lib_loanable( $item_id );
	
	// Fetches title
	$title = get_the_title( $item_id );
	
	// If item is late, display error bar
	if ( $late )
		wp_lib_add_notification( "{$title} is late, please resolve this issue" );
	
	// Prepares the management header
	$header = wp_lib_prep_item_management_header( $item_id );
	
	// Adds item ID to form
	$form[] = array(
		'type'	=> 'hidden',
		'name'	=> 'item_id',
		'value'	=> $item_id
	);
	
	// If item is currently on loan
	if ( $loan_id ) {
		// Regardless of lateness, provides link to return item at a past date
		$form[] = array(
			'type'	=> 'button',
			'link'	=> 'page',
			'html'	=> 'Return at a past date',
			'value'	=> 'return-past'
		);
		
		// If item is late
		if ( $late ) {
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
	elseif ( $loanable ) {
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
	
	// Creates titles for page and browser tab
	$page_title = 'Managing: ' . $title;
	$tab_title = 'Managing Item #' . $item_id;
	
	// Encodes page as an array to be rendered client-side
	wp_lib_send_page( $page_title, $tab_title, $header, $form );
}

// Displays member's details and loan history
function wp_lib_page_manage_member() {
	// Fetches member ID from AJAX request
	$member_id = $_POST['member_id'];
	
	// Attempts to fetch member object
	$member = wp_lib_fetch_member( $member_id );
	
	// If member fetching failed, load Dashboard (causing error will have been added to the buffer)
	if ( !$member )
		wp_lib_page_dashboard();
	
	// Renders management header
	$header = wp_lib_prep_member_management_header( $member );
	
	// Renders first part of member management page
	$content[] = array(
		'type'		=> 'paras',
		'content'	=> array( 'Nothing much to see here yet!' )
	);
	
	// Sets up loan history query arguments
	$args = array(
		'post_type' => 'wp_lib_loans',
		'tax_query' => array(
			array(
				'taxonomy'	=> 'wp_lib_member',
				'field'		=> 'term_id',
				'terms'		=> $member->term_id
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
			
			// Gets item ID of loan
			$item_id = $meta['wp_lib_item'][0];
			
			$loans[] = array(
				'loan'		=> array( '#' . get_the_ID(), wp_lib_format_manage_loan( $loan_id ) ),
				'item'		=> array( get_the_title( $item_id ), wp_lib_format_manage_item( $item_id ) ),
				'startDate'	=> wp_lib_format_unix_timestamp( $meta['wp_lib_start_date'][0] ),
				'endDate'	=> wp_lib_format_unix_timestamp( $meta['wp_lib_end_date'][0] )
			);
		}
		
		// Adds loans (rows) to table
		$content[] = array(
			'type'		=> 'dtable',
			'id'		=> 'member-loans',
			'headers'	=> array(
				'Loan',
				'Item',
				'Start Date',
				'End Date'
			),
			'data'		=> $loans
		);
	}

	$page_title = 'Managing: ' . $member->name;
	
	$tab_title = 'Managing Member #' . $member_id;
	
	wp_lib_send_page( $page_title, $tab_title, array_merge( $header, $content ) );
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
	
	// Adds fine ID to form
	$form[] = array(
		'type'	=> 'hidden',
		'name'	=> 'fine_id',
		'value'	=> $fine_id
	);
	
	// If fine is unpaid, allows user to mark a fine as paid
	if ( $fine_status == 1 ) {
		$form[] = array(
			'type'		=> 'paras',
			'content'	=> array( 'Marking a fine as paid assumes the money has been collected from the relevant member.' )
		);
		
		$form[] = array(
			'type'	=> 'button',
			'link'	=> 'action',
			'value'	=> 'pay-fine',
			'html'	=> 'Pay Fine'
		);
	}
	// If fine is paid, allows Librarian to revert fine to being unpaid
	elseif ( $fine_status == 2 ) {
		$form[] = array(
			'type'	=> 'button',
			'link'	=> 'action',
			'value'	=> 'revert-fine',
			'html'	=> 'Revert to Unpaid'
		);
	}
	
	// If fine has not already been cancelled, allows fine to be cancelled
	if ( $fine_status != 3 ) {
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

// Displays lack of loan management page
function wp_lib_page_manage_loan() {
	// Fetches loan ID from AJAX request
	$loan_id = $_POST['loan_id'];
	
	// Checks if loan ID is valid
	wp_lib_check_loan_id( $loan_id );
	
	// Returns error
	wp_lib_stop_ajax( false, 202 );
}

// Page for looking up an item by its barcode
function wp_lib_page_scan_item() {
	// Enqueues barcode page script
	$scripts[] = plugins_url( '/scripts/admin-barcode-scanner.js', __FILE__ );
	
	$form = array(
		array(
			'type'		=> 'paras',
			'content'	=> array( 'Once the barcode is scanned the item will be retried automatically' )
		),
		array(
			'type'		=> 'text',
			'id'		=> 'barcode-input',
			'name'		=> 'item_barcode',
			'autofocus'	=> true
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
		wp_lib_stop_ajax( 402 );
	
	// Renders the management header
	$header = wp_lib_prep_item_management_header( $item_id );
	
	$form = array(
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
					'name'	=> 'loan_end_date',
					'id'	=> 'loan-end-date',
					'value'	=> Date( 'Y-m-d' )
				)
			)
		),
		array(
			'type'	=> 'button',
			'link'	=> 'action',
			'value'	=> 'return',
			'html'	=> 'Return Item'
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
	$loan_id = wp_lib_fetch_loan( $item_id );
	
	// Ensures item is actually late
	if ( !wp_lib_item_late( $loan_id ) )
		wp_lib_stop_ajax( 406 );
	
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
	// Fetches Library object ID
	$post_id = $_POST['obj_id'];
	
	// Switch to determine the object being deleted
	switch ( $_POST['obj_type'] ) {
		case 'item':
			$item_id = $_POST['item_id'];
			
			wp_lib_check_item_id( $item_id );
			
			$header = wp_lib_prep_item_management_header( $item_id );
			
			$page_title = 'Deleting: ' . get_the_title( $item_id );
			
			$tab_title = 'Deleting Item #' . $item_id;
			
			// Passes array to client-side JavaScript to render form
			$form = array(
				array(
					'type'	=> 'hidden',
					'name'	=> 'post_id',
					'value'	=> $item_id
				),
				array(
					'type'		=> 'paras',
					'content'	=> array(
						'Deleting items is a permanent action. You can also delete all loans/fines linked to this item.',
						'If you want to delete items in bulk, without this prompt, allow bulk deletion via WP-Librarian\'s settings'
					)
				),
				array(
					'type'	=> 'button',
					'link'	=> 'page',
					'value'	=> '',
					'html'	=> 'Cancel'
				),
				array(
					'type'	=> 'button',
					'link'	=> 'action',
					'value'	=> 'delete-object-et-al',
					'html'	=> 'Delete connected Loans/Fines and Item'
				),
				array(
					'type'	=> 'button',
					'link'	=> 'action',
					'value'=> 'delete-object',
					'html'	=> 'Delete Item Only'
				)
			);
			
		break;
		
		case 'loan':
			
			$loan_id = $_POST['loan_id'];
			
			wp_lib_check_loan_id( $loan_id );
			
			$header = false;
			
			$page_title = 'Deleting: Loan #' . $loan_id;
			
			$tab_title = 'Deleting Loan #' . $loan_id;
			
			$form = array(
				array(
					'type'	=> 'hidden',
					'name'	=> 'post_id',
					'value'	=> $loan_id
				),
				array(
					'type'		=> 'paras',
					'content'	=> array(
						'Deleting a loan is a permanent action. You can choose to also delete any fine connected to this loan.',
						'If you want to delete loans in bulk, without this prompt, allow bulk deletion via WP-Librarian\'s settings'
					)
				),
				array(
					'type'	=> 'button',
					'link'	=> 'page',
					'value'	=> '',
					'html'	=> 'Cancel'
				),
				array(
					'type'	=> 'button',
					'link'	=> 'action',
					'value'	=> 'delete-object-et-al',
					'html'	=> 'Delete Fine/Loan'
				),
				array(
					'type'	=> 'button',
					'link'	=> 'action',
					'value'	=> 'delete-object',
					'html'	=> 'Delete Loan Only'
				)
			);
		break;
		
		case 'fine':
			$fine_id = $_POST['fine_id'];
			
			wp_lib_check_fine_id( $fine_id );
			
			$header = wp_lib_prep_fine_management_header( $fine_id );
			
			$page_title = 'Deleting: Fine #' . $fine_id;
			
			$tab_title = 'Deleting Fine #' . $fine_id;
			
			$form = array(
				array(
					'type'	=> 'hidden',
					'name'	=> 'post_id',
					'value'	=> $fine_id
				),
				array(
					'type'		=> 'paras',
					'content'	=> array(
						'Deleting a fine is a permanent action, this will change the connected loan to state that no fine was charged.',
						'If you want to delete fines in bulk, without this prompt, allow bulk deletion via WP-Librarian\'s settings'
					)
				),
				array(
					'type'	=> 'button',
					'link'	=> 'page',
					'value'	=> '',
					'html'	=> 'Cancel'
				),
				array(
					'type'	=> 'button',
					'link'	=> 'action',
					'value'	=> 'delete-object-et-al',
					'html'	=> 'Delete Fine'
				)
			);
		break;
		
		case 'member':
			$member_id = $_POST['member_id'];
			
			wp_lib_check_member_id( $member_id );
			
			$member = wp_lib_fetch_member( $member_id );
			
			if ( !$member )
				wp_lib_stop_ajax( false );
			
			$header = wp_lib_prep_member_management_header( $member_id );
			
			$page_title = 'Deleting: ' . $member->name;
			
			$tab_title = 'Deleting Member #' . $member_id;
			
			$form = array(
				array(
					'type'	=> 'hidden',
					'name'	=> 'post_id',
					'value'	=> $member_id
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
					'link'	=> 'page',
					'value'	=> '',
					'html'	=> 'Cancel'
				),
				array(
					'type'	=> 'button',
					'link'	=> 'action',
					'value'	=> 'delete-object-et-al',
					'html'	=> 'Delete connected loans/fines and Member'
				),
				array(
					'type'	=> 'button',
					'link'	=> 'action',
					'value'	=> 'delete-object',
					'html'	=> 'Delete Member Only'
				)
			);
		break;
		
		default:
			wp_lib_stop_ajax( false, 315 );
		break;
	}
	
	wp_lib_send_page( $page_title, $tab_title, $header, $form );
}

?>