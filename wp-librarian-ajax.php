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
	
	/* Library Actions - Loaning/returning items etc. */
	add_action( 'wp_ajax_wp_lib_loan_item', 'wp_lib_do_loan_item' );
	add_action( 'wp_ajax_wp_lib_schedule_loan', 'wp_lib_do_schedule_loan' );
	add_action( 'wp_ajax_wp_lib_return_item', 'wp_lib_do_return_item' );
	add_action( 'wp_ajax_wp_lib_fine_member', 'wp_lib_do_fine_member' );
	add_action( 'wp_ajax_wp_lib_modify_fine', 'wp_lib_do_modify_fine' );
	
	/* Misc */
	add_action( 'wp_ajax_wp_lib_deletion_failed', 'wp_lib_page_deletion_failed' );
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
function wp_lib_stop_ajax( $boolean = '', $error_code = false, $params = false ) {
	// If specified, calls error with code provided
	if ( $error_code )
		wp_lib_error( $error_code, false, $params );

	// Returns boolean result of success
	if ( is_bool( $boolean ) )
		echo json_encode( $boolean );
	
	// Closes PHP session
	session_write_close();
	
	// Kills execution
	die();
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

	/* Pages */
	/* Renders then returns Dashboard pages */

// Displays Library Dashboard
function wp_lib_page_dashboard() {
	
	wp_lib_dashboard();
	
	wp_lib_stop_ajax();
}

// Informs Librarian that item cannot be deleted because it is currently on loan
function wp_lib_page_deletion_failed() {
	// Fetches item ID from AJAX request
	$item_id = $_POST['item_id'];
	
	// Checks if item ID is valid
	wp_lib_check_item_id( $item_id );
	
	// Fetches item's title
	$title = get_the_title( $item_id );

	// Calls error to inform user of failed item deletion
	wp_lib_error( 113, false, $title );
	
	// Redirects user to dashboard
	wp_lib_page_dashboard();
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
	
	// Displays the management header
	wp_lib_render_item_management_header( $item_id );
	?>
	<form id="library-form">
		<input type="hidden" name="item_id" value="<?= $item_id; ?>" />
		<?php
		// If item is current on loan
		if ( $loan_id ) {
			// If item is also late
			if ( $late ){
				?>
				<button class="button button-primary button-large dash-page" name="dash_page" value="resolve">Resolve</button>
				<?php
			// If item is not late
			} else {
				?>
				<button class="button button-primary button-large dash-action" name="dash_action" value="return-item">Return</button>
				<?php
			}
			// Regardless of if item is late, user is allowed to return item at a previous date
			?>
			<button class="button button-primary button-large dash-page" name="dash_page" value="return-past">Return at a Past Date</button>
			<?php
		}
		// If item is not in loan and is allowed to be loaned
		elseif ( $loanable ) {
			$members = get_terms( 'wp_lib_member', 'hide_empty=0' );
			?>
			<h4>Loan item:</h4>
			<select name='member_id' id='member_id'>
				<option class='member-option' value=''>None</option>
				<?php
				foreach ($members as $member) {
					echo "<option class=\"member-option\" value=\"{$member->term_id}\">{$member->name}</option>";
				}
				?>
			</select>
			<select name='loan_length' id='loan_length'>
				<option class='loan-length-option' value=''>Default</option>
				<?php
				// Temporary code to render days to loan for option
				$inc = -3;
				while ( $inc < 12 ) {
					++$inc;
					echo "<option class=\"loan-length-option\" value=\"{$inc}\">{$inc} Days</option>";
				}
				?>
			</select>
			<button class="button button-primary button-large dash-action" name="dash_action" value="loan">Loan Item</button>
			<button class="button button-primary button-large dash-page" name="dash_page" value="scheduling-page">Schedule Future Loan</button>
			<?php
		}
		?>
	</form>
	<?php
	wp_lib_stop_ajax();
}

// Displays member's details and loan history
function wp_lib_page_manage_member() {
	// Fetches member ID from AJAX request
	$member_id = $_POST['member_id'];
	
	// Checks if member ID is valid
	wp_lib_check_member_id( $member_id );
	
	// Renders management header
	wp_lib_render_member_management_header( $member_id );

	echo "<p>Nothing much to see here yet!</p>";
	
	wp_lib_stop_ajax();
}

// Displays fine details and provides options to modify the fine
function wp_lib_page_manage_fine() {
	// Fetches fine ID from AJAX request
	$fine_id = $_POST['fine_id'];
	
	// Checks if fine ID is valid
	wp_lib_check_fine_id( $fine_id );

	wp_lib_render_fine_management_header( $fine_id );
	
	// Fetches fine status
	$fine_status = get_post_meta( $fine_id, 'wp_lib_status', true );
	?>
	<form id="library-form">
		<input type="hidden" name="fine_id" value="<?= $fine_id; ?>" />
		<?php
		// If fine is unpaid, provide options to Pay or Cancel fine
		if ( $fine_status == 1 ) {
			?>
			<p>Marking a fine as paid assumes the money has been collected from the relevant member.</p>
			<button class="button button-primary button-large dash-action" name="dash_action" value="pay-fine">Pay Fine</button>
			<?php
		}
		// If fine is paid, provide options to revert fine to being unpaid
		elseif ( $fine_status == 2 ) {
			?>
			<button class="button button-primary button-large dash-action" name="dash_action" value="revert-fine">Revert to Unpaid</button>
			<?php
		}
		
		// If fine has not been cancelled, display option to cancel fine
		if ( $fine_status != 3 ) {
			?>
			<button class="button button-primary button-large dash-action" name="dash_action" value="cancel-fine">Cancel Fine</button>
			<?php
		}
		?>
	</form>
	<?php
	wp_lib_stop_ajax();
}

// Displays lack of loan management page
function wp_lib_page_manage_loan() {
	// Fetches loan ID from AJAX request
	$loan_id = $_POST['loan_id'];
	
	// Checks if loan ID is valid
	wp_lib_check_loan_id( $loan_id );
	
	// Returns error
	wp_lib_stop_ajax( '', 202);
}

// Page for looking up an item by its barcode
function wp_lib_page_scan_item() {

	$script_url = plugins_url( '/scripts/admin-barcode-scanner.js', __FILE__ );
	?>
	<script>
		jQuery.getScript( <?php echo json_encode( $script_url ); ?> )
		.fail( function( jqxhr, settings, exception ) {
			wp_lib_local_error( "Failed to load JavaScript needed for this page" );
		});
	</script>
	<h2>Scan Item Barcode</h2>
	<p>Once the barcode is scanned the item will be retried automatically</p>
	<form id="library-form">
		<input type="text" id="barcode-input" name="item_barcode" autofocus="autofocus" />
	</form>
	<?php
	wp_lib_stop_ajax();
}

// Allows user to schedule a loan to happen in the future, to be fulfilled when the time comes
function wp_lib_page_scheduling_page() {
	// Fetches item ID from AJAX request
	$item_id = $_POST['item_id'];
	
	// Checks if item ID is valid
	wp_lib_check_item_id( $item_id );
	
	// Displays the management header
	wp_lib_render_item_management_header( $item_id );

	// Fetches list of all Members
	$members = get_terms( 'wp_lib_member', 'hide_empty=0' );
	
	// Formats placeholder loan start date (current date)
	$start_date = Date( 'Y-m-d' );
	
	// Adds default loan length to current date
	$time = current_time( 'timestamp' ) + ( get_option( 'wp_lib_loan_length', 12 ) * 24 * 60 * 60);
	
	// Formats placeholder loan end date (current date + default loan length)
	$end_date = Date( 'Y-m-d', $time );
	?>
	<form id="library-form">
		<input type="hidden" name="item_id" value="<?= $item_id; ?>" />
		<h4>Schedule Loan:</h4>
		<div class="member-select manage-item">
			<label for="member-select">
				<strong>Member:</strong>
			</label>
			<select name='member_id' class='member-select'>
				<option class='member-option' value=''>None</option>
				<?php
				foreach ($members as $member) {
					echo "<option class=\"member-option\" value=\"{$member->term_id}\">{$member->name}</option>";
				}
			   ?>
			</select>
		</div>
		
		<div class="loan-start manage-item">
			<label for="loan-start">
				<strong>Start Date:</strong>
			</label>
			<input type="date" name="loan_start_date" id="loan-start-date" class="loan-date datepicker ll-skin-melon" value="<?= $start_date ?>" />
		</div>
		
		<div class="loan-end manage-item">
			<label for="loan-end">
				<strong>End Date:</strong>
			</label>
			<input type="date" name="loan_end_date" id="loan-end-date" class="loan-date datepicker ll-skin-melon" value="<?= $end_date ?>" />
		</div>
		<button class="button button-primary button-large dash-action" name="dash_action" value="schedule-loan">Schedule Loan</button>
	</form>
	<?php
	wp_lib_stop_ajax();
}

// Displays page for returning an item in the past
function wp_lib_page_return_past() {
	// Fetches item ID from AJAX request
	$item_id = $_POST['item_id'];
	
	// Checks if item ID is valid
	wp_lib_check_item_id( $item_id );

	// Checks if item is on loan
	if ( !wp_lib_on_loan( $item_id ) )
		wp_lib_error( 402, true );
	
	// Renders the management header
	wp_lib_render_item_management_header( $item_id );
	
	// Creates placeholder for return date as current date (formatted)
	$date = Date( 'Y-m-d' );
	?>
	
	<h4>Return item at a past date:</h4>
	<form id="library-form">
		<input type="hidden" name="item_id" value="<?= $item_id; ?>" />
		<div class="loan-end manage-item">
			<label for="loan-end">
				<strong>Date:</strong>
			</label>
			<input type="date" name="loan_end_date" id="loan-end-date" class="loan-date datepicker ll-skin-melon" value="<?= $date ?>" />
		</div>
		<button class="button button-primary button-large dash-action" name="dash_action" value="return">Return Item</button>
	</form>
	<?php
	wp_lib_stop_ajax();
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
		wp_lib_error( 406, true );
	
	// Creates formatted string containing item lateness
	$args = array( 'late' => '\d day\p' );
	$days_late = wp_lib_prep_item_due( $item_id, $date, $args );
	
	// Renders 'Managing: $item' header
	wp_lib_render_item_management_header( $item_id );
	
	// Prepares useful variables
	$title = get_the_title( $item_id );
	$daily_fine = get_option( 'wp_lib_fine_daily' );
	$late = -wp_lib_cherry_pie( $loan_id, false );
	$fine = wp_lib_format_money( $daily_fine * $late );
	$daily_fine = wp_lib_format_money( $daily_fine );
	?>
	<p><?= $title ?> is late by <?= $days_late ?>. If charged, a fine of <?= $fine ?> would be incurred (<?= $daily_fine ?> per day x <?= $days_late?>)</p>
	<form id="library-form">
		<input type="hidden" name="item_id" value="<?= $item_id; ?>" />
		<button class="button button-primary button-large dash-action" name="dash_action" value="return-item-no-fine">Fine</button>
		<button class="button button-primary button-large dash-action" name="dash_action" value="no-fine">Return Without Fine</button>
	</form>
	<?php
	wp_lib_stop_ajax();
}

?>