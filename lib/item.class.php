<?php
// No direct loading
defined('ABSPATH') OR die('No');

/**
 * Represents a single library item, with methods to loan/return/etc. said item
 * Loaded Automatically: NO
 */
class WP_LIB_ITEM extends WP_LIB_OBJECT {
	/**
	 * Creates new instance of an item from its post ID
	 * @param	WP_LIBRARIAN			$wp_librarian	Single instance of core plugin class
	 * @param	int						$item_id		Post ID of an item
	 * @return	WP_LIB_ITEM|WP_LIB_ERROR				Instance of class or, if error occurred, error class
	 */
	public static function create(WP_LIBRARIAN $wp_librarian, $item_id) {
		return parent::initObject($wp_librarian, $item_id, __class__, 'wp_lib_items', 'Item');
	}
	
	/**
	 * Checks if item item is scheduled to be on loan between given dates
	 * Given no dates, checks if item is currently on loan
	 * @param	int|bool	$start_date	OPTIONAL Start of date range to check if item is on loan between
	 * @param	int|bool	$end_date	OPTIONAL End of date range to check if item is on loan between
	 * @return	bool					If item is on loan currently or between given dates
	 * @todo							Improve checking of start/end date
	 */
	public function onLoan($start_date = false, $end_date = false) {
		// If dates weren't given then the schedule doesn't need to be checked
		// The simpler method of checking the item for an assigned member can be used
		if ( $start_date === false || $end_date === false ) {
			// Fetches all members assigned to item
			$loan_already = get_post_meta($this->ID, 'wp_lib_member', true);

			// If a member is not assigned to the item (meta value is an empty string) then item is not on loan
			return ($loan_already !== '') ? true : false;
		}
		
		// Fetches all loans assigned to item
		$loans = $this::createLoanIndex($this->ID);
		
		// If item has no loans, it'll be available regardless of date
		if (!$loans)
			return false;
		
		// Runs scheduling engine to check for conflicts. If engine returns true, no conflicts exist.
		return !wp_lib_recursive_scheduling_engine($start_date, $end_date, $loans);
	}
	
	/**
	 * Checks if item is allowed to be loaned
	 * @see http://tvtropes.org/pmwiki/pmwiki.php/Main/ExactlyWhatItSaysOnTheTin
	 */
	public function loanAllowed() {
		// Checks item's 'loan allowed' meta to see if item has been authorised for loaning
		return (get_post_meta($this->ID, 'wp_lib_item_loanable', true) === "1" ? true : false);
	}
	
	/**
	 * Checks if item is allowed to be loaned and won't be on loan between given dates
	 * @param	int|bool	$start_date	OPTIONAL Start of date range to check if item is on loan between
	 * @param	int|bool	$end_date	OPTIONAL End of date range to check if item is on loan between
	 * @return	bool					If the proposed loan would be allowed
	 * @todo							Force start/end date to be passed to be function
	 */
	public function loanPossible($start_date = false, $end_date = false) {
		if ($this->loanAllowed() && !$this->onLoan($start_date, $end_date))
			return true;
		else
			return false;
	}
	
	/**
	 * Generates formatted string of item's current availability
	 * @param	bool	$no_url		Whether or not item status is a hyperlink to manage the item
	 * @param	bool	$short		Whether or not to display the full item status
	 * @return	string				A string of the item's current loan availability
	 */
	public function formattedStatus($no_url = false, $short = false) {
		// Checks if the current user was the permissions of a Librarian
		$is_librarian = wp_lib_is_librarian();
		
		// Fetches if item is currently on loan
		$on_loan = $this->onLoan();
		
		// If item can be loaned and is available, url is made to take user to loans Dashboard to loan item
		if (!$on_loan && $this->loanAllowed()) {
			$status = 'Available';
		// If item is on loan link is composed to return item
		} elseif ($on_loan) {
			// Sets item status accordingly
			$status = 'On Loan';
			
			// Checks if user has permission to see full details of current loan
			if ($is_librarian) {
				// If user wants full item status, member that item is loaned to is fetched
				if (!$short) {
					$status .= ' to ' . get_the_title(get_post_meta($this->ID, 'wp_lib_member', true));
				}
				
				$args = array(
				'due'	=> 'due in \d day\p',
				'today'	=> 'due today',
				'late'	=> '\d day\p late',
				);
				
				$status .= ' (' . $this->getLoan()->formatDueDate(false, $args) . ')';
			}
		}
		// If item isn't allowed to be loaned item is marked as unavailable
		else {
			$status = 'Unavailable';
		}
		
		// If user has the relevant permissions, availability will contain link to manage item
		if ($is_librarian && !$no_url)
			return wp_lib_hyperlink(wp_lib_manage_item_url($this->ID), $status);
		else
			return $status;
	}
	
	/**
	 * Creates chronologically ordered list of loans associated with an item
	 * @return array Ordered list of item's loans
	 */
	public function createLoanIndex() {
		// Searches post table for all loans of the item. Note that loans are returned in creation order which isn't necessarily loan start/end order
		$query = NEW WP_Query(array(
			'post_type'		=> 'wp_lib_loans',
			'post_status'	=> 'publish',
			'nopaging'		=> true,
			'meta_query'	=> array(
				array(
					'key'		=> 'wp_lib_item',
					'value'		=> $this->ID,
					'compare'	=> 'IN'
				)
			)
		));
		
		if ($query->have_posts()) {
			// Initialises output
			$loan_index = array();
			
			// Iterates through loans
			while ($query->have_posts()) {
				// Selects current post (loan)
				$query->the_post();
				
				// Fetches loan meta
				$meta = get_post_meta(get_the_ID());
				
				// Sets start date to date item was given to member, falls back to scheduled start date
				if (isset($meta['wp_lib_give_date']))
					$start_date = $meta['wp_lib_give_date'][0];
				else
					$start_date = $meta['wp_lib_start_date'][0];
				
				// Sets end date to date item was returned, falls back to scheduled end date
				if (isset($meta['wp_lib_return_date']))
					$end_date = $meta['wp_lib_return_date'][0];
				else
					$end_date = $meta['wp_lib_end_date'][0];
				
				// Adds loan index entry
				$loan_index[] = array(
					'start'		=> (int) $start_date,
					'end'		=> (int) $end_date,
					'loan_id'	=> get_the_ID()
				);
			}
			
			// Sorts array by start/end date rather than post creation order
			usort($loan_index, function($a, $b) {
				if ($a['start'] == $b['start'])
					return 0;
				
				return ($a['start'] > $b['start']) ? 1 : -1;
			});
			
			// Returns loan index
			return $loan_index;
		} else {
			// If item has never been loaned, return empty array
			return array();
		}
	}
	
	/**
	 * Fetches loan ID of current loan item is on, or loan that was in progress on a given date
	 * @param	int|bool		 $date	OPTIONAL Date (as UNIX timestamp) to check for loan on. Uses current time if unspecified
	 * @return	int|WP_LIB_ERROR		Loan ID on success, error on failure
	 */
	public function getLoanId($date = false) {
		// If a date hasn't been given, assume loan is in progress
		if ($date === false) {
			// Fetches loan ID from item metadata
			return (int) get_post_meta($this->ID, 'wp_lib_loan', true);
		} else {
			// Fetches item loan index
			$loans = $this::createLoanIndex();
			
			// If $loans is empty or the given date is after the last loan ends, call error
			if (!$loans || end($loans)['end'] <= $date) {
				return wp_lib_error(302);
			}
				
			// Searches loan index for loan that matches $date
			foreach ($loans as $loan) {
				if ($loan['start'] <= $date && $date <= $loan['end']) {
					return (int) $loan['loan_id'];
				}
			}
		}
	}
	
	/**
	 * Fetches loan ID of current loan item is on, or 
	 * @param	int|bool					$date	OPTIONAL Date (as UNIX timestamp) to check for loan on. Uses current time if unspecified
	 * @return	WP_LIB_LOAN|WP_LIB_ERROR			Item's current loan as object on success, library error on failure
	 */
	public function getLoan($date = false) {
		// Fetches loan ID
		$loan_id = $this->getLoanId($date);
		
		// Attempts to create loan object from loan ID
		$loan = WP_LIB_LOAN::create($this->wp_librarian, $loan_id);
		
		// If loan ID was invalid, attempt to repair item and return error
		if (wp_lib_is_error($loan)) {
			$this->repair;
			return wp_lib_error(403);
		} else {
			return $loan;
		}
	}
	
	/**
	 * Loans given item to given member for the given number of days
	 * Uses schedule_loan() and give_item() to achieve most functionality
	 * @param	int					$member_id		Post ID of member who is loaning the item
	 * @param	int|bool			$loan_length	OPTIONAL Number of days loan should last, uses default loan length is unspecified
	 * @return	bool|WP_LIB_ERROR					True on success, an error on failure
	 */
	function loanItem($member_id, $loan_length = false) {
		// Sets start date to current date
		$start_date = current_time('timestamp');
		
		// If loan length wasn't given, use default loan length
		if ($loan_length === false)
			$loan_length = get_option('wp_lib_loan_length', array(12))[0];
		// If loan length is not a positive integer, call error
		elseif (!ctype_digit($loan_length))
			return wp_lib_error(311);

		// Sets end date to current date + loan length
		$end_date = $start_date + ($loan_length * 24 * 60 * 60);
		
		// Schedules loan, returns loan's ID on success or error on failure
		$loan_id = $this->scheduleLoan($member_id, $start_date, $end_date);
		
		// If scheduling function failed, return failure (schedule_loan will have already called errors)
		if (wp_lib_is_error($loan_id))
			return $loan_id;
		
		// Creates loan object
		$loan = WP_LIB_LOAN::create($this->wp_librarian, $loan_id);
		
		// Attempts to pass item to member and returns success/failure
		return $loan->giveItem() === true ?: wp_lib_error(411);
	}
	
	/**
	 * Schedules a loan of an item to a member
	 * For an item to be marked as having left the library use give_item after calling this function
	 * @param	int					$member_id	Post ID of member to whom the loan will be
	 * @param	int					$start_date	Date proposed loan will start
	 * @param	int					$end_date	Date proposed loan will end
	 * @return	int|WP_LIB_ERROR				New loan's post ID on success, error on failure
	 */
	public function scheduleLoan($member_id, $start_date, $end_date) {
		// Checks if member is allowed to be loaned items
		if (get_post_meta($member_id, 'wp_lib_member_archive', true))
			return wp_lib_error(316);
		
		// Checks if item can actually be loaned
		if (!$this->loanPossible($start_date, $end_date))
			return wp_lib_error(401);
		
		// Creates the loan, a custom post type that holds useful meta about the loan
		$loan_id = wp_insert_post(array(
			'post_status'		=> 'publish',
			'post_type'			=> 'wp_lib_loans'
		));
		
		// If loan was not successfully created, call error
		if ($loan_id === 0) {
			return wp_lib_error(400);
		}
		
		// Saves important information about the fine to its post meta
		wp_lib_update_meta($loan_id,
			array(
				'wp_lib_item'		=> $this->ID,
				'wp_lib_member'		=> $member_id,
				'wp_lib_start_date'	=> $start_date,
				'wp_lib_end_date'	=> $end_date,
				'wp_lib_status'		=> 5
			)
		);
		
		return $loan_id;
	}
	
	/**
	 * Clears all item meta marking it as on loan. Used to fix a corrupted item
	 * This function should not be called under regular operation and should definitely not used to return an item
	 */
	public function repair(){
		delete_post_meta($this->ID, 'wp_lib_loan');
		delete_post_meta($this->ID, 'wp_lib_member');
	}
}
