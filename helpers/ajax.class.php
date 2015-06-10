<?php
// No direct loading
defined('ABSPATH') OR die('No');

/**
 * Handles all Dashboard AJAX requests
 */
class WP_Lib_AJAX {
	/**
	 * Buffers content to be returned to server
	 * @var array
	 */
	protected $output_buffer = array(
		true,   // Success/failure of request
		array(),// Buffered notifications
		array() // Content
	);
	
	/**
	 * Single instance of core plugin class
	 * @var WP_Librarian
	 */
	protected $wp_librarian;
	
	/**
	 * Adds instance of core plugin class to ajax classes' properties
	 */
	function __construct(WP_Librarian $wp_librarian) {
		$this->wp_librarian = $wp_librarian;
		
		// Ensures object can only be created if an AJAX request is being performed
		if (!defined('DOING_AJAX') || !DOING_AJAX)
			return wp_lib_error(116);
		
		if (!wp_lib_is_librarian())
			return wp_lib_error(112);
		
		// Loads library object classes
		$wp_librarian->loadObjectClasses();
	}
	
	/**
	 * Adds notification to the notification/error buffer
	 * Typically you should use stopAJAX() as this also handles stopping execution
	 * @param string    $notification   Explanation of error that has occurred or modification to the library
	 * @param int       $error_code     OPTIONAL error code, leave if not an error. Set as 1 if it's an error but not one with an error code
	 */
	public function addNotification($notification, $error_code = 0) {
		$this->output_buffer[1][] = array($error_code, $notification);
	}
	
	/**
	 * Adds an entry to the 'content' section of the AJAX buffer sent as a reply to the Dashboard
	 * @param mixed $content Whatever content is to be added to the buffer
	 */
	public function addContent($content) {
		$this->output_buffer[2][] = $content;
	}
	
	/**
	 * Verifies WP nonce of AJAX request, stopping request with error on failure
	 * @param string $action Circumstance of page that generated the request
	 */
	public function checkNonce($action) {
		if (!isset($_POST['wp_lib_ajax_nonce']) || wp_verify_nonce($_POST['wp_lib_ajax_nonce'], $action) !== 1)
			$this->stopAjax(503);
	}
	
	/**
	 * Fetches a parameter from POST data, handling failure
	 * This function does not perform any data sanitization
	 * @param string $post_key Name of POST variable e.g 'item_id'
	 * @param string $post_key Readable variable name used in error messages e.g. 'Item ID'
	 */
	protected function getPostParam($post_key, $param_name) {
		// If parameter doesn't exist, call error
		if (!isset($_POST[$post_key]))
			$this->stopAjax(314, $param_name);
		else
			return $_POST[$post_key];
	}
	
	/**
	 * Returns value of meta field, if it exists, via a callback (if one was given). Otherwise returns '-'
	 * @param   array       $meta   Post meta
	 * @param   string      $key    Post meta key
	 * @param   bool        $error  OPTIONAL Call an error if the meta field does not exist
	 * @param   callback    $filter OPTIONAL Callback to process the meta field
	 * @return  string              Post meta value or '-'
	 */
	public function getMetaField(Array $meta, $key, $error = false, $filter = false) {
		if (isset($meta[$key]))
			// Returns meta value, filtered via a callback if there is a valid one
			if ($filter && is_callable($filter))
				return $filter($meta[$key][0]);
			else
				return $meta[$key][0];
		elseif ($error)
			$this->stopAjax(507, $key);
		else
			return '-';
	}
	
	/**
	 * Creates instance of item class using Item ID fetched from AJAX request
	 * @return WP_Lib_Item|WP_Lib_Error Item instance
	 */
	protected function getItem() {
		// If item ID was not given, call error
		if (!isset($_POST['item_id']))
			$this->stopAjax(wp_lib_error(314, 'Item ID'));
		
		// Attempts to create item instance from item ID
		$item = WP_Lib_Item::create($this->wp_librarian, (int) $_POST['item_id']);
		
		// If item ID was invalid (error was returned) stops AJAX request
		if (wp_lib_is_error($item))
			$this->stopAjax($item);
		// Otherwise returns new item instance
		else
			return $item;
	}
	
	/**
	 * Creates instance of member class using Member ID fetched from AJAX request
	 * @return WP_Lib_Member|WP_Lib_Error   Member instance
	 */
	protected function getMember() {
		// If member ID was not given, call error
		if (!isset($_POST['member_id']))
			$this->stopAjax(wp_lib_error(314, 'Member ID'));
		
		// Attempts to create member instance from member ID
		$member = WP_Lib_Member::create($this->wp_librarian, (int) $_POST['member_id']);
		
		// If member ID was invalid (error was returned) stops AJAX request
		if (wp_lib_is_error($member))
			$this->stopAjax($member);
		// Otherwise returns new member instance
		else
			return $member;
	}
	
	/**
	 * Creates instance of loan class using Loan ID fetched from AJAX request
	 * @return WP_Lib_Loan|WP_Lib_Error Loan instance
	 */
	protected function getLoan() {
		// If loan ID was not given, call error
		if (!isset($_POST['loan_id']))
			$this->stopAjax(wp_lib_error(314, 'Loan ID'));
		
		// Attempts to create loan instance from loan ID
		$loan = WP_Lib_Loan::create($this->wp_librarian, (int) $_POST['loan_id']);
		
		// If loan ID was invalid (error was returned) stops AJAX request
		if (wp_lib_is_error($loan))
			$this->stopAjax($loan);
		// Otherwise returns new loan instance
		else
			return $loan;
	}
	
	/**
	 * Creates instance of fine class using Fine ID fetched from AJAX request
	 * @return WP_Lib_Fine|WP_Lib_Error Fine instance
	 */
	protected function getFine() {
		// If fine ID was not given, call error
		if (!isset($_POST['fine_id']))
			$this->stopAjax(wp_lib_error(314, 'Fine ID'));
		
		// Attempts to create fine instance from fine ID
		$fine = WP_Lib_Fine::create($this->wp_librarian, (int) $_POST['fine_id']);
		
		// If fine ID was invalid (error was returned) stops AJAX request
		if (wp_lib_is_error($fine))
			$this->stopAjax($fine);
		// Otherwise returns new fine instance
		else
			return $fine;
	}
	
	/**
	 * Adds error to notification buffer
	 * @param int|WP_Lib_Error  $error  ID or instance of an error that occurred within WP-Librarian
	 * @param string|array      $params OPTIONAL Details to enhance error message
	 */
	protected function handleError($error, $params = false) {
		// If error code was given, create error instance then add error to notification buffer
		if (is_int($error))
			$this->addNotification(wp_lib_error($error, $params)->description, $error);
		// If error instance was given, add error to notification buffer
		elseif (wp_lib_is_error($error))
			$this->addNotification($error->description, $error->ID);
	}
	
	/**
	 * Terminates AJAX request, calling optional explanatory error
	 * @param int|WP_Lib_Error  $error      OPTIONAL ID or instance of error that necessitated terminating request
	 * @param string|array      $params     OPTIONAL Details to enhance error message
	 */
	public function stopAjax($error = false, $params = false) {
		if ($error !== false)
			$this->handleError($error, $params);
		
		// Sets request success to failure
		$this->output_buffer[0] = false;
		
		// Kills execution, destructor will handle sending buffer
		die();
	}
	
	// Renders buffered output to page
	function __destruct() {
		echo json_encode($this->output_buffer);
	}
}

/**
 * Performs a Dashboard action, modifying the Library in some way
 * such as loaning/returning an item or fining a member
 */
class WP_Lib_AJAX_Action extends WP_Lib_AJAX {
	/**
	 * Post IDs/post types of library objects that have been checked and can be deleted
	 * without breaking the library, provided that ALL posts in the array are deleted
	 * @todo Find a better way than this to allow object deletion
	 * @var Array
	 */
	public $deletion_authorised_objects = array();
	
	/**
	 * Performs Dashboard based on requested action
	 */
	function __construct(WP_Librarian $wp_librarian) {
		add_action('wp_lib_bypass_deletion_checks', array($this, 'allowPostDeletion'), 2, 10);
		
		parent::__construct($wp_librarian);
		// If no action has been specified, call error
		if (!isset($_POST['dash_action']))
			$this->stopAjax(500);
		
		// Allows developers to add/overwrite a specific Dash action
		do_action('wp_lib_dash_action_'.$_POST['dash_action'], $this);
		
		// Allows developers to interact with all Dash actions
		do_action('wp_lib_dash_action', $this, $_POST['dash_action']);
		
		switch($_POST['dash_action']) {
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
	
	/**
	 * Ends AJAX request, returning indication of action success to client
	 * Also buffers any given success notification
	 * @param bool|WP_Lib_Error $success        Whether the AJAX request succeeded or not
	 * @param string|array      $notification   OPTIONAL Notification(s) to send to client if action was a success
	 * @todo                                    Improve commenting and/or refactor function
	 */
	public function endAction($success, $notification = false){
		if ($success === true) {
			if (is_string($notification)) {
				$this->addNotification($notification);
			} elseif (is_array($notification)) {
				foreach ($notification as $single_no) {
					$this->addNotification($notification);
				}
			}
			
			// Triggers WP_Lib_AJAX destructor to render buffered output
			die();
		} else {
			$this->stopAjax($success);
		}
	}
	
	/**
	 * Loans an item to a member from now until $loan_length of days
	 */
	private function doLoanItem() {
		// Fetches params from AJAX request
		$item           = $this->getItem();
		$member         = $this->getMember();
		$loan_length    = $this->getPostParam('loan_length', 'Loan Length');
		
		// Validates Nonce
		$this->checkNonce('Managing Item: ' . $item->ID);
		
		// Attempts to loan item, retuning to client success/failure
		$this->endAction(
			$item->loanItem($member->ID, $loan_length),
			'Loan of ' . get_the_title($item->ID) . ' to ' . get_the_title($member->ID) . ' was successful!'
		);
	}
	
	/**
	 * Schedules a loan of an item between the given dates
	 * Scheduled loans can be fulfilled later when the item is given to the member
	 */
	private function doScheduleLoan() {
		// Fetches params from AJAX request
		$item       = $this->getItem();
		$member     = $this->getMember();
		$start_date = $this->getPostParam('start_date', 'Start Date');
		$end_date   = $this->getPostParam('end_date', 'End Date');
		
		// Validates Nonce
		$this->checkNonce('Scheduling Item: ' . $item->ID);
		
		// Attempts to convert given dates to Unix timestamps
		wp_lib_convert_date($start_date);
		wp_lib_convert_date($end_date);
		
		// Checks if dates failed to convert, return false and call error
		if (!$start_date || !$end_date)
			$this->stopAjax(312);
		
		// If loan starts before it sends or ends before current time, calls error
		if ($start_date > $end_date || $end_date < current_time('timestamp'))
			$this->stopAjax(307);
		
		// Loan ID is returned on success, WP_Lib_Error on false
		$success = $item->scheduleLoan($member->ID, $start_date, $end_date);
		
		// Returns success/failure
		$this->endAction(
			is_int($success) ? true : $success,
			'A loan of ' . get_the_title($item->ID) . ' has been scheduled'
		);
	}
	
	/**
	 * Returns an item on loan on the given date
	 * @todo Consider adding functionality to allow late fine suppression on items returned at a previous date
	 * @todo Add ref specific nonce checking
	 */
	private function doReturnItem() {
		// Creates instance of item from item ID in AJAX request
		$item = $this->getItem();
		
		// Verifies nonce based on where user came from
		if (isset($_POST['ref'])) {
			switch ($_POST['ref']) {
				case 'manage-item':
					$this->checkNonce('Managing Item: ' . $item->ID);
				break;
				
				case 'resolve-loan':
					$this->checkNonce('Resolution of item ' . $item->ID . ' for loan '. $item->getLoanId());
				break;
				
				case 'return-past':
					$this->checkNonce('Past returning: ' . $item->ID);
				break;
			}
		} else {
			$this->stopAjax(503);
		}
		
		// Attempts to validate the relevant nonce depending on whether the item is being returned at a past date, late or on time/early
		if (isset($_POST['end_date'])) {
			$end_date = $_POST['end_date'];
			
			// Attempts to converts formatted date to Unix timestamp e.g. 12/08/2013 -> 1386460800
			wp_lib_convert_date($end_date);
			
			// If date failed to convert, call error
			if (!$end_date)
				$this->stopAjax(310);
		} else {
			// Defaults to not specifying an end date (item will be returned at current WP time)
			$end_date = false;
		}
		
		// Sets whether to fine member and if any behaviour for a fine has been specified
		$fine = (isset($_POST['fine_member']) ? ($_POST['fine_member'] === 'true' ? true : false) : null);
		
		// Creates loan object
		$loan = $item->getLoan();
		
		// Attempts to return item, potentially charging fine
		$success = $loan->returnItem($end_date, $fine);
		
		$title = get_the_title($item->ID);
		
		// If fine was charged, use different (more informative) notification
		if (is_int($success))
			$notification = get_the_title(get_post_meta($success, 'wp_lib_member', true)).' has been fined and '.$title.' has been returned';
		elseif ($success === true)
			$notification = $title.' has been returned to the library';
		
		$this->endAction(
			$success,
			$notification
		);
	}
	
	/**
	 * When a scheduled loan is fulfilled by giving the item to the member
	 */
	private function doGiveItem() {
		$loan = $this->getLoan();
		
		// Validates nonce based on source page, as action can come from an Item or Loan management page
		if (isset($_POST['ref'])) {
			switch ($_POST['ref']) {
				case 'manage-loan':
					$nonce_input = 'Managing Loan: ' . $loan->ID;
				break;
				
				case 'give-item-past':
					$nonce_input = 'Give item past. loan ID: ' . $loan->ID;
				break;
				
				default:
					$this->stopAjax(503);
				break;
			}
			
			// Validates Nonce
			$this->checkNonce($nonce_input);
		} else {
			$this->stopAjax(503);
		}
		
		// If item is being given at a past date, prepares date
		if (isset($_POST['give_date'])) {
			$give_date = $_POST['give_date'];
			
			// Attempts to convert date to Unix timestamp
			wp_lib_convert_date($give_date);
			
			// Calls error on failure
			if ($give_date === false)
				$this->stopAjax(312);
		}
		
		// Fetches item and member IDs
		$item_id = get_post_meta($loan->ID, 'wp_lib_item', true);
		$member_id = get_post_meta($loan->ID, 'wp_lib_member', true);
		
		// Attempts to return item, returning success
		$this->endAction(
			$loan->giveItem($give_date),
			get_the_title($item_id) . ' has been loaned to ' . get_the_title($member_id)
		);
	}
	
	/**
	 * Renews an item on loan, extending its due date
	 * Renewals can be limited, if the Library is configured accordingly
	 */
	private function doRenewItem() {
		// Fetches params from AJAX request
		$loan           = $this->getLoan();
		$renewal_date   = $this->getPostParam('renewal_date', 'Renewal Date');
		
		// Attempts to convert renewal date to Unix timestamp
		wp_lib_convert_date($renewal_date);
		
		// Attempts to renew item, returning success
		$this->endAction(
			$loan->renewItem($renewal_date),
			get_the_title(get_post_meta($loan->ID, 'wp_lib_item', true)) . ' has been renewed'
		);
	}
	
	/**
	 * Creates a loan for testing purposes, loaning the item to a hardcoded member
	 * Created loan is automatically late by design
	 */
	private function doRunTestLoan() {
		// Fetches params from AJAX request
		$item = $this->getItem();
		
		// If debugging mode isn't enabled, stop action
		if (WP_LIB_DEBUG_MODE !== true)
			$this->stopAjax();
		
		// Validates Nonce
		$this->checkNonce('Managing Item: ' . $item->ID);
		
		// Fetches all valid (un-archived) members
		$query = NEW WP_Query(
			array(
				'post_type'     => 'wp_lib_members',
				'post_status'   => 'publish',
				'nopaging'      => true,
				'meta_query'    => array(
					array(
						'key'       => 'wp_lib_member_archive',
						'value'     => 'bug #23268', // Allows WP-Librarian to run on pre-3.9 WP installs (bug was fixed for 3.9, text is arbitrary)
						'compare'   => 'NOT EXISTS'
					)
				)
			)
		);
		
		if ($query->have_posts()){
			while ($query->have_posts()) {
				$query->the_post();
				
				$valid_members[] = get_the_ID();
			}
		} else {
			$this->stopAjax();
		}
		
		// Randomly selects member from all valid members
		$member_id = $valid_members[array_rand($valid_members,1)];
		
		$start_date = current_time('timestamp') - (10 * 24 * 60 * 60);
		$end_date = current_time('timestamp') - (3 * 24 * 60 * 60);
		
		// If possible creates loan of item starting 10 days ago, due 3 days ago (to test fining capabilities)
		$loan_id = $item->scheduleLoan($member_id, $start_date, $end_date);
		
		// If scheduling failed, stop AJAX with error that occurred
		if (!is_int($loan_id))
			$this->stopAjax($loan_id);
		
		// Creates loan object
		$loan = WP_Lib_Loan::create($this->wp_librarian, $loan_id);
		
		if ($loan->giveItem(current_time('timestamp') - (10 * 24 * 60 * 60) + 900)) {
			$this->endAction(
				true,
				"Debugging loan created"
			);
		} else {
			$this->stopAjax(601);
		}
	}
	
	/**
	 * Cancels a fine held against a member, cancelling the money owed by the member
	 */
	private function doCancelFine() {
		// Fetches params from AJAX request
		$fine = $this->getFine();
		
		// Validates Nonce
		$this->checkNonce('Managing Fine: ' . $fine->ID);
		
		// Attempts to cancel fine
		$this->endAction(
			$fine->cancel(),
			"Fine #{$fine->ID} has been cancelled"
		);
	}
	
	/**
	 * Subtracts debt from a member's total amount owed for late item return
	 * @todo Move payment functionality to functions.php, actions should only prepare data and call functions
	 */
	private function doPayFine() {
		// Fetches member and fine amount
		$member         = $this->getMember();
		$fine_payment   = floatval(isset($_POST['fine_payment']) ? $_POST['fine_payment'] : 0);
		
		// Validates Nonce
		$this->checkNonce('Pay Member Fines ' . $member->ID);
		
		// Attempts to pay fine
		$success = $member->payMoneyOwed($fine_payment);
		
		// If payment failed, send error to user
		if (wp_lib_is_error($success))
			$this->stopAjax($success);
		
		// Sets up notification for successful fine reduction
		$notification = wp_lib_format_money($fine_payment) . ' in fines has been paid by ' . get_the_title($member->ID) . '.';
		
		// Fetches new amount owed by member
		$owed = $member->getMoneyOwed();
		
		// If money is still owed by the member, inform librarian
		if ($owed != 0)
			$notification .= ' ' . wp_lib_format_money($owed) . ' is still owed.';
		
		$this->endAction(true, $notification);
	}
	
	/**
	 * Deletes a Library object and any connected objects
	 * e.g. an item and all loans/fines caused by said item
	 */
	private function doDeleteObject() {
		// Fetches library object ID
		$post_id = (int) $this->getPostParam('post_id', 'Library Item');
		
		// Fetches object type and capitalises first letter
		switch (get_post_type($post_id)){
			case 'wp_lib_items':
				$object_type = 'Item';
			break;
			
			case 'wp_lib_members':
				$object_type = 'Member';
			break;
			
			case 'wp_lib_loans':
				$object_type = 'Loan';
			break;
			
			case 'wp_lib_fines':
				$object_type = 'Fine';
			break;
			
			default:
				$this->stopAjax(303, 'Library Object');
			break;
		}
		
		// Validates Nonce
		$this->checkNonce('Deleting object: ' . $post_id);
		
		// Fetches all objects connected to current object
		$connected_objects = wp_lib_fetch_dependant_objects($post_id);
		
		// Counts objects to be deleted
		$object_count = count($connected_objects);
		
		// Adds current object
		$connected_objects[] = array($post_id, get_post_type($post_id));
		
		// Iterates over objects, checking for any items currently on loan
		// If an item is on loan then it is physically outside the Library and should not be deleted from Library records
		foreach ($connected_objects as $object) {
			switch($object[1]) {
				// Checks if item is on loan
				case 'wp_lib_items':
					$item = WP_Lib_Item::create($this->wp_librarian, $object[0]);
					
					if ($item->onLoan())
						$this->stopAjax(205);
				break;
				
				// Checks if loan is open, meaning item is outside the library
				case 'wp_lib_loans':
					if (get_post_meta($object[0], 'wp_lib_status', true) == 1)
						$this->stopAjax(205);
				break;
			}
		}
		
		// Adds objects authorised for deletion to class buffer
		$this->deletion_authorised_objects = $connected_objects;
		
		// Iterates over objects, deleting them
		foreach($connected_objects as $object) {
			wp_delete_post($object[0], true);
		}
		
		// If connected objects existed, inform user of how many were deleted, otherwise just inform user object was deleted
		if ($object_count != 0) {
			$this->endAction(
				true,
				wp_lib_plural($object_count, $object_type . ' and \v connected object\p deleted')
			);
		} else {
			$this->endAction(
				true,
				$object_type . ' deleted'
			);
		}
	}
	
	/**
	 * Attempts to repair an item with conflicting/bad data
	 * Can occur if safeguards are disabled or a bug occurs
	 */
	private function doCleanDamagedItem() {
		$item = $this->getItem();
		
		$this->endAction(
			$item->repair(),
			'Attempted repair of '.get_the_title($item->ID).' completed'
		);
	}
	
	/**
	 * Allows checked items/members/loans/fines to be deleted without WP-Librarian's integrity checks causing a problem
	 * @param   int $post_id    WP Post ID of post being deleted
	 * @return  bool            Whether post can be deleted
	 */
	public function allowPostDeletion($allow_deletion, $post_id) {
		if (in_array(array($post_id, get_post_type($post_id)), $this->deletion_authorised_objects)) {
			return true;
		} else {
			return $allow_deletion;
		}
	}
}

/**
 * Prepares a requested Dashboard page to be rendered client-side by wp_lib_render_page()
 */
class WP_Lib_AJAX_Page extends WP_Lib_AJAX {
	/**
	 * Calls relevant prep function based on requested Dash page
	 */
	function __construct(WP_Librarian $wp_librarian) {
		parent::__construct($wp_librarian);
	
		// If no dash page has been specified, load Dashboard
		if (!isset($_POST['dash_page'])) {
			$_POST['dash_page'] = 'dashboard';
		}
		
		// Allows developers to add/overwrite a specific Dash page
		do_action('wp_lib_dash_page_'.$_POST['dash_page'], $this);
		
		// Allows developers to interact with all Dash page requests
		do_action('wp_lib_dash_page', $this, $_POST['dash_page']);
		
		// Calls relevant function to prepare requested page
		switch($_POST['dash_page']) {
			case 'dashboard':
				$this->genDashboard();
			break;
			
			case 'view-items':
				$this->genViewItems();
			break;
			
			case 'manage-item':
				$this->genManageItem();
			break;
			
			case 'manage-member':
				$this->genManageMember();
			break;
			
			case 'manage-loan':
				$this->genManageLoan();
			break;
			
			case 'manage-fine':
				$this->genManageFine();
			break;
			
			case 'scan-item':
				$this->genSearchItems();
			break;
			
			case 'scheduling-page':
				$this->genScheduleLoan();
			break;
			
			case 'renew-item':
				$this->genRenewItem();
			break;
			
			case 'give-item-past':
				$this->genGiveItemPast();
			break;
			
			case 'return-past':
				$this->genReturnItemPast();
			break;
			
			case 'resolve-loan':
				$this->genLateItemResolution();
			break;
			
			case 'pay-fines':
				$this->genPayMemberFines();
			break;
			
			case 'object-deletion':
				$this->genConfirmObjectDeletion();
			break;
			
			default:
				$this->stopAjax(502);
			break;
		}
	}
	
	/**
	 * Stops Dashboard page loading based on current user circumstances
	 * @param int|WP_Lib_Error  $error      OPTIONAL ID or instance of error that occurred
	 * @param string|array      $params     OPTIONAL parameters to pass to error reporter
	 */
	public function stopAjax($error = false, $params = false) {
		if ($error !== false)
			$this->handleError($error, $params);
		
		// If this is the user's first Dash page then, to avoid user being faced with a blank Dash page, load Dashboard
		// Otherwise allows user to remain on current page and just returns error
		if (isset($_POST['ref'])) {
			parent::stopAjax();
		} else {
			// If error occurred on dashboard, call error, otherwise an infinite loop would occur
			// Otherwise load Dashboard homepage, so user can easily continue site navigation after encountering the error
			if (debug_backtrace()[1]['function'] === 'prepDashboard')
				parent::stopAjax(506);
			else
				$this->genDashboard();
		}
	}
	
	/**
	 * Adds prepared Dashboard page to content buffer then trigger AJAX request closure
	 * @param string        $page_title Title that displays on the Dash page
	 * @param string        $tab_title  Title that displays in the browser tab
	 * @param array         $page       All Dash page elements, passed to client-side wp_lib_render_page() to construct HTML
	 * @param array|null    $scripts    OPTIONAL Script names (strings) required by the Dash page
	 */
	public function sendPage($page_title, $tab_title, $page, $scripts = null) {
		$this->output_buffer[2] = array(
			apply_filters('wp_lib_dash_page_title', $page_title, $_POST['dash_page']),
			apply_filters('wp_lib_dash_tab_title',  $tab_title, $_POST['dash_page']),
			apply_filters('wp_lib_dash_page_content',       $page, $_POST['dash_page'])
		);
		
		if (is_array($scripts) || is_string($scripts))
			$this->output_buffer[2][] = $scripts;
		
		// Triggers flushing of output buffer
		die();
	}
	
	/**
	 * Prepares WordPress nonce
	 * @see                     http://codex.wordpress.org/WordPress_Nonces
	 * @param   str     $action Action to create custom nonce
	 * @return  array           Nonce as Dashboard page element
	 */
	public function prepNonce($action) {
		// Creates nonce
		$nonce = wp_create_nonce($action);
		
		// Builds and returns form field
		return array(
			'type'  => 'nonce',
			'value' => $nonce
		);
	}
	
	/**
	 * Prepares a box of details about the given item
	 * @param   WP_Lib_Item $item   Item to generate meta box for
	 * @return  array               Meta box, as a Dash page element
	 */
	private function prepItemMetaBox($item) {
		// Fetches post meta
		$meta = get_post_meta($item->ID);
		
		// Item meta fields to be displayed in management header
		$meta_fields = array(
			array('Item ID',    $item->ID),
			array('Condition',  $this->getMetaField($meta, 'wp_lib_item_condition', false, 'wp_lib_format_item_condition'))
		);
		
		// If item has a donor and the ID matches an existing member
		if (isset($meta['wp_lib_item_donor']) && wp_lib_sanitize_donor($meta['wp_lib_item_donor'][0]) !== '') {
			// Adds meta field, displaying donor with a link to manage the member
			$meta_fields[] = array(
				'Donor',
				$this->getMetaField($meta, 'wp_lib_item_donor', false, 'wp_lib_manage_member_dash_hyperlink')
			);
		}
		
		// Taxonomy terms to be fetched
		$tax_terms = array(
			'Media Type'=> 'wp_lib_media_type',
			'Author'    => 'wp_lib_author'
		);
		
		// Iterates through taxonomies, fetching their terms and adding them to the meta field array
		foreach ($tax_terms as $tax_name => $tax_key) {
			// Fetches terms for given taxonomy
			$terms = get_the_terms($item->ID, $tax_key);
			
			// If no terms or an error were returned, skip
			if (!$terms || is_wp_error($terms))
				continue;
			
			$terms_array = array();
			
			// Iterates through tax terms, formatting them
			foreach ($terms as $term) {
				// Adds tax term to term array
				$terms_array[] = array($term->name, get_term_link($term));
			}
			
			// Adds tax terms to meta fields
			$meta_fields[] = array($tax_name, $terms_array);
		}
		
		// Adds item status as last meta field
		$meta_fields[] = array('Status', $item->formattedStatus(true));
		
		// Finalises and returns management header
		return array(
			'type'      => 'metabox',
			'title'     => 'Details',
			'classes'   => 'item-man',
			'fields'    => $meta_fields
		);
	}
	
	/**
	 * Prepares a box of details about the given member
	 * @param   WP_Lib_Member   $member Member to generate meta box for
	 * @return  array                   Meta box, as a Dash page element
	 */
	private function prepMemberMetaBox($member) {
		// Fetches member meta
		$meta = get_post_meta($member->ID);
		
		// Sets up header's meta fields
		$meta_fields = array(
			array('Member ID',  $member->ID),
			array('Email',      $this->getMetaField($meta, 'wp_lib_member_email')),
			array('Phone',      $this->getMetaField($meta, 'wp_lib_member_phone')),
			array('Mobile', $this->getMetaField($meta, 'wp_lib_member_mobile')),
			array('Owed',       wp_lib_format_money($member->getMoneyOwed())),
			array('On Loan',    wp_lib_prep_members_items_out($member->ID))
		);
		
		// Finalises and returns management header
		return array(
			'type'      => 'metabox',
			'title'     => 'Details',
			'classes'   => 'member-man',
			'fields'    => $meta_fields
		);
	}

	/**
	 * Prepares a box of details about the given loan
	 * @param   WP_Lib_Loan $loan   Loan to generate meta box for
	 * @return  array               Meta box, as a Dash page element
	 */
	private function prepLoanMetaBox($loan) {
		// Fetches loan meta
		$meta = get_post_meta($loan->ID);
		
		// Formats loan status as natural language
		$status = wp_lib_format_loan_status($meta['wp_lib_status'][0]);
		
		// Adds basic loan meta fields
		$meta_fields = array(
			array('Loan ID',            $loan->ID),
			array('Item',               $this->getMetaField($meta, 'wp_lib_item', true, 'wp_lib_manage_item_dash_hyperlink')),
			array('Member',         $this->getMetaField($meta, 'wp_lib_member', true, 'wp_lib_manage_member_dash_hyperlink')),
			array('Creator',            $this->getUserName(get_post_field('post_author', $loan->ID))),
			array('Expected Start', $this->getMetaField($meta, 'wp_lib_start_date', true, 'wp_lib_format_unix_timestamp')),
			array('Expected End',       $this->getMetaField($meta, 'wp_lib_end_date', true, 'wp_lib_format_unix_timestamp')),
			array('Actual Start',       $this->getMetaField($meta, 'wp_lib_give_date', false, 'wp_lib_format_unix_timestamp')),
			array('Actual End',     $this->getMetaField($meta, 'wp_lib_return_date', false, 'wp_lib_format_unix_timestamp')),
			array('Status',         $meta['wp_lib_status'][0] === '4' ? wp_lib_prep_dash_hyperlink($status, $this->getMetaField($meta, 'wp_lib_fine', true, 'wp_lib_prep_manage_fine_params')) : $status) // If loan incurred fine, status is link to manage fine
		);
		
		// Finalises and returns management header
		return array(
			'type'      => 'metabox',
			'title'     => 'Details',
			'classes'   => 'loan-man',
			'fields'    => $meta_fields
		);
	}

	/**
	 * Prepares a box of details about the given fine
	 * @param   WP_Lib_Fine $fine   Fine to generate meta box for
	 * @return  array               Meta box, as a Dash page element
	 */
	private function prepFineMetaBox($fine) {
		// Fetches fine meta
		$meta = get_post_meta($fine->ID);
		
		// Creates and returns fine management header
		return array(
			'type'      => 'metabox',
			'title'     => 'Details',
			'classes'   => 'fine-man',
			'fields'    => array(
				array('Fine ID',    $fine->ID),
				array('Loan ID',    wp_lib_prep_dash_hyperlink($this->getMetaField($meta, 'wp_lib_loan', true), $this->getMetaField($meta, 'wp_lib_loan', true, 'wp_lib_prep_manage_loan_params'))),
				array('Item',       $this->getMetaField($meta, 'wp_lib_item', true, 'wp_lib_manage_item_dash_hyperlink')),
				array('Member', $this->getMetaField($meta, 'wp_lib_member', true, 'wp_lib_manage_member_dash_hyperlink')),
				array('Creator',    $this->getUserName(get_post_field('post_author', $fine->ID))),
				array('Amount', $this->getMetaField($meta, 'wp_lib_owed', true, 'wp_lib_format_money')),
				array('Status', $this->getMetaField($meta, 'wp_lib_status', true, 'wp_lib_format_fine_status')),
				array('Created',    get_the_date('', $fine->ID))
			)
		);
	}
	
	/**
	 * Generates table of all loans of a given item
	 * @param   int     $item_id    Post ID of item
	 * @return  array               Dynatable of loans of given item
	 */
	private function prepLoansTable($item_id) {
		$this->wp_librarian->loadHelper('dynatable');
		
		// Queries WP for all loans of item
		$dynatable = new WP_Lib_Dynatable_Loans($this, 'wp_lib_item', $item_id);
		
		// Generates Dynatable table of query results
		return $dynatable->generateTable(
			array(
				array('Loan',       'loan',     'genColumnManageLoan'),
				array('Member', 'member',   'genColumnManageMember'),
				array('Status', 'status',   'genColumnLoanStatus'),
				array('Loaned', 'loaned',   'genColumnLoanStart'),
				array('Expected',   'expected', 'genColumnLoanEnd'),
				array('Returned',   'returned', 'genColumnReturned')
			),
			array(
				'id'            => 'member-loans',
				'labels'        => array(
					'records'   => 'loans'
				)
			),
			get_the_title($item_id) . ' has never been loaned'
		);
	}
	
	/**
	 * Gets a user's name. If the user has been deleted, returns a placeholder
	 * @param   int|str $user_id    ID of WordPress user
	 * @return  str                 Name of user, or placeholder
	 */
	private function getUserName($user_id) {
		return get_the_author_meta('user_nicename', $user_id)?: '[User #'.$user_id.' Deleted]';
	}
	
	/**
	 * Displays Library Dashboard with links to manage the Library, change settings or read the documentation
	 */
	private function genDashboard() {
		// Dashboard icons
		$buttons = apply_filters('wp_lib_dash_home_buttons', array(
			array(
				'bName' => 'Scan Barcode',
				'icon'  => 'search',
				'link'  => 'dash-page',
				'value' => 'scan-item',
				'title' => 'Enter item barcode or ISBN'
			),
			array(
				'bName' => 'Manage Items',
				'icon'  => 'book-alt',
				'link'  => 'dash-page',
				'value' => 'view-items',
				'title' => 'View list of all items'
			),
			array(
				'bName' => 'Manage Members',
				'icon'  => 'admin-users',
				'link'  => 'post-type',
				'cpt'   => 'wp_lib_members',
				'title' => 'View list of all members'
			),
			array(
				'bName' => 'Manage Loans',
				'icon'  => 'upload',
				'link'  => 'post-type',
				'cpt'   => 'wp_lib_loans',
				'title' => 'View list of all loans'
			),
			array(
				'bName' => 'Manage Fines',
				'icon'  => 'carrot', // Placeholder carrot
				'link'  => 'post-type',
				'cpt'   => 'wp_lib_fines',
				'title' => 'View list of all fines'
			),
			array(
				'bName' => 'Settings',
				'icon'  => 'admin-generic',
				'link'  => 'admin-url',
				'url'   => 'edit.php?post_type=wp_lib_items&page=wp-lib-settings',
				'title' => 'Change WP-Librarian\'s settings'
			),
			array(
				'bName' => 'Help',
				'icon'  => 'editor-help',
				'link'  => 'url',
				'url'   => 'https://github.com/kittsville/WP-Librarian/wiki',
				'title' => 'Read plugin documentation'
			)
		));

		// Adds element type to each button, so it will be rendered correctly client-side
		foreach ($buttons as $key => $value) {
			$buttons[$key]['type'] = 'dash-button';
		}
		
		// Prepares Dashboard content
		$page = array(
			array(
				'type'      => 'paras',
				'content'   => 'Use the options below to manage your Library'
			),
			array(
				'type'      => 'div',
				'classes'   => 'dashboard-buttons-wrap',
				'inner'     => $buttons
			)
		);
		
		// Sets page title and browser page title
		$page_title = 'Library Dashboard';

		// Sends page to client
		$this->sendPage($page_title, $page_title, $page);
	}
	
	/**
	 * Displays list of all current items in the library
	 * @todo Move Dynatable generation to dedicated class
	 */
	private function genViewItems() {
		// Initialises page output
		$page = array();
		
		// Queries database for all valid library items
		$query = NEW WP_Query(
			array(
				'post_type'         => 'wp_lib_items',
				'post_status'       => 'publish',
				'nopaging'          => true
			)
		);
		
		// Checks if any items were returned
		if ($query->have_posts()){
			$page[] = array(
				'type'      => 'paras',
				'content'   => 'Select an item to manage it. Late items are highlighted in red.'
			);
			
			// Iterates through items
			while ($query->have_posts()) {
				$query->the_post();
				
				// Creates item object from post ID
				$item = WP_Lib_Item::create($this->wp_librarian, get_the_ID());
				
				// Sets up basic item parameters
				$item_details = array(
					'title' => get_the_title($item->ID),
					'link'  => get_permalink($item->ID)
				);
				
				// If item has a cover image, fetch url and add to item array
				if (has_post_thumbnail())
					$item_details['cover'] = wp_get_attachment_image_src(get_post_thumbnail_id($item->ID), array(300, 160));
				else
					$item_details['cover'] = false;
				
				// Fetches all authors of item
				$authors = get_the_terms($item->ID, 'wp_lib_author');
				
				// If result contains authors
				if ($authors && !is_wp_error($authors)) {
					// Iterates over authors, adding their term names to the item's author meta
					foreach ($authors as $author) {
						$item_details['authors'][] = $author->name;
					}
				} else {
					$item_details['authors'] = false;
				}
				
				// Fetches various item details
				$item_details['status']     = $item->formattedStatus(true, true);
				$item_details['item_id']    = $item->ID;
				$item_details['view']       = get_permalink();
				
				// If item is on loan, fetches if item is late
				$item_details['late'] = $item->onLoan() ? $item->getLoan()->isLate() : false;
				
				// Adds prepared item to array of all items
				$items[] = $item_details;
			}
			// Creates element that will hold all items
			$page[] = array(
				'type'      => 'item-list',
				'data'      => $items,
				'records'   => 'items'
			);
		} else {
			$page[] = array(
				'type'      => 'paras',
				'content'   => 'No items found.'
			);
		}
		
		$this->sendPage('Library Items', 'All Library Items', $page);
	}
	
	/**
	 * Displays information about the given item with links to modify the item based on its current state
	 * @todo Refactor function heavily, it hasn't been reviewed in a very long time
	 */
	private function genManageItem() {
		// Fetches item using item ID in AJAX request
		$item = $this->getItem();
		
		// Prepares the meta box
		$page[] = $this->prepItemMetaBox($item);
		
		// Adds page nonce
		$form = array($this->prepNonce('Managing Item: ' . $item->ID));
		
		// Adds item ID to form
		$form[] = array(
			'type'  => 'hidden',
			'name'  => 'item_id',
			'value' => $item->ID
		);
		
		// If debugging is enabled, add test loan creation button to every loan's page
		if (WP_LIB_DEBUG_MODE) {
			$form[] = array(
				'type'  => 'button',
				'link'  => 'action',
				'value' => 'run-test-loan',
				'html'  => 'Create Debug Entry',
				'title' => 'Create loan that is automatically late to test site functionality'
			);
		}
		
		// Fetches if item is currently on loan
		$on_loan = $item->onLoan();
		
		if ($on_loan) {
			// Fetches loan ID from Item meta
			$loan = $item->getLoan();
			
			// If item can be renewed, provided link to renew item
			if ($loan->isRenewable()) {
				$form[] = array(
					'type'  => 'button',
					'link'  => 'page',
					'value' => 'renew-item',
					'html'  => 'Renew'
				);
			}
			
			// Regardless of lateness, provides link to return item at a past date
			$form[] = array(
				'type'  => 'button',
				'link'  => 'page',
				'html'  => 'Return Past',
				'value' => 'return-past',
				'title' => 'Return item at a past date'
			);
			
			// If item is late
			if ($loan->isLate()) {
				// Provides link to resolve late item
				$form[] = array(
					'type'  => 'button',
					'link'  => 'page',
					'html'  => 'Resolve',
					'value' => 'resolve-loan',
					'title' => 'Choose whether or not to fine the user for the late return'
				);
			
			} else {
				// Provides link to return item today
				$form[] = array(
					'type'  => 'button',
					'link'  => 'action',
					'html'  => 'Return',
					'value' => 'return-item',
					'title' => 'Return item to the library'
				);
			}
		}
		elseif ($item->loanAllowed()) {
			// Creates options for loan length
			$length_options[] = array(
				'value' => '',
				'html'  => '0 Days'
			);
			
			// Creates loan length options from 1-12
			for ($i = 1; $i < 13; $i++){
				$length_options[] = array(
					'value' => $i,
					'html'  => $i . ' Days'
				);
			}
			
			$form = array_merge(
				$form,
				array(
					// Dropdown menu of possible members to loan item to
					array(
						'type'          => 'select',
						'options'       => wp_lib_prep_member_options(),
						'optionClass'   => 'member-choice-option',
						'classes'       => 'member-select',
						'name'          => 'member_id'
					),
					// Dropdown menu of loan lengths
					array(
						'type'          => 'select',
						'options'       => $length_options,
						'optionClass'   => 'loan-length-option',
						'classes'       => array('loan-length'),
						'name'          => 'loan_length'
					),
					// Button to loan item to selected member for selected number of days
					array(
						'type'  => 'button',
						'link'  => 'action',
						'value' => 'loan',
						'html'  => 'Loan Item'
					),
					// To schedule a loan, where the item is given to the member at a later date
					array(
						'type'  => 'button',
						'link'  => 'page',
						'html'  => 'Schedule Loan',
						'value' => 'scheduling-page',
						'title' => 'Schedule a loan to be fulfilled later'
					)
				)
			);
		}
		
		// Button to edit item
		$form[] = array(
			'type'  => 'button',
			'link'  => 'edit',
			'icon'  => 'edit',
			'title' => 'Edit Item'
		);
		
		// Only show item deletion button if item isn't on loan
		if (!$on_loan) {
			$form[] = array(
				'type'  => 'button',
				'link'  => 'page',
				'value' => 'object-deletion',
				'icon'  => 'trash',
				'title' => 'Delete Item'
			);
		}
		
		// Adds Dash form elements to page
		$page[] = array(
			'type'      => 'form',
			'content'   => $form
		);
		
		// Fetches list of loans of item
		$page[] = $this->prepLoansTable($item->ID);
		
		// Script to display currently selected member's meta box
		$script = $this->wp_librarian->getScriptUrl('admin-dashboard-manage-item');
		
		// Encodes page as an array to be rendered client-side
		$this->sendPage('Managing: ' . get_the_title($item->ID), 'Managing Item #' . $item->ID, $page, $script);
	}
	
	/**
	 * Displays member's details and loan history, giving means to pay any outstanding fines
	 */
	private function genManageMember() {
		// Fetches and validates member ID
		$member = $this->getMember();
		
		// Renders meta box
		$page[] = $this->prepMemberMetaBox($member);
		
		// Adds nonce to form
		$form[] = $this->prepNonce('Managing Member ' . $member->ID);
		
		// Adds member ID to form
		$form[] = array(
			'type'  => 'hidden',
			'name'  => 'member_id',
			'value' => $member->ID
		);
		
		// Fetches amount owed by member to Library
		$owed = $member->getMoneyOwed();
		
		// If money is owed by the member
		if ($owed > 0) {
			$form[] = array(
				'type'  => 'button',
				'link'  => 'page',
				'value' => 'pay-fines',
				'html'  => 'Pay Fines',
				'title' => 'Subtract money from amount owed by member'
			);
		}
		
		// Button to edit member
		$form[] = array(
			'type'  => 'button',
			'link'  => 'edit',
			'icon'  => 'edit',
			'title' => 'Edit member details'
		);
		
		// Button to delete member
		$form[] = array(
			'type'  => 'button',
			'link'  => 'page',
			'value' => 'object-deletion',
			'icon'  => 'trash',
			'title' => 'Delete member and all their loans/fines'
		);
		
		// Adds form elements to Dash page
		$page[] = array(
			'type'      => 'form',
			'content'   => $form
		);
		
		$this->wp_librarian->loadHelper('dynatable');
		
		// Queries database for loans made by member
		$dynatable = new WP_Lib_Dynatable_Loans($this, 'wp_lib_member', $member->ID);
		
		// Generates table of query results
		$page[] = $dynatable->generateTable(
			array(
				array('Loan',       'loan',     'genColumnManageLoan'),
				array('Item',       'item',     'genColumnManageItem'),
				array('Status', 'status',   'genColumnLoanStatus'),
				array('Loaned', 'loaned',   'genColumnLoanStart'),
				array('Expected',   'expected', 'genColumnLoanEnd'),
				array('Returned',   'returned', 'genColumnReturned')
			),
			array(
				'id'        => 'member-loans',
				'labels'    => array(
					'records'   => 'loans'
				)
			),
			get_the_title($member->ID) . ' has never borrowed an item'
		);
		
		$this->sendPage('Managing: ' . get_the_title($member->ID), 'Managing Member #' . $member->ID, $page);
	}
	
	/**
	 * Displays loan details and options to change the loan, if it hasn't been completed yet
	 */
	private function genManageLoan() {
		// Fetches loan ID from AJAX request
		$loan = $this->getLoan();
		
		// Fetches loan meta
		$meta = get_post_meta($loan->ID);
		
		// Renders header with useful loan information
		$page[] = $this->prepLoanMetaBox($loan);
		
		$status = $this->getMetaField($meta, 'wp_lib_status', true);
		
		// Initialises form
		$form = array(
			array(
				'type'  => 'hidden',
				'name'  => 'loan_id',
				'value' => $loan->ID
			)
		);
		
		// If item can be renewed, provided link to renew item
		if ($loan->isRenewable()) {
			$form[] = array(
				'type'  => 'button',
				'link'  => 'page',
				'value' => 'renew-item',
				'html'  => 'Renew Item'
			);
		}
		
		// Fetches current local time
		$time = current_time('timestamp');
		
		// If loan is scheduled and the loan's start date has already happened
		if ($status === '5' && $this->getMetaField($meta, 'wp_lib_start_date', true) <= $time) {
			// If loan's end date has not passed yet
			if ($time <= $this->getMetaField($meta, 'wp_lib_end_date', true)) {
				// Adds nonce so dash action will work
				$form[] = $this->prepNonce('Managing Loan: ' . $loan->ID);
				
				// Button to give item to member
				$form[] = array(
					'type'  => 'button',
					'link'  => 'action',
					'value' => 'give-item',
					'html'  => 'Loan Item'
				);
			}
			
			// Adds option to fulfil loan at a past date
			$form[] = array(
				'type'  => 'button',
				'link'  => 'page',
				'value' => 'give-item-past',
				'html'  => 'Loan at a Past Date',
				'title' => 'Mark item as having been given to the member at a past date'
			);
		
		// If item is currently on loan, provides button to return item
		} else if ($status === '1') {
			// Adds hidden element with item ID
			$form[] = array(
				'type'  => 'hidden',
				'name'  => 'item_id',
				'value' => get_post_meta($loan->ID, 'wp_lib_item', true)
			);
			
			// Adds button to return item (redirects to item management page)
			$form[] = array(
				'type'  => 'button',
				'link'  => 'page',
				'value' => 'manage-item',
				'html'  => 'Return Item',
				'title' => 'Return item to the library'
			);
		}
		
		// If loan is not open, displays delete button
		if ($status !== '1') {
			$form[] = array(
				'type'  => 'button',
				'link'  => 'page',
				'value' => 'object-deletion',
				'icon'  => 'trash',
				'title' => 'Delete loan and any connected fine'
			);
		}
		
		// Adds form to page
		$page[] = array(
			'type'      => 'form',
			'content'   => $form
		);
		
		// If loan has ever been renewed, generates table listing all times loan has been renewed
		if (isset($meta['wp_lib_renew'])) {
			// Un-serializes renewing events
			foreach ($meta['wp_lib_renew'] as $i => $renew_event) $meta['wp_lib_renew'][$i] = unserialize($renew_event);
			
			// Initialises table output and counter
			$renewings = array();
			$count = 0;
			
			// Generates table rows
			foreach($meta['wp_lib_renew'] as $renew_event) {
				++$count;
				$renewings[] = array(
					'renewedOn'     => wp_lib_format_unix_timestamp($renew_event[0]),
					'renewedUntil'  => wp_lib_format_unix_timestamp(isset($meta['wp_lib_renew'][$count]) ? $meta['wp_lib_renew'][$count][1] : $meta['wp_lib_end_date'][0]),
					'librarian'     => $this->getUserName($renew_event[2])
				);
			}
			
			// Creates table using table rows
			$page[] = array(
				'type'      => 'dtable',
				'id'        => 'loan-renewings',
				'headers'   => ['Renewed On', 'Renewed Until', 'Librarian'],
				'data'      => $renewings,
				'labels'    => array(
					'records'   => 'times renewed'
				)
			);
		}
		
		$this->sendPage('Managing: Loan #' . $loan->ID, 'Managing Loan #' . $loan->ID, $page);
	}
	
	/**
	 * Displays details of fine and provides options to cancel/pay fine
	 */
	private function genManageFine() {
		// Fetches and validates Fine ID
		$fine = $this->getFine();
		
		$page[] = $this->prepFineMetaBox($fine);
		
		// Fetches fine status
		$fine_status = get_post_meta($fine->ID, 'wp_lib_status', true);
		
		// Multiple columns to form
		$form = array(
			$this->prepNonce('Managing Fine: ' . $fine->ID),
			// Adds fine ID to form
			array(
			'type'  => 'hidden',
			'name'  => 'fine_id',
			'value' => $fine->ID
			),
			array(
				'type'      => 'paras',
				'content'   => 'Fines can be paid from the member\'s management page.'
			)
		);
		
		// If fine has not already been cancelled, allows fine to be cancelled
		if ($fine_status != 2) {
			$form[] = array(
				'type'  => 'button',
				'link'  => 'action',
				'value' => 'cancel-fine',
				'html'  => 'Cancel Fine',
				'title' => 'Mark fine as cancelled. This stops the member owing the fine\'s charge'
			);
		}
		
		// Adds deletion option
		$form[] = array(
			'type'  => 'button',
			'link'  => 'page',
			'value' => 'object-deletion',
			'icon'  => 'trash',
			'title' => 'Delete fine. Does not cancel money owed by member'
		);
		
		// Adds form to Dash page
		$page[] = array(
			'type'      => 'form',
			'content'   => $form
		);
		
		// Sends entire page to be encoded in JSON
		$this->sendPage('Managing: Fine #' . $fine->ID, 'Managing Fine #' . $fine->ID, $page);
	}
	
	/**
	 * Displays page for user to look up an item using its barcode or ISBN
	 */
	private function genSearchItems() {
		$page = array(
			array(
				'type'      => 'form',
				'content'   =>
				array(
					$this->prepNonce('Lookup Item Barcode'),
					array(
						'type'      => 'paras',
						'content'   => 'Once the barcode is scanned the item will be retried automatically'
					),
					array(
						'type'      => 'input',
						'id'        => 'barcode-input',
						'name'      => 'item_barcode',
						'attr'      => array(
							'autofocus' => true,
							'type'      => 'text'
						)
					),
					array(
						'type'  => 'button',
						'link'  => 'none',
						'id'    => 'barcode-submit',
						'value' => 'scan-barcode',
						'html'  => 'Scan'
					)
				)
			)
		);
		
		// Script to handle dynamic barcode lookup
		$script = $this->wp_librarian->getScriptUrl('admin-barcode-scanner');
		
		// Browser tab and Dashboard page title/header
		$title = 'Scan Item Barcode';
		
		// Sends form to client to be rendered
		$this->sendPage($title, $title, $page, $script);
	}
	
	/**
	 * Provides form for Library to schedule a loan to be fulfilled later
	 * Scheduled loans are fulfilled when the item is then given to the member on the appropriate date
	 */
	private function genScheduleLoan() {
		// Fetches and validates Item ID
		$item = $this->getItem();
		
		// Prepares box of useful information about the current item
		$page[] = $this->prepItemMetaBox($item);
		
		$current_time = current_time('timestamp');
		
		$page[] = array(
			'type'      => 'form',
			'content'   =>
			array(
				$this->prepNonce('Scheduling Item: ' . $item->ID),
				array(
					'type'  => 'hidden',
					'name'  => 'item_id',
					'value' => $item->ID
				),
				array(
					'type'      => 'div',
					'classes'   => array('member-select', 'manage-item'),
					'inner'     => array(
						array(
							'type'  => 'strong',
							'html'  => 'Member:',
							'label' => 'member-select'
						),
						array(
							'type'          => 'select',
							'name'          => 'member_id',
							'options'       => wp_lib_prep_member_options(),
							'optionClass'   => 'member-select-option',
							'classes'       => 'member-select'
						)
					)
				),
				array(
					'type'  => 'div',
					'inner' => array(
						array(
							'type'  => 'strong',
							'html'  => 'Start Date:',
							'label' => 'loan-start'
						),
						array(
							'type'  => 'date',
							'name'  => 'start_date',
							'id'    => 'loan-start',
							'value' => Date('Y-m-d', $current_time)
						)
					)
				),
				array(
					'type'  => 'div',
					'inner' => array(
						array(
							'type'  => 'strong',
							'html'  => 'End Date:',
							'label' => 'loan-end'
						),
						array(
							'type'  => 'date',
							'name'  => 'end_date',
							'id'    => 'loan-end',
							'value' => Date('Y-m-d', $current_time + (get_option('wp_lib_loan_length', array(12))[0] * 24 * 60 * 60))
						)
					)
				),
				array(
					'type'  => 'button',
					'html'  => 'Schedule Loan',
					'link'  => 'action',
					'value' => 'schedule'
				)
			)
		);
		
		// Fetches list of loans of item
		$page[] = $this->prepLoansTable($item->ID);
		
		// Script to display currently selected member's meta box
		$script = $this->wp_librarian->getScriptUrl('admin-dashboard-manage-item');
		
		$this->sendPage('Scheduling loan of ' . get_the_title($item->ID), 'Scheduling loan of #' . $item->ID, $page, $script);
	}
	
	/**
	 * Displays form allowing Librarian to renew item, extending its due date
	 */
	private function genRenewItem() {
		// Creates item and loan instances
		if (isset($_POST['item_id'])) {
			$item = $this->getItem();
			
			$loan = WP_Lib_Loan::create($this->wp_librarian, get_post_meta($item->ID, 'wp_lib_loan', true));
			
			// If loan ID was invalid (e.g. if item was not on loan) call error
			if (wp_lib_is_error($loan))
				$this->stopAjax($loan);
		} else {
			$loan = $this->getLoan();
			
			$item = WP_Lib_Item::create($this->wp_librarian, get_post_meta($loan->ID, 'wp_lib_item', true));
		}
		
		// Counts number of times item has already been renewed
		$renewed_count = count(get_post_meta($loan->ID, 'wp_lib_renew'));
		
		// Fetches limit to number of times item can be renewed
		$limit = (int) get_option('wp_lib_renew_limit')[0];
		
		// If item can be renewed an infinite number of times
		if ($limit === 0)
			$renewals_left = 'This item can be renewed indefinitely';
		// If item has been renewed the maxim number of times allowed (or more)
		elseif (!($limit > $renewed_count))
			$this->stopAjax(209);
		// Otherwise item has at least renewal left
		else
			$renewals_left = wp_lib_plural($limit - $renewed_count, 'This item can be renewed \v more time\p');
		
		$page[] = $this->prepItemMetaBox($item);
		
		$page[] = array(
			'type'      => 'paras',
			'content'   => array(
				$renewals_left,
				'Select when the item should now be due back:'
			)
		);
		
		$page[] = array(
			'type'      => 'form',
			'content'   => array(
				array(
					'type'  => 'hidden',
					'name'  => 'loan_id',
					'value' => $loan->ID
				),
				array(
					'type'  => 'date',
					'name'  => 'renewal_date',
					'id'    => 'item-renew-date',
					'value' => Date('Y-m-d', current_time('timestamp'))
				),
				array(
					'type'  => 'button',
					'link'  => 'action',
					'value' => 'renew-item',
					'html'  => 'Renew Item'
				)
			)
		);
		
		$page[] = $this->prepLoansTable($item->ID);
		
		$this->sendPage(
			'Renewing Item: ' . get_the_title($item->ID),
			'Renewing Item #' . $item->ID,
			$page
		);
	}
	
	/**
	 * Displays form to allow Librarian to mark an item as having left the Library at a previous date
	 */
	private function genGiveItemPast() {
		// Fetches and validates Loan ID
		$loan = $this->getLoan();
		
		// Fetches loan meta
		$meta = get_post_meta($loan->ID);
		
		// Checks if loan has correct status to be allowed to 
		if ($this->getMetaField($meta, 'wp_lib_status', true) !== '5' || $this->getMetaField($meta, 'wp_lib_start_date', true) > current_time('timestamp')) {
			$this->stopAjax(322);
		}
		
		$this->sendPage(
			'Managing: Loan #' . $loan->ID,     // Page title
			'Managing Loan #' . $loan->ID,      // Tab title
			array(
				$this->prepLoanMetaBox($loan),// Prepares box with useful information about the loan
				array(
					'type'      => 'form',
					'content'   =>
					array(
						$this->prepNonce('Give item past. loan ID: ' . $loan->ID),
						array(
							'type'  => 'hidden',
							'name'  => 'loan_id',
							'value' => $loan->ID
						),
						array(
							'type'      => 'paras',
							'content'   => 'Enter date that item was given to member. In future do not release the item from the Library without recording it first.'
						),
						array(
							'type'  => 'date',
							'name'  => 'give_date',
							'id'    => 'loan-give-date',
							'value' => Date('Y-m-d', $meta['wp_lib_start_date'][0])
						),
						array(
							'type'  => 'button',
							'link'  => 'action',
							'value' => 'give-item',
							'html'  => 'Loan Item'
						)
					)
				)
			)
		);
	}
	
	/**
	 * Provides a form to choose when in the past to return an item
	 */
	private function genReturnItemPast() {
		// Fetches item ID from AJAX request
		$item = $this->getItem();

		// Checks if item is on loan
		if (!$item->onLoan())
			$this->stopAjax(402);
		
		$this->sendPage(
			'Returning: ' . get_the_title($item->ID),   // Page Title
			'Returning item #' . $item->ID,             // Tab Title
			array(
				$this->prepItemMetaBox($item),// Prepares box with useful details about the item
				array(
					'type'      => 'form',
					'content'   =>
					array(
						$this->prepNonce('Past returning: ' . $item->ID),
						array(
							'type'  => 'hidden',
							'name'  => 'item_id',
							'value' => $item->ID
						),
						array(
							'type'  => 'div',
							'inner' => array(
								array(
									'type'  => 'strong',
									'html'  => 'Date:',
									'label' => 'loan-end-date'
								),
								array(
									'type'  => 'date',
									'name'  => 'end_date',
									'id'    => 'loan-end-date',
									'value' => Date('Y-m-d', current_time('timestamp'))
								)
							)
						),
						array(
							'type'  => 'button',
							'link'  => 'action',
							'value' => 'return-item',
							'html'  => 'Return'
						)
					)
				)
			)
		);
	}
	
	/**
	 * Provides options to remedy an item that should have been returned earlier
	 */
	private function genLateItemResolution() {
		// Fetches loan ID using item ID
		$item = $this->getItem();
		
		// Checks if item is actually on loan
		if (!$item->onLoan())
			$this->stopAjax(214);
		
		// Fetches item ID from loan meta
		$loan = $item->getLoan();
		
		// Ensures item is actually late
		if (!$loan->isLate())
			$this->stopAjax(406);
		
		$date = current_time('timestamp');
		
		$days_late              = $loan->formatDueDate($date, array('late' => '\d day\p'));         // Formatted string of item lateness
		$title                  = get_the_title($item->ID);                                         // Item's title
		$fine_per_day           = get_option('wp_lib_fine_daily', array(0))[0];                     // Librarian set charge for each day an item is late
		$late                   = -$loan->cherryPie($date);                                         // Days item is late
		$fine                   = wp_lib_format_money($fine_per_day * $late);                           // Total fine member member is facing, if charged
		$fine_per_day_formatted = wp_lib_format_money($fine_per_day);                                   // Fine per day formatted
		$member_name            = get_the_title(get_post_meta($item->ID, 'wp_lib_member', true));   // Member's name
		
		$this->sendPage(
			'Resolving Late Item: ' . $title,
			'Resolving Item #' . $item->ID,
			array(
				$this->prepItemMetaBox($item),
				array(
					'type'      => 'form',
					'content'   =>
					array(
						$this->prepNonce('Resolution of item ' . $item->ID . ' for loan '. $loan->ID),
						array(
							'type'  => 'hidden',
							'name'  => 'item_id',
							'value' => $item->ID
						),
						array(
							'type'      => 'paras',
							'content'   => "{$title} is late by {$days_late}. If fined, {$member_name} would incur a fine of {$fine} ({$fine_per_day_formatted} per day x {$days_late})."
						),
						array(
							'type'  => 'button',
							'link'  => 'action',
							'value' => 'fine-member',
							'html'  => 'Fine',
							'title' => 'Charge member given fine amount and return item'
						),
						array(
							'type'  => 'button',
							'link'  => 'action',
							'value' => 'return-item-no-fine',
							'html'  => 'Return with no Fine',
							'title' => 'Return item without fining user'
						),
						array(
							'type'  => 'button',
							'link'  => 'page',
							'value' => 'manage-item',
							'html'  => 'Cancel',
							'title' => 'Go back to item management page'
						)
					)
				),
				$this->prepLoansTable($item->ID)
			)
		);
	}
	
	/**
	 * Allows Library to reduce fines owed by member by paying part/all owed
	 */
	private function genPayMemberFines() {
		$member = $this->getMember();
		
		if ($member->getMoneyOwed() <= 0)
			$this->stopAjax(206);
		
		$this->sendPage(
			'Managing: ' . get_the_title($member->ID),
			'Managing Member #' . $member->ID,
			array(
				$this->prepMemberMetaBox($member),
				array(
					'type'      => 'form',
					'content'   =>
					array(
						array(
							'type'      => 'paras',
							'content'   => 'Enter an amount to reduce the member\'s total owed to the Library'
						),
						array(
							'type'  => 'hidden',
							'name'  => 'member_id',
							'value' => $member->ID
						),
						$this->prepNonce('Pay Member Fines ' . $member->ID),
						array(
							'type'          => 'input',
							'name'          => 'fine_payment',
							'attr'          => array(
								'type'          => 'number',
								'placeholder'   => wp_lib_format_money(0, false),
								'step'          => '0.05'
							)
						),
						array(
							'type'  => 'button',
							'link'  => 'action',
							'value' => 'pay-fine',
							'html'  => 'Pay'
						)
					)
				)
			)
		);
	}
	
	/**
	 * Checks for confirmation for deleting an object in the Library
	 * Also displays all objects connected to the current object
	 */
	private function genConfirmObjectDeletion() {
		// Looks for post ID in POST fields. Multiple fields are checked because user can come to page from many different places
		foreach(['post_id','item_id','member_id','loan_id','fine_id'] as $key) {
			// If field exists, fetch object ID from field
			if (isset($_POST[$key])) {
				$post_id = $_POST[$key];
				break;
			}
		}
		
		// If no ID field was set, call error
		if (!isset($post_id))
			$this->stopAjax(314, 'Object ID');
		
		// Renders page title and details based on if object is item/member/loan/fine
		switch(get_post_type($post_id)) {
			case 'wp_lib_items':
				// Creates item instance from item ID
				$item = WP_Lib_Item::create($this->wp_librarian, $post_id);
			
				// If item is on loan, call error
				if ($item->onLoan())
					$this->stopAjax(205);
				
				// Renders meta box, displaying useful information about the item
				$page[] = $this->prepItemMetaBox($item);
				
				// Sets titles of Dash page and browser tab
				$page_title = 'Deleting: ' . get_the_title($post_id);
				$tab_title  = 'Deleting Item #' . $post_id;
				
				// Informs user of implications of deletion
				$page[] = array(
					'type'      => 'paras',
					'content'   => array(
						'Deleting items is a permanent action. Any loans or fines dependant on this member will be deleted as well.',
						'If you want to delete items in bulk, without this prompt, allow bulk deletion via WP-Librarian\'s settings'
					)
				);
			break;
			
			case 'wp_lib_members':
				// Creates member instance from member ID
				$member = WP_Lib_Member::create($this->wp_librarian, $post_id);
			
				// Renders meta box, displaying useful information about the member
				$page[] = $this->prepMemberMetaBox($member);
				
				// Sets dash page title and tab title
				$page_title = 'Deleting: ' . get_the_title($post_id);
				$tab_title  = 'Deleting Member #' . $post_id;
				
				// Informs user of implications of deletion
				$page[] = array(
					'type'      => 'paras',
					'content'   => array(
						'Deleting a member is a permanent action. You can choose to also delete all loans/fines dependant on this member',
						'If you want to delete members in bulk, without this prompt, allow bulk deletion via WP-Librarian\'s settings'
					)
				);
			break;
			
			case 'wp_lib_loans':
				// Creates loan instance from loan ID
				$loan = WP_Lib_Loan::create($this->wp_librarian, $post_id);
				
				// If loan is open (item is outside Library), call error
				if (get_post_meta($post_id, 'wp_lib_status', true) == 1)
					$this->stopAjax(205);
				
				// Renders meta box, displaying useful information about the loan
				$page[] = $this->prepLoanMetaBox($loan);
				
				// Sets titles of Dash page and browser tab
				$page_title = 'Deleting: Loan #' . $post_id;
				$tab_title  = 'Deleting Loan #' . $post_id;
				
				// Informs user of implications of deletion
				$page[] = array(
					'type'      => 'paras',
					'content'   => array(
						'Deleting a loan is a permanent action. Any fines dependant on this loan will also be deleted.',
						'If you want to delete loans in bulk, without this prompt, allow bulk deletion via WP-Librarian\'s settings'
					)
				);
			break;
			
			case 'wp_lib_fines':
				// Creates fine instance from fine ID
				$fine = WP_Lib_Fine::create($this->wp_librarian, $post_id);
			
				// Renders meta box, displaying useful information about the fine
				$page[] = $this->prepFineMetaBox($fine);
				
				// Sets titles of Dash page and browser tab
				$page_title = 'Deleting: Fine #' . $post_id;
				$tab_title  = 'Deleting Fine #' . $post_id;
				
				// Informs user of implications of deletion
				$page[] = array(
					'type'      => 'paras',
					'content'   => array(
						'Deleting a fine is a permanent action and will result in the deletion of any loan dependant on this fine',
						'To remove any money owed by the member because of this fine, cancel the fine first',
						'If you want to delete fines in bulk, without this prompt, allow bulk deletion via WP-Librarian\'s settings'
					)
				);
			break;
			
			default:
				$this->stopAjax(303, 'object');
			break;
		}
		
		// Adds page nonce, object ID and relevant buttons to form
		$page[] = array(
			'type'      => 'form',
			'content'   =>
			array(
				$this->prepNonce('Deleting object: ' . $post_id),
				array(
					'type'  => 'hidden',
					'name'  => 'post_id',
					'value' => $post_id
				),
				array(
					'type'      => 'button',
					'link'      => 'action',
					'value'     => 'delete-object',
					'classes'   => 'dash-button-danger',
					'html'      => 'Delete'
				),
				array(
					'type'  => 'button',
					'link'  => 'page',
					'value' => 'dashboard',
					'html'  => 'Cancel',
					'title' => 'Cancel deletion and return to Dashboard home'
				)
			)
		);
		
		// Looks for all objects connected to the current one (e.g. loans by a member, or fines as a result of a loan)
		$connected_objects = wp_lib_fetch_dependant_objects($post_id);
		
		if ($connected_objects) {
			$page[] = array(
				'type'  => 'header',
				'size'  => 4,
				'html'  => wp_lib_plural(count($connected_objects), 'Dependant object\p:')
			);
			
			// Iterates over connected objects, creating table rows for each object
			foreach ($connected_objects as $connected_object) {
				// Adds table row with post ID and link to manage loan/fine
				switch($connected_object[1]) {
					case 'wp_lib_loans':
						$objects[] = array(
							'id'    => wp_lib_manage_loan_dash_hyperlink($connected_object[0]),
							'type'  => 'Loan'
						);
					break;
					
					case 'wp_lib_fines':
						$objects[] = array(
							'id'    => wp_lib_manage_fine_dash_hyperlink($connected_object[0]),
							'type'  => 'Fine'
						);
					break;
				}
			}
			
			// Creates table using objects
			$page[] = array(
				'type'      => 'dtable',
				'id'        => 'connected-objects',
				'headers'   => array(
					'ID',
					'Type'
				),
				'data'      => $objects,
				'labels'    => array(
					'records'   => 'dependant objects'
				)
			);
		} else {
			$page[] = array(
				'type'      => 'paras',
				'content'   => 'No other objects in the Library are dependant on this object'
			);
		}
		
		$this->sendPage($page_title, $tab_title, $page);
	}
}

/**
 * Handles Dashboard API requests, providing useful information to dynamically enhance Dash pages
 */
class WP_Lib_AJAX_API extends WP_Lib_AJAX {
	/**
	 * Adds result of API request to content buffer, to be returned to user, then triggers AJAX request closure
	 * @param mixed $data Content to be returned to client
	 */
	private function sendData($data) {
		$this->output_buffer[2] = $data;
		
		// Triggers parent destructor, rendering output buffer to page as JSON
		die();
	}
	
	/**
	 * Performs API request and returns relevant information to client
	 */
	function __construct(WP_Librarian $wp_librarian) {
		parent::__construct($wp_librarian);
		
		// If no request has been given, return error
		if (!isset($_POST['api_request']))
			$this->stopAjax(504);
		
		// Allows developers to add/overwrite a specific Dash API request
		do_action('wp_lib_dash_api_'.$_POST['api_request'], $this);
		
		// Allows developers to interact with all Dash API requests
		do_action('wp_lib_dash_api', $this, $_POST['api_request']);
		
		// Performs action based on request
		switch($_POST['api_request']) {
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
				$this->stopAjax(504);
			break;
		}
	}
	
	/**
	 * Creates a meta box with useful details for the given member ID
	 */
	private function getMemberMetaBox() {
		// Fetches member ID from AJAX request
		$member = $this->getMember();
		
		// Fetches member meta
		$meta = get_post_meta($member->ID);
		
		// Sets up header's meta fields
		$meta_fields = array(
			array('Name',       get_the_title($member->ID)),
			array('Email',      $this->getMetaField($meta, 'wp_lib_member_email')),
			array('On Loan',    wp_lib_prep_members_items_out($member->ID)),
			array('Owed',       wp_lib_format_money($member->getMoneyOwed()))
		);
		
		// Finalises and returns member meta box
		$this->sendData(array(
			'type'      => 'metabox',
			'title'     => 'Member Details',
			'classes'   => 'member-man',
			'fields'    => $meta_fields
		));
	}
	
	/**
	 * Fetches barcode field settings and returns them
	 */
	private function getBarcodePageSettings() {
		$settings = get_option('wp_lib_barcode_config', false);
		
		// If setting are invalid, run settings integrity check
		// Otherwise sends settings to user
		if ($settings === false) {
			$this->wp_librarian->loadHelper('settings');
			WP_Lib_Settings::checkPluginSettingsIntegrity();
			
			$this->stopAjax();
		} else {
			$this->sendData($settings);
		}
	}
	
	/**
	 * Looks up given barcode and returns ID of corresponding item, or error if no/more than one item exists
	 */
	private function getSearchItemByBarcode(){
		if (isset($_POST['code']))
			$barcode = $_POST['code'];
		else
			stopAjax(318);
		
		// Attempts to sanitize barcode as an ISBN
		$isbn = wp_lib_sanitize_isbn($barcode);
		
		// If sanitization fails, assumes given value is a barcode
		if ($isbn === '') {
			// If barcode is zero, invalid barcode was given
			if (!ctype_digit($barcode))
			$this->stopAjax(318);
			
			$meta_query = array(
				'key'       => 'wp_lib_item_barcode',
				'value'     => $barcode,
				'compare'   => 'IN'
			);
		} else {
			$meta_query = array(
				'key'       => 'wp_lib_item_isbn',
				'value'     => $isbn,
				'compare'   => 'IN'
			);
		}

		// Looks for post(s) with barcode
		$query = new WP_Query(array(
			'post_type'     => 'wp_lib_items',
			'post_status'   => 'publish',
			'nopaging'      => true,
			'meta_query'    => array(
				$meta_query
			)
		));
		
		// Checks number of posts found
		$posts_found = $query->found_posts;
		
		// If an item was found
		if ($posts_found == 1) {
			$query->the_post();
			
			// Return item ID
			$this->sendData(get_the_ID());
		} elseif ($posts_found > 1) {
			// If multiple items have said barcode, call error
			$this->stopAjax(204);
		} else {
			// If no items were found, call error
			$this->stopAjax(319);
		}
	}
}
