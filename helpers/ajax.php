<?php
/*
 * Handles all Dashboard AJAX requests
 */
class WP_LIB_AJAX {
	// Holds data to be returned to the server
	protected $output_buffer = array(
		true,	// Success/failure of request
		array(),// Buffered notifications
		array()	// Content
	);
	
	function __construct() {
		// Ensures object can only be created if an AJAX request is being performed
		if ( !defined('DOING_AJAX') || !DOING_AJAX )
			wp_lib_error( 116 );
		
		if ( !wp_lib_is_librarian() )
			wp_lib_error( 112 );
	}
	
	/*
	 * Adds notification to the notification/error buffer
	 * Typically you should use stopAJAX() as this also handles stopping execution
	 * @param string	$notification	Explanation of error that has occurred or modification to the library
	 * @param int		$error_code		OPTIONAL error code, leave if not an error. Set as 1 if it's an error but not one with an error code
	 */
	public function addNotification( $notification, $error_code = 0 ) {
		$this->output_buffer[1][] = array( $error_code, $notification );
	}
	
	/*
	 * Verifies WP nonce of AJAX request, stopping request with error on failure
	 * @param string $action Circumstance of page that generated the request
	 */
	protected function checkNonce( $action ) {
		if ( !isset( $_POST['wp_lib_ajax_nonce'] ) || wp_verify_nonce( $_POST['wp_lib_ajax_nonce'], $action ) !== 1 )
			$this->stopAjax( 503 );
	}
	
	/*
	 * Fetches a parameter from POST data, handling failure
	 * This function does not perform any data sanitization
	 * @param string $post_key Name of POST variable e.g 'item_id'
	 * @param string $post_key Readable variable name used in error messages e.g. 'Item ID'
	 */
	protected function getPostParam( $post_key, $param_name ) {
		// If parameter doesn't exist, call error
		if ( !isset( $_POST[$post_key] ) )
			$this->stopAjax( 314, $param_name );
		else
			return $_POST[$post_key];
	}
	
	/*
	 * Fetches Item/Member/Loan/Fine ID from POST data and validates
	 * @param	string		$object		Name of object being fetched e.g. Item
	 * @param	string		$post_type	Name of post type e.g. wp_lib_items
	 * @param	string		$var_name	Name of POST variable
	 * @return	int|bool	Item ID on success, false on failure
	 */
	protected function getLibraryObjectId( $object, $post_type, $var_name ) {
		if ( !isset( $_POST[$var_name] ) ) {
			wp_lib_error( 300, $object );
			return false;
		} else {
			$object_id = (int) $_POST[$var_name];
			
			if ( get_post_type( $object_id ) === $post_type ) {
				return $object_id;
			} else {
				wp_lib_error( 303, $object );
				return false;
			}
		}
	}
	
/*
	 * Fetches Item ID from URL parameters and validates, handling failure
	 * @return int Item ID, if valid
	 */
	protected function getItemId() {
		$item_id = $this->getLibraryObjectId( 'Item', 'wp_lib_items', 'item_id' );
		
		// If Item ID is invalid, stop page load
		// Otherwise return Item ID
		if ( $item_id === false )
			$this->stopAjax();
		else
			return $item_id;
	}
	
	/*
	 * Fetches Member ID from URL parameters and validates, handling failure
	 * @return int Member ID, if valid
	 */
	protected function getMemberId() {
		$member_id = $this->getLibraryObjectId( 'Member', 'wp_lib_members', 'member_id' );
		
		// If Member ID is invalid, stop page load
		// Otherwise return Member ID
		if ( $member_id === false )
			$this->stopAjax();
		else
			return $member_id;
	}
	
	/*
	 * Fetches Loan ID from URL parameters and validates, handling failure
	 * @return int Loan ID, if valid
	 */
	protected function getLoanId() {
		$loan_id = $this->getLibraryObjectId( 'Loan', 'wp_lib_loans', 'loan_id' );
		
		// If Loan ID is invalid, stop page load
		// Otherwise return Loan ID
		if ( $loan_id === false )
			$this->stopAjax();
		else
			return $loan_id;
	}
	
	/*
	 * Fetches Fine ID from URL parameters and validates, handling failure
	 * @return int Fine ID, if valid
	 */
	protected function getFineId() {
		$fine_id = $this->getLibraryObjectId( 'Fine', 'wp_lib_fines', 'fine_id' );
		
		// If Fine ID is invalid, stop AJAX request
		// Otherwise return Fine ID
		if ( $fine_id === false )
			$this->stopAjax();
		else
			return $fine_id;
	}
	
	/*
	 * Terminates AJAX request, calling optional explanatory error
	 * @param int			$error_code	OPTIONAL Error that necessitated terminating request
	 * @param string|array	$params		OPTIONAL Details to enhance error message
	 */
	protected function stopAjax( $error_code = false, $params = false ) {
		// If error code was set, call error
		if ( $error_code )
			wp_lib_error( $error_code, $params );
		
		// Set output success to failure
		$this->output_buffer[0] = false;
		
		// Kill execution, destructor will handle outputting
		die();
	}
	
	// Renders buffered output to page
	function __destruct() {
		echo json_encode( $this->output_buffer );
	}
}

/*
 * Performs a Dashboard action, modifying the Library in some way
 * such as loaning/returning an item or fining a member
 */
class WP_LIB_AJAX_ACTION extends WP_LIB_AJAX {
	// Contains array of posts authorised for deletion
	public $deletion_authed_objects = [];
	
	/*
	 * Performs Dashboard based on requested action
	 */
	public function doAction(){
		// If no action has been specified, call error
		if ( !isset( $_POST['dash_action'] ) )
			$this->stopAjax(500);
		
		switch( $_POST['dash_action'] ) {
			case 'loan':
				$this->doLoanItem();
			break;
			
			case 'schedule':
				$this->doScheduleLoan();
			break;
			
			case 'return-item':
				$this->doReturnItem();
			break;
			
			case 'give-item':
				$this->doGiveItem();
			break;
			
			case 'renew-item':
				$this->doRenewItem();
			break;
			
			case 'run-test-loan':
				$this->doRunTestLoan();
			break;
			
			case 'cancel-fine':
				$this->doCancelFine();
			break;
			
			case 'pay-fine':
				$this->doPayFine();
			break;
			
			case 'delete-object':
				$this->doDeleteObject();
			break;
			
			case 'clean-item':
				$this->doCleanDamagedItem();
			break;
			
			default:
				$this->stopAjax(500);
			break;
		}
	}
	
	/*
	 * Ends AJAX request, returning indication of action success to client
	 * Also buffers any given success notification
	 * @param bool			$success		Whether the AJAX request succeeded or not
	 * @param string|array	$notification	OPTIONAL Notification(s) to send to client
	 * @todo Improve commenting and/or refactor function
	 */
	private function endAction( $success, $notification = false ){
		if ( $success ) {
			if ( is_string( $notification ) ) {
				$this->addNotification( $notification );
			} elseif ( is_array( $notification ) ) {
				foreach ( $notification as $single_no ) {
					$this->addNotification( $notification );
				}
			}
			
			// Triggers WP_LIB_AJAX destructor to render buffered output
			die();
		} else {
			$this->stopAjax();
		}
	}
	
	/*
	 * Loans an item to a member from now until $loan_length of days
	 */
	private function doLoanItem() {
		// Fetches params from AJAX request
		$item_id = $this->getItemId();
		$member_id = $this->getMemberId();
		$loan_length = $this->getPostParam( 'loan_length', 'Loan Length' );
		
		// Validates Nonce
		$this->checkNonce( 'Managing Item: ' . $item_id );
		
		// Attempts to loan item, retuning to client success/failure
		$this->endAction(
			wp_lib_loan_item( $item_id, $member_id, $loan_length ),
			'Loan of ' . get_the_title( $item_id ) . ' to ' . get_the_title( $member_id ) . ' was successful!'
		);
	}
	
	/*
	 * Schedules a loan of an item between the given dates
	 * Scheduled loans can be fulfilled later when the item is given to the member
	 */
	private function doScheduleLoan() {
		// Fetches params from AJAX request
		$item_id = $this->getItemId();
		$member_id = $this->getMemberId();
		$start_date = $this->getPostParam( 'start_date', 'Start Date' );
		$end_date = $this->getPostParam( 'end_date', 'End Date' );
		
		// Validates Nonce
		$this->checkNonce( 'Scheduling Item: ' . $item_id );
		
		// Attempts to convert given dates to Unix timestamps
		wp_lib_convert_date( $start_date );
		wp_lib_convert_date( $end_date );
		
		// Checks if dates failed to convert, return false and call error
		if ( !$start_date || !$end_date )
			$this->stopAjax( 312 );
		
		// If loan starts before it sends or ends before current time, calls an error and The Doctor
		if ( $start_date > $end_date || $end_date < current_time( 'timestamp' ) )
			$this->stopAjax( 307 );
		
		// Returns result (boolean)
		$this->endAction(
			is_int( wp_lib_schedule_loan( $item_id, $member_id, $start_date, $end_date ) ),
			'A loan of ' . get_the_title( $item_id ) . ' has been scheduled'
		);
	}
	
	/*
	 * Returns an item on loan on the given date
	 * @todo Consider adding functionality to allow late fine suppression on items returned at a previous date
	 * @todo Add ref specific nonce checking
	 */
	private function doReturnItem() {
		// Fetches params from AJAX request
		$item_id = $this->getItemId();
		
		// Verifies nonce based on where user came from
		if ( isset( $_POST['ref'] ) ) {
			switch ( $_POST['ref'] ) {
				case 'manage-item':
					$this->checkNonce( 'Managing Item: ' . $item_id );
				break;
				
				case 'resolve-loan':
					$this->checkNonce( 'Resolution of item ' . $item_id . ' for loan '. wp_lib_fetch_loan_id( $item_id ) );
				break;
				
				case 'return-past':
					$this->checkNonce( 'Past returning: ' . $item_id );
				break;
			}
		} else {
			$this->stopAjax( 503 );
		}
		
		// Attempts to validate the relevant nonce depending on whether the item is being returned at a past date, late or on time/early
		if ( isset($_POST['end_date']) ) {
			$end_date = $_POST['end_date'];
			
			// Attempts to converts formatted date to Unix timestamp e.g. 12/08/2013 -> 1386460800
			wp_lib_convert_date( $end_date );
			
			// If date failed to convert, call error
			if ( !$end_date )
				$this->stopAjax( 310 );
		} else {
			// Defaults to not specifying an end date (item will be returned at current WP install time)
			$end_date = false;
		}
		
		if ( isset( $_POST['fine_member'] ) && $_POST['fine_member'] === 'true' ) {
			$member_id = get_post_meta($item_id, 'wp_lib_member', true );
			
			$this->endAction(
				wp_lib_create_fine( $item_id, $end_date ),
				get_the_title($member_id).' has been fined and '.get_the_title($item_id).' has been returned'
			);
		}
		
		$this->endAction(
			wp_lib_return_item( $item_id, $end_date, false ),
			get_the_title( $item_id ) . ' has been returned to the library'
		);
	}
	
	/*
	 * When a scheduled loan is fulfilled by giving the item to the member
	 */
	private function doGiveItem() {
		$loan_id = $this->getLoanId();
		
		// Validates nonce based on source page, as action can come from an Item or Loan management page
		if ( isset( $_POST['ref'] ) ) {
			switch ( $_POST['ref'] ) {
				case 'manage-loan':
					$nonce_input = 'Managing Loan: ' . $loan_id;
				break;
				
				case 'give-item-past':
					$nonce_input = 'Give item past. loan ID: ' . $loan_id;
				break;
				
				default:
					$this->stopAjax( 503 );
				break;
			}
			
			// Validates Nonce
			$this->checkNonce( $nonce_input );
		} else {
			$this->stopAjax( 503 );
		}
		
		// If item is being given at a past date, prepares date
		if ( isset( $_POST['give_date'] ) ) {
			$give_date = $_POST['give_date'];
			
			// Attempts to convert date to Unix timestamp
			wp_lib_convert_date( $give_date );
			
			// Calls error on failure
			if ( $give_date === false )
				$this->stopAjax( 312 );
		}
		
		// Fetches item and member IDs
		$item_id = get_post_meta( $loan_id, 'wp_lib_item', true );
		$member_id = get_post_meta( $loan_id, 'wp_lib_member', true );
		
		// Attempts to return item, returning success
		$this->endAction(
			wp_lib_give_item( $loan_id, $give_date ),
			get_the_title( $item_id ) . ' has been loaned to ' . get_the_title( $member_id )
		);
	}
	
	/*
	 * Renews an item on loan, extending its due date
	 * Renewals can be limited, if the Library is configured accordingly
	 */
	private function doRenewItem() {
		// Fetches params from AJAX request
		$loan_id = $this->getLoanId();
		$renewal_date = $this->getPostParam( 'renewal_date', 'Renewal Date' );
		
		// Attempts to convert renewal date to Unix timestamp
		wp_lib_convert_date( $renewal_date );
		
		// Attempts to renew item, returning success
		$this->endAction(
			wp_lib_renew_item( $loan_id, $renewal_date ),
			get_the_title( get_post_meta( $loan_id, 'wp_lib_item', true ) ) . ' has been renewed'
		);
	}
	
	/*
	 * Creates a loan for testing purposes, loaning the item to a hardcoded member
	 * Created loan is automatically late by design
	 */
	private function doRunTestLoan() {
		// Fetches params from AJAX request
		$item_id = $this->getItemId();
		
		// If debugging mode isn't enabled, stop action
		if ( WP_LIB_DEBUG_MODE !== true )
			$this->stopAjax();
		
		// Validates Nonce
		$this->checkNonce( 'Managing Item: ' . $item_id );
		
		// Fetches all valid (un-archived) members
		$query = NEW WP_Query(
			array(
				'post_type' 	=> 'wp_lib_members',
				'post_status'	=> 'publish',
				'meta_query'	=> array(
					array(
						'key'		=> 'wp_lib_member_archive',
						'value'		=> 'bug #23268', // Allows WP-Librarian to run on pre-3.9 WP installs (bug was fixed for 3.9, text is arbitrary)
						'compare'	=> 'NOT EXISTS'
					)
				)
			)
		);
		
		if ( $query->have_posts() ){
			while ( $query->have_posts() ) {
				$query->the_post();
				
				$valid_members[] = get_the_ID();
			}
		} else {
			$this->stopAjax();
		}
		
		// Randomly selects member from all valid members
		$member_id = $valid_members[array_rand($valid_members,1)];
		
		$start_date = current_time( 'timestamp' ) - ( 10 * 24 * 60 * 60 );
		$end_date = current_time( 'timestamp' ) - ( 3 * 24 * 60 * 60 );
		
		// If possible creates loan of item starting 10 days ago, due 3 days ago (to test fining capabilities)
		$loan_id = wp_lib_schedule_loan( $item_id, $member_id, $start_date, $end_date );
		
		if ( !is_numeric( $loan_id ) )
			$this->stopAjax( 600 );
		
		if ( wp_lib_give_item( $loan_id, current_time( 'timestamp' ) - ( 10 * 24 * 60 * 60 ) + 900 ) ) {
			$this->endAction(
				true,
				"Debugging loan created"
			);
		} else {
			$this->stopAjax( 601 );
		}
	}
	
	/*
	 * Cancels a fine held against a member, cancelling the money owed by the member
	 */
	private function doCancelFine() {
		// Fetches params from AJAX request
		$fine_id = $this->getFineId();
		
		// Validates Nonce
		$this->checkNonce( 'Managing Fine: ' . $fine_id );
		
		// Attempts to cancel fine
		$this->endAction(
			wp_lib_cancel_fine( $fine_id ),
			"Fine #{$fine_id} has been cancelled"
		);
	}
	
	/*
	 * Subtracts debt from a member's total amount owed for late item return
	 * @todo Move payment functionality to functions.php, actions should only prepare data and call functions
	 */
	private function doPayFine() {
		// Fetches member ID and fine amount
		$member_id = $this->getMemberId();
		$fine_payment = floatval( isset($_POST['fine_payment']) ? $_POST['fine_payment'] : 0 );
		
		// Validates Nonce
		$this->checkNonce( 'Pay Member Fines ' . $member_id );
		
		// Fetches member's current amount owed in fines
		$owed = wp_lib_fetch_member_owed( $member_id );
		
		// If fine payment is negative or failed to validate (resulting in 0), call error
		if ( $fine_payment <= 0 )
			$this->stopAjax( 320 );
		// If proposed amount is greater than the amount that needs to be paid, call error
		elseif ( $fine_payment > $owed )
			$this->stopAjax( 321 );
		
		// Subtracts proposed amount from amount owed by member
		$owed = $owed - $fine_payment;
		
		// Updates member's amount owed
		update_post_meta( $member_id, 'wp_lib_owed', $owed );
		
		// Sets up notification for successful fine reduction
		$notification = wp_lib_format_money( $fine_payment ) . ' in fines has been paid by ' . get_the_title( $member_id ) . '.';
		
		// If money is still owed by the member, inform librarian
		if ( $owed != 0 )
			$notification .= ' ' . wp_lib_format_money( $owed ) . ' is still owed.';
		
		$this->endAction( true, $notification );
	}
	
	/*
	 * Deletes a Library object and any connected objects
	 * e.g. an item and all loans/fines caused by said item
	 */
	private function doDeleteObject() {
		// Fetches library object ID
		$post_id = $this->getPostParam( 'post_id', 'Library Item' );
		
		// Fetches object type and capitalises first letter
		$object_type = ucwords( wp_lib_get_object_type( $post_id ) );
		
		// Validates ID of Library object
		if ( $object_type === '' )
			$this->stopAjax();
		
		// Validates Nonce
		$this->checkNonce( 'Deleting object: ' . $post_id );
		
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
							$this->stopAjax( 205 );
					break;
					
					// Checks if loan is open, meaning item is outside the library
					case 'wp_lib_loans':
						if ( get_post_meta( $object[0], 'wp_lib_status', true ) == 1 )
							$this->stopAjax( 205 );
					break;
			}
		}
		
		// Adds objects authorised for deletion to class buffer
		$this->deletion_authed_objects = $connected_objects;
		
		// Iterates over objects, deleting them
		foreach( $connected_objects as $object ) {
			wp_delete_post( $object[0], true );
		}
		
		// If connected objects existed, inform user of how many were deleted, otherwise just inform user object was deleted
		if ( $object_count != 0 ) {
			$this->endAction(
				true,
				wp_lib_plural( $object_count, $object_type . ' and \v connected object\p deleted' )
			);
		} else {
			$this->endAction(
				true,
				$object_type . ' deleted'
			);
		}
	}
	
	/*
	 * Attempts to repair an item with conflicting/bad data
	 * Can occur if safeguards are disabled or a bug occurs
	 */
	private function doCleanDamagedItem() {
		$item_id = $this->getItemId();
		
		// Strips any loan or member currently attached to item
		if ( wp_lib_clean_item( $item_id ) )
			$this->endAction(true, 'Attempted repair of '.get_the_title($item_id).' completed');
		else
			$this->stopAjax();
	}
}

/*
 * Prepares a requested Dashboard page to be rendered client-side by wp_lib_render_page()
 */
class WP_LIB_AJAX_PAGE extends WP_LIB_AJAX {
	/*
	 * Calls relevant prep function based on requested Dash page
	 */
	function loadPage() {
		// If no dash page has been specified, load Dashboard
		if ( !isset( $_POST['dash_page'] ) )
			$this->prepDashboard();
		
		// Calls relevant function to prepare requested page
		switch( $_POST['dash_page'] ) {
			case 'dashboard':
				$this->prepDashboard();
			break;
			
			case 'view-items':
				$this->prepViewItems();
			break;
			
			case 'manage-item':
				$this->prepManageItem();
			break;
			
			case 'manage-member':
				$this->prepManageMember();
			break;
			
			case 'manage-loan':
				$this->prepManageLoan();
			break;
			
			case 'manage-fine':
				$this->prepManageFine();
			break;
			
			case 'scan-item':
				$this->prepSearchItems();
			break;
			
			case 'scheduling-page':
				$this->prepScheduleLoan();
			break;
			
			case 'renew-item':
				$this->prepRenewItem();
			break;
			
			case 'give-item-past':
				$this->prepGiveItemPast();
			break;
			
			case 'return-past':
				$this->prepReturnItemPast();
			break;
			
			case 'resolve-loan':
				$this->prepLateItemResolution();
			break;
			
			case 'pay-fines':
				$this->prepPayMemberFines();
			break;
			
			case 'object-deletion':
				$this->prepConfirmObjectDeletion();
			break;
			
			default:
				$this->stopAjax( 502 );
			break;
		}
	}
	
	/*
	 * Stops Dashboard page loading based on current user circumstances
	 * @param int			$error_code	OPTIONAL error to generate
	 * @param string|array	$param		OPTIONAL parameters to pass to error reporter
	 */
	protected function stopAjax( $error_code = false, $param = false ) {
		if ( $error_code !== false )
			wp_lib_error( $error_code, $param );
		
		// If this is the user's first Dash page then, to avoid user being faced with a blank Dash page, load Dashboard
		// Otherwise allows user to remain on current page and just returns error
		if ( isset( $_POST['ref'] ) ) {
			parent::stopAjax();
		} else {
			// If error occurred on dashboard, call error, otherwise an infinite loop would occur
			// Otherwise load Dashboard homepage, so user can easily continue site navigation after encountering the error
			if ( debug_backtrace()[1]['function'] === 'prepDashboard' )
				parent::stopAjax( 506 );
			else
				$this->prepDashboard();
		}
	}
	
	/* Adds prepared Dashboard page to content buffer then trigger AJAX request closure
	 * @param string	$page_title	Title that displays on the Dash page
	 * @param string	$tab_title	Title that displays in the browser tab
	 * @param array		$page		All Dash page elements, passed to client-side wp_lib_render_page() to construct HTML
	 * @param array		$scripts	OPTIONAL Array of script names (strings) required by the Dash page
	 */
	private function sendPage( $page_title, $tab_title, $page, $scripts = false ) {
		$this->output_buffer[2] = array(
			$page_title,
			$tab_title,
			$page
		);
		
		if ( $scripts )
			$this->output_buffer[2][] = $scripts;
		
		// Triggers flushing of output buffer
		die();
	}
	
	/*
	 * Displays Library Dashboard with links to manage the Library, change settings or read the documentation
	 */
	private function prepDashboard() {
		// Dashboard icons
		$buttons = array(
			array(
				'bName'	=> 'Scan Barcode',
				'icon'	=> 'search',
				'link'	=> 'dash-page',
				'value'	=> 'scan-item',
				'title'	=> 'Enter item barcode or ISBN'
			),
			array(
				'bName'	=> 'Manage Items',
				'icon'	=> 'book-alt',
				'link'	=> 'dash-page',
				'value'	=> 'view-items',
				'title'	=> 'View list of all items'
			),
			array(
				'bName'	=> 'Manage Members',
				'icon'	=> 'admin-users',
				'link'	=> 'post-type',
				'pType'	=> 'wp_lib_members',
				'title'	=> 'View list of all members'
			),
			array(
				'bName'	=> 'Manage Loans',
				'icon'	=> 'upload',
				'link'	=> 'post-type',
				'pType'	=> 'wp_lib_loans',
				'title'	=> 'View list of all loans'
			),
			array(
				'bName'	=> 'Manage Fines',
				'icon'	=> 'carrot', // Placeholder carrot
				'link'	=> 'post-type',
				'pType'	=> 'wp_lib_fines',
				'title'	=> 'View list of all fines'
			),
			array(
				'bName'	=> 'Settings',
				'icon'	=> 'admin-generic',
				'link'	=> 'admin-url',
				'url'	=> 'edit.php?post_type=wp_lib_items&page=wp-lib-settings',
				'title'	=> 'Change WP-Librarian\'s settings'
			),
			array(
				'bName'	=> 'Help',
				'icon'	=> 'editor-help',
				'link'	=> 'url',
				'url'	=> 'https://github.com/kittsville/WP-Librarian/wiki',
				'title'	=> 'Read plugin documentation'
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
		$this->sendPage( $page_title, $page_title, $page );
	}
	
	/*
	 * Displays list of all current items in the library
	 * @todo Move Dynatable generation to dedicated class
	 */
	private function prepViewItems() {
		// Initialises page output
		$page = array();
		
		// Queries database for all valid library items
		$query = NEW WP_Query(
			array(
				'post_type' 	=> 'wp_lib_items',
				'post_status'	=> 'publish'
			)
		);
		
		// Checks if any items were returned
		if ( $query->have_posts() ){
			$page[] = array(
				'type'		=> 'paras',
				'content'	=> array('Select an item to manage it. Late items are highlighted in red.')
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
			$page[] = array(
				'type'		=> 'item-list',
				'data'		=> $items,
				'records'	=> 'items'
			);
		} else {
			$page[] = array(
				'type'		=> 'paras',
				'content'	=> array('No items found.')
			);
		}
		
		$this->sendPage( 'Library Items', 'All Library Items', $page );
	}
	
	/*
	 * Displays information about the given item with links to modify the item based on its current state
	 * @todo Refactor function heavily, it hasn't been reviewed in a very long time
	 */
	private function prepManageItem() {
		// Fetches, sanitizes and verifies validity of item ID
		$item_id = $this->getItemID();
		
		// Prepares the meta box
		$page[] = wp_lib_prep_item_meta_box( $item_id );
		
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
				'html'	=> 'Create Debug Entry',
				'title'	=> 'Create loan that is automatically late to test site functionality'
			);
		}
		
		// Fetches if item is currently on loan
		$on_loan = wp_lib_on_loan( $item_id );
		
		if ( $on_loan ) {
			// Fetches loan ID from Item meta
			$loan_id = wp_lib_fetch_loan_id( $item_id );
			
			// If item can be renewed, provided link to renew item
			if ( wp_lib_loan_renewable( $loan_id ) ) {
				$form[] = array(
					'type'	=> 'button',
					'link'	=> 'page',
					'value'	=> 'renew-item',
					'html'	=> 'Renew'
				);
			}
			
			// Regardless of lateness, provides link to return item at a past date
			$form[] = array(
				'type'	=> 'button',
				'link'	=> 'page',
				'html'	=> 'Return Past',
				'value'	=> 'return-past',
				'title'	=> 'Return item at a past date'
			);
			
			// If item is late
			if ( wp_lib_item_late( $loan_id ) ) {
				// Provides link to resolve late item
				$form[] = array(
					'type'	=> 'button',
					'link'	=> 'page',
					'html'	=> 'Resolve',
					'value'	=> 'resolve-loan',
					'title'	=> 'Choose whether or not to fine the user for the late return'
				);
			
			} else {
				// Provides link to return item today
				$form[] = array(
					'type'	=> 'button',
					'link'	=> 'action',
					'html'	=> 'Return',
					'value'	=> 'return-item',
					'title'	=> 'Return item to the library'
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
			
			// Button to schedule a loan to be fulfilled later
			$form[] = array(
				'type'	=> 'button',
				'link'	=> 'page',
				'html'	=> 'Schedule Loan',
				'value'	=> 'scheduling-page',
				'title'	=> 'Schedule a loan to be fulfilled later'
			);

		}
		
		// Button to edit item
		$form[] = array(
			'type'	=> 'button',
			'link'	=> 'edit',
			'icon'	=> 'edit',
			'title'	=> 'Edit Item'
		);
		
		// Only show item deletion button if item isn't on loan
		if ( !$on_loan ) {
			$form[] = array(
				'type'	=> 'button',
				'link'	=> 'page',
				'value'	=> 'object-deletion',
				'icon'	=> 'trash',
				'title'	=> 'Delete Item'
			);
		}
		
		// Adds Dash form elements to page
		$page[] = array(
			'type'		=> 'form',
			'content'	=> $form
		);
		
		// Fetches list of loans of item
		$page[] = wp_lib_prep_loans_table( $item_id );
		
		// Lists additional scripts needed for Dash page
		$scripts = array( 'admin-dashboard-manage-item' );
		
		// Encodes page as an array to be rendered client-side
		$this->sendPage( 'Managing: ' . get_the_title( $item_id ), 'Managing Item #' . $item_id, $page, $scripts );
	}
	
	/*
	 * Displays member's details and loan history, giving means to pay any outstanding fines
	 */
	private function prepManageMember() {
		// Fetches and validates member ID
		$member_id = $this->getMemberId();
		
		// Renders meta box
		$page[] = wp_lib_prep_member_meta_box( $member_id );
		
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
				'html'	=> 'Pay Fines',
				'title'	=> 'Subtract money from amount owed by member'
			);
		}
		
		// Button to edit member
		$form[] = array(
			'type'	=> 'button',
			'link'	=> 'edit',
			'icon'	=> 'edit',
			'title'	=> 'Edit member details'
		);
		
		// Button to delete member
		$form[] = array(
			'type'	=> 'button',
			'link'	=> 'page',
			'value'	=> 'object-deletion',
			'icon'	=> 'trash',
			'title'	=> 'Delete member and all their loans/fines'
		);
		
		// Adds form elements to Dash page
		$page[] = array(
			'type'		=> 'form',
			'content'	=> $form
		);
		
		wp_lib_add_helper( 'dynatable' );
		
		// Queries database for loans made by member
		$dynatable = new WP_LIB_DYNATABLE_LOANS( 'wp_lib_member', $member_id );
		
		// Generates table of query results
		$page[] = $dynatable->generateTable(
			array(
				array( 'Loan',		'loan',		'genColumnManageLoan' ),
				array( 'Item',		'item',		'genColumnManageItem' ),
				array( 'Status',	'status',	'genColumnLoanStatus' ),
				array( 'Loaned',	'loaned',	'genColumnLoanStart' ),
				array( 'Expected',	'expected',	'genColumnLoanEnd' ),
				array( 'Returned',	'returned',	'genColumnReturned' )
			),
			array(
				'id'		=> 'member-loans',
				'labels'	=> array(
					'records'	=> 'loans'
				)
			),
			get_the_title( $member_id ) . ' has never borrowed an item'
		);
		
		$this->sendPage( 'Managing: ' . get_the_title( $member_id ), 'Managing Member #' . $member_id, $page );
	}
	
	/*
	 * Displays loan details and options to change the loan, if it hasn't been completed yet
	 */
	private function prepManageLoan() {
		// Fetches loan ID from AJAX request
		$loan_id = $this->getLoanId();
		
		// Fetches loan meta
		$meta = get_post_meta( $loan_id );
		
		// Renders header with useful loan information
		$page[] = wp_lib_prep_loan_meta_box( $loan_id );
		
		// Fetches item status
		$status = $meta['wp_lib_status'][0];
		
		// Initialises form
		$form = array(
			array(
				'type'	=> 'hidden',
				'name'	=> 'loan_id',
				'value'	=> $loan_id
			)
		);
		
		// If item can be renewed, provided link to renew item
		if ( wp_lib_loan_renewable( $loan_id ) ) {
			$form[] = array(
				'type'	=> 'button',
				'link'	=> 'page',
				'value'	=> 'renew-item',
				'html'	=> 'Renew Item'
			);
		}
		
		// Fetches current local time
		$time = current_time( 'timestamp' );
		
		// If loan is scheduled and the loan's start date has already happened
		if ( $status === '5' && $meta['wp_lib_start_date'][0] <= $time ) {
			// If loan's end date has not passed yet
			if ( $time <= $meta['wp_lib_end_date'][0] ) {
				// Adds nonce so dash action will work
				$form[] = wp_lib_prep_nonce( 'Managing Loan: ' . $loan_id );
				
				// Button to give item to member
				$form[] = array(
					'type'	=> 'button',
					'link'	=> 'action',
					'value'	=> 'give-item',
					'html'	=> 'Loan Item'
				);
			}
			
			// Adds option to fulfil loan at a past date
			$form[] = array(
				'type'	=> 'button',
				'link'	=> 'page',
				'value'	=> 'give-item-past',
				'html'	=> 'Loan at a Past Date',
				'title'	=> 'Mark item as having been given to the member at a past date'
			);
		
		// If item is currently on loan, provides button to return item
		} else if ( $status === '1' ) {
			// Adds hidden element with item ID
			$form[] = array(
				'type'	=> 'hidden',
				'name'	=> 'item_id',
				'value'	=> get_post_meta( $loan_id, 'wp_lib_item', true )
			);
			
			// Adds button to return item (redirects to item management page)
			$form[] = array(
				'type'	=> 'button',
				'link'	=> 'page',
				'value'	=> 'manage-item',
				'html'	=> 'Return Item',
				'title'	=> 'Return item to the library'
			);
		}
		
		// If loan is not open, displays delete button
		if ( $status !== '1' ) {
			$form[] = array(
				'type'	=> 'button',
				'link'	=> 'page',
				'value'	=> 'object-deletion',
				'icon'	=> 'trash',
				'title'	=> 'Delete loan and any connected fine'
			);
		}
		
		// Adds form to page
		$page[] = array(
			'type'		=> 'form',
			'content'	=> $form
		);
		
		$this->sendPage( 'Managing: Loan #' . $loan_id, 'Managing Loan #' . $loan_id, $page );
	}
	
	/*
	 * Displays details of fine and provides options to cancel/pay fine
	 */
	private function prepManageFine() {
		// Fetches and validates Fine ID
		$fine_id = $this->getFineId();
		
		$page[] = wp_lib_prep_fine_meta_box( $fine_id );
		
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
				'html'	=> 'Cancel Fine',
				'title'	=> 'Mark fine as cancelled. This stops the member owing the fine\'s charge'
			);
		}
		
		// Adds deletion option
		$form[] = array(
			'type'	=> 'button',
			'link'	=> 'page',
			'value'	=> 'object-deletion',
			'icon'	=> 'trash',
			'title'	=> 'Delete fine. Does not cancel money owed by member'
		);
		
		// Adds form to Dash page
		$page[] = array(
			'type'		=> 'form',
			'content'	=> $form
		);
		
		// Sends entire page to be encoded in JSON
		$this->sendPage( 'Managing: Fine #' . $fine_id, 'Managing Fine #' . $fine_id, $page );
	}
	
	/*
	 * Displays page for user to look up an item using its barcode or ISBN
	 */
	private function prepSearchItems() {
		$page = array(
			array(
				'type'		=> 'form',
				'content'	=>
				array(
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
				)
			)
		);
		
		// Enqueues barcode page script
		$scripts[] = 'admin-barcode-scanner';
		
		// Sets item title
		$title = 'Scan Item Barcode';
		
		// Sends form to client to be rendered
		$this->sendPage( $title, $title, $page, $scripts );
	}
	
	/*
	 * Provides form for Library to schedule a loan to be fulfilled later
	 * Scheduled loans are fulfilled when the item is then given to the member on the appropriate date
	 */
	private function prepScheduleLoan() {
		// Fetches and validates Item ID
		$item_id = $this->getItemId();
		
		// Prepares box of useful information about the current item
		$page[] = wp_lib_prep_item_meta_box( $item_id );
		
		$member_options = wp_lib_prep_member_options();
		
		// Formats placeholder loan start date (current date)
		$start_date = Date( 'Y-m-d', current_time( 'timestamp' ) );
		
		// Formats placeholder loan end date (current date + default loan length)
		$end_date = Date( 'Y-m-d', current_time( 'timestamp' ) + ( get_option( 'wp_lib_loan_length', array(12) )[0] * 24 * 60 * 60) );
		
		$page[] = array(
			'type'		=> 'form',
			'content'	=>
			array(
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
			)
		);
		
		// Fetches list of loans of item
		$page[] = wp_lib_prep_loans_table( $item_id );
		
		// Lists additional scripts needed for Dash page
		$scripts = array( 'admin-dashboard-manage-item' );
		
		$this->sendPage( 'Scheduling loan of ' . get_the_title( $item_id ), 'Scheduling loan of #' . $item_id, $page, $scripts );
	}
	
	/*
	 * Displays form allowing Librarian to renew item, extending its due date
	 */
	private function prepRenewItem() {
		// Fetches loan ID, or uses item ID to fetch loan ID
		if ( isset( $_POST['item_id'] ) ) {
			$item_id = $this->getItemId();
			
			$loan_id = get_post_meta( $item_id, 'wp_lib_loan', true );
			
			// If item is not currently on loan (and thus loan ID is missing from item meta), call error
			if ( $loan_id === '' )
				$this->stopAjax( 208 );
		} else {
			$loan_id = $this->getLoanId();
			
			$item_id = get_post_meta( $loan_id, 'wp_lib_item', true );
		}
		
		// Counts number of times item has already been renewed
		$renewed_count = count(get_post_meta( $loan_id, 'wp_lib_renew' ));
		
		// Fetches limit to number of times item can be renewed
		$limit = (int) get_option( 'wp_lib_renew_limit' )[0];
		
		// If item can be renewed an infinite number of times
		if ( $limit === 0 )
			$renewals_left = 'This item can be renewed indefinitely';
		// If item has been renewed the maxim number of times allowed (or more)
		elseif (!( $limit > $renewed_count ))
			$this->stopAjax( 209 );
		// Otherwise item has at least renewal left
		else
			$renewals_left = wp_lib_plural( $limit - $renewed_count, 'This item can be renewed \v more time\p' );
		
		$page[] = wp_lib_prep_item_meta_box( $item_id );
		
		$page[] = array(
			'type'		=> 'paras',
			'content'	=> array(
				$renewals_left,
				'Select when the item should now be due back:'
			)
		);
		
		$page[] = array(
			'type'		=> 'form',
			'content'	=> array(
				array(
					'type'	=> 'hidden',
					'name'	=> 'loan_id',
					'value'	=> $loan_id
				),
				array(
					'type'	=> 'date',
					'name'	=> 'renewal_date',
					'id'	=> 'item-renew-date',
					'value'	=> Date( 'Y-m-d', current_time( 'timestamp' ) )
				),
				array(
					'type'	=> 'button',
					'link'	=> 'action',
					'value'	=> 'renew-item',
					'html'	=> 'Renew Item'
				)
			)
		);
		
		$page[] = wp_lib_prep_loans_table( $item_id );
		
		$this->sendPage(
			'Renewing Item: ' . get_the_title( $item_id ),
			'Renewing Item #' . $item_id,
			$page
		);
	}
	
	/*
	 * Displays form to allow Librarian to mark an item as having left the Library at a previous date
	 */
	private function prepGiveItemPast() {
		// Fetches and validates Loan ID
		$loan_id = $this->getLoanId();
		
		// Fetches loan meta
		$meta = get_post_meta( $loan_id );
		
		// Checks if loan has correct status to be allowed to 
		if ( $meta['wp_lib_status'][0] !== '5' || $meta['wp_lib_start_date'][0] > current_time( 'timestamp' ) ) {
			$this->stopAjax( 322 );
		}
		
		$this->sendPage(
			'Managing: Loan #' . $loan_id,				// Page title
			'Managing Loan #' . $loan_id,				// Tab title
			array(
				wp_lib_prep_loan_meta_box( $loan_id ),	// Prepares box with useful information about the loan
				array(
					'type'		=> 'form',
					'content'	=>
					array(
						wp_lib_prep_nonce( 'Give item past. loan ID: ' . $loan_id ),
						array(
							'type'	=> 'hidden',
							'name'	=> 'loan_id',
							'value'	=> $loan_id
						),
						array(
							'type'		=> 'paras',
							'content'	=> array( 'Enter date that item was given to member. In future do not release the item from the Library without recording it first.' )
						),
						array(
							'type'	=> 'date',
							'name'	=> 'give_date',
							'id'	=> 'loan-give-date',
							'value'	=> Date( 'Y-m-d', $meta['wp_lib_start_date'][0] )
						),
						array(
							'type'	=> 'button',
							'link'	=> 'action',
							'value'	=> 'give-item',
							'html'	=> 'Loan Item'
						)
					)
				)
			)
		);
	}
	
	/*
	 * Provides a form to choose when in the past to return an item
	 */
	private function prepReturnItemPast() {
		// Fetches item ID from AJAX request
		$item_id = $this->getItemId();

		// Checks if item is on loan
		if ( !wp_lib_on_loan( $item_id ) )
			$this->stopAjax( 402 );
		
		$this->sendPage(
			'Returning: ' . get_the_title( $item_id ),	// Page Title
			'Returning item #' . $item_id,				// Tab Title
			array(
				wp_lib_prep_item_meta_box( $item_id ),// Prepares box with useful details about the item
				array(
					'type'		=> 'form',
					'content'	=>
					array(
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
					)
				)
			)
		);
	}
	
	/*
	 * Provides options to remedy an item that should have been returned earlier
	 */
	private function prepLateItemResolution() {
		// Fetches loan ID using item ID
		$item_id = $this->getItemId();
		
		// Fetches item ID from loan meta
		$loan_id = wp_lib_fetch_loan_id( $item_id );
		
		// Ensures item is actually late
		if ( !wp_lib_item_late( $loan_id ) )
			$this->stopAjax( 406 );
		
		// Fetches current date
		$date = current_time( 'timestamp' );
		
		// Useful variables:
		// Formatted string of item lateness
		$days_late = wp_lib_prep_item_due( $item_id, $date, array( 'late' => '\d day\p' ) );
		// Item's title
		$title = get_the_title( $item_id );
		// Librarian set charge for each day an item is late
		$fine_per_day = get_option( 'wp_lib_fine_daily', array(0) )[0];
		// Days item is late
		$late = -wp_lib_cherry_pie( $loan_id, $date );
		// Total fine member member is facing, if charged
		$fine = wp_lib_format_money( $fine_per_day * $late );
		// Fine per day formatted
		$fine_per_day_formatted = wp_lib_format_money( $fine_per_day );
		// Member's name
		$member_name = get_the_title( get_post_meta( $item_id, 'wp_lib_member', true ) );
		
		$this->sendPage(
			'Resolving Late Item: ' . $title,
			'Resolving Item #' . $item_id,
			array(
				wp_lib_prep_item_meta_box( $item_id ),
				array(
					'type'		=> 'form',
					'content'	=>
					array(
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
							'html'	=> 'Fine',
							'title'	=> 'Charge member given fine amount and return item'
						),
						array(
							'type'	=> 'button',
							'link'	=> 'action',
							'value'	=> 'return-item-no-fine',
							'html'	=> 'Return with no Fine',
							'title'	=> 'Return item without fining user'
						),
						array(
							'type'	=> 'button',
							'link'	=> 'page',
							'value'	=> 'manage-item',
							'html'	=> 'Cancel',
							'title'	=> 'Go back to item management page'
						)
					)
				),
				wp_lib_prep_loans_table( $item_id )
			)
		);
	}
	
	/*
	 * Allows Library to reduce fines owed by member by paying part/all owed
	 */
	private function prepPayMemberFines() {
		// Fetches and validates Member ID
		$member_id = $this->getMemberId();
		
		// Checks that there is actually money owed, stopping page load on failure
		if ( wp_lib_fetch_member_owed( $member_id ) == 0 )
			$this->stopAjax( 206 );
		
		$this->sendPage(
			'Managing: ' . get_the_title( $member_id ),
			'Managing Member #' . $member_id,
			array(
				wp_lib_prep_member_meta_box( $member_id ),
				array(
					'type'		=> 'form',
					'content'	=>
					array(
						array(
							'type'		=> 'paras',
							'content'	=> array("Enter an amount to reduce the member's total owed to the Library")
						),
						array(
							'type'	=> 'hidden',
							'name'	=> 'member_id',
							'value'	=> $member_id
						),
						wp_lib_prep_nonce( 'Pay Member Fines ' . $member_id ),
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
					)
				)
			)
		);
	}
	
	/*
	 * Checks for confirmation for deleting an object in the Library
	 * Also displays all objects connected to the current object
	 */
	private function prepConfirmObjectDeletion() {
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
			
			// If no ID field was set, call error
			if ( !isset( $post_id ) )
				$this->stopAjax( 300, 'Object' );
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
					$this->stopAjax( 205 );
				
				// Renders meta box, displaying useful information about the item
				$page[] = wp_lib_prep_item_meta_box( $post_id );
				
				// Sets titles of Dash page and browser tab
				$page_title = 'Deleting: ' . get_the_title( $post_id );
				$tab_title = 'Deleting Item #' . $post_id;
				
				// Sets object type for use in button labels
				$object_type = 'Item';
				
				// Informs user of implications of deletion
				$page[] = array(
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
					$this->stopAjax( 205 );
				
				// Renders meta box, displaying useful information about the loan
				$page[] = wp_lib_prep_loan_meta_box( $post_id );
				
				// Sets titles of Dash page and browser tab
				$page_title = 'Deleting: Loan #' . $post_id;
				$tab_title = 'Deleting Loan #' . $post_id;
				
				// Informs user of implications of deletion
				$page[] = array(
					'type'		=> 'paras',
					'content'	=> array(
						'Deleting a loan is a permanent action. Any fines dependant on this loan will also be deleted.',
						'If you want to delete loans in bulk, without this prompt, allow bulk deletion via WP-Librarian\'s settings'
					)
				);
			break;
			
			case 'fine':
				// Renders meta box, displaying useful information about the fine
				$page[] = wp_lib_prep_fine_meta_box( $post_id );
				
				// Sets titles of Dash page and browser tab
				$page_title = 'Deleting: Fine #' . $post_id;
				$tab_title = 'Deleting Fine #' . $post_id;
				
				// Informs user of implications of deletion
				$page[] = array(
					'type'		=> 'paras',
					'content'	=> array(
						'Deleting a fine is a permanent action and will result in the deletion of any loan dependant on this fine',
						'To remove any money owed by the member because of this fine, cancel the fine first',
						'If you want to delete fines in bulk, without this prompt, allow bulk deletion via WP-Librarian\'s settings'
					)
				);
			break;
			
			case 'member':
				// Renders meta box, displaying useful information about the member
				$page[] = wp_lib_prep_member_meta_box( $post_id );
				
				// Sets dash page title and tab title
				$page_title = 'Deleting: ' . get_the_title( $post_id );
				$tab_title = 'Deleting Member #' . $post_id;
				
				// Informs user of implications of deletion
				$page[] = array(
					'type'		=> 'paras',
					'content'	=> array(
						'Deleting a member is a permanent action. You can choose to also delete all loans/fines dependant on this member',
						'If you want to delete members in bulk, without this prompt, allow bulk deletion via WP-Librarian\'s settings'
					)
				);
			break;
		}
		
		// Adds page nonce, object ID and relevant buttons to form
		$page[] = array(
			'type'		=> 'form',
			'content'	=>
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
					'html'	=> 'Cancel',
					'title'	=> 'Cancel deletion and return to Dashboard home'
				)
			)
		);
		
		// Looks for all objects connected to the current one (e.g. loans by a member, or fines as a result of a loan)
		$connected_objects = wp_lib_fetch_dependant_objects( $post_id );
		
		if ( $connected_objects ) {
			$page[] = array(
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
			$page[] = array(
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
			$page[] = array(
				'type'		=> 'paras',
				'content'	=> array( 'No other objects in the Library are dependant on this object' )
			);
		}
		
		$this->sendPage( $page_title, $tab_title, $page );
	}
}

/*
 * Handles Dashboard API requests, providing useful information to dynamically enhance Dash pages
 */
class WP_LIB_AJAX_API extends WP_LIB_AJAX {
	/*
	 * Adds result of API request to content buffer, to be returned to user, then triggers AJAX request closure
	 * @param mixed $data Content to be returned to client
	 */
	private function sendData( $data ) {
		$this->output_buffer[2] = $data;
		
		// Triggers parent destructor, rendering output buffer to page as JSON
		die();
	}
	
	/*
	 * Performs API request and returns relevant information to client
	 */
	public function doRequest(){
		// If no request has been given, return error
		if ( !isset( $_POST['api_request'] ) )
			$this->stopAjax( 504 );
		
		// Performs action based on request
		switch( $_POST['api_request'] ) {
			// Loads member metabox, given member ID
			case 'member-metabox':
				$this->getMemberMetaBox();
			break;
			
			// Fetches parameters for barcode scanning script from database
			case 'barcode-setup':
				$this->getBarcodePageSettings();
			break;
			
			// Looks up item based on given ISBN or barcode
			case 'scan-barcode':
				$this->getSearchItemByBarcode();
			break;
			
			default:
				$this->stopAjax( 504 );
			break;
		}
	}
	
	/*
	 * Creates a meta box with useful details for the given member ID
	 */
	private function getMemberMetaBox() {
		// Fetches member ID from AJAX request
		$member_id = $this->getMemberId();
		
		// Fetches member meta
		$meta = get_post_meta( $member_id );
		
		// Sets up header's meta fields
		$meta_fields = array(
			array( 'Name', get_the_title( $member_id ) ),
			array( 'Email', $meta['wp_lib_member_email'][0] ),
			array( 'On Loan', wp_lib_prep_members_items_out( $member_id ) ),
			array( 'Owed', wp_lib_format_money( wp_lib_fetch_member_owed( $member_id ) ) )
		);
		
		// Finalises and returns member meta box
		$this->sendData( array(
			'type'		=> 'metabox',
			'title'		=> 'Member Details',
			'classes'	=> 'member-man',
			'fields'	=> $meta_fields
		));
	}
	
	/*
	 * Fetches barcode field settings and returns them
	 */
	private function getBarcodePageSettings() {
		$settings = get_option( 'wp_lib_barcode_config', false );
		
		// If setting are invalid, run settings integrity check
		// Otherwise sends settings to user
		if ( $settings === false ) {
			wp_lib_add_helper( 'settings' );
			WP_LIB_SETTINGS::checkPluginSettingsIntegrity();
			
			$this->stopAjax();
		} else {
			$this->sendData( $settings );
		}
	}
	
	/*
	 * Looks up given barcode and returns ID of corresponding item, or error if no/more than one item exists
	 */
	private function getSearchItemByBarcode(){
		if ( isset( $_POST['code'] ) )
			$barcode = $_POST['code'];
		else
			stopAjax( 318 );
		
		// Attempts to sanitize barcode as an ISBN
		$isbn = wp_lib_sanitize_isbn( $barcode );
		
		// If sanitization fails, assumes given value is a barcode
		if ( $isbn === '' ) {
			// If barcode is zero, invalid barcode was given
			if ( !ctype_digit( $barcode ) )
			$this->stopAjax( 318 );
			
			$meta_query = array(
				'key'		=> 'wp_lib_item_barcode',
				'value'		=> $barcode,
				'compare'	=> 'IN'
			);
		} else {
			$meta_query = array(
				'key'		=> 'wp_lib_item_isbn',
				'value'		=> $isbn,
				'compare'	=> 'IN'
			);
		}
			
		// Sets up meta query arguments
		$args = array(
			'post_type'		=> 'wp_lib_items',
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
			$this->sendData( get_the_ID() );
		} elseif ( $posts_found > 1 ) {
			// If multiple items have said barcode, call error
			$this->stopAjax( 204 );
		} else {
			// If no items were found, call error
			$this->stopAjax( 319 );
		}
	}
}


















?>