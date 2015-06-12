<?php
// No direct loading
defined('ABSPATH') OR die('No');

/**
 * Represents the loan of an item to a member
 * Loaded Automatically: NO
 */
class WP_Lib_Loan extends WP_Lib_Object {
	/**
	 * Creates new instance of a loan from its post ID
	 * @param   WP_Librarian                $wp_librarian   Single instance of core plugin class
	 * @param   int                         $loan_id        Post ID of a loan
	 * @return  WP_Lib_Loan||WP_Lib_Error                   Instance of class or, if error occurred, error class
	 */
	public static function create(WP_Librarian $wp_librarian, $loan_id) {
		return parent::initObject($wp_librarian, $loan_id, __class__, 'wp_lib_loans', 'Loan');
	}
	
	/**
	 * Marks item as having left the library
	 * @param   int|bool            $date       OPTIONAL Date (as UNIX timestamp) to give item to member on, deafaults to WP's current_time()
	 * @return  bool|WP_Lib_Error               Success/failure of giving item to member
	 */
	public function giveItem($date = false) {
		// Sets date to current time if not set
		wp_lib_prep_date($date);
		
		// Fetches loan meta
		$meta = get_post_meta($this->ID);
		
		// Checks to make sure loan is currently scheduled
		if ($meta['wp_lib_status'][0] !== '5')
			return wp_lib_error(211);
		
		// Checks to make sure item is allowed to be loaned
		if (!(WP_Lib_Item::create($this->wp_librarian, $meta['wp_lib_item'][0])->loanAllowed()))
			return wp_lib_error(213);
		
		// If proposed give date isn't between loan's start/end dates, call error
		if ($date < $meta['wp_lib_start_date'][0] || $date > $meta['wp_lib_end_date'][0])
			return wp_lib_error(310);
		
		/* Updates item and loan meta */
		
		// Updates loan status from 'Scheduled' to 'On Loan'
		update_post_meta($this->ID, 'wp_lib_status', 1);
		
		// Sets date item was loaned
		wp_lib_add_meta($this->ID,
			array(
				'wp_lib_give_date'  => $date,                       // Date/time item is passed to member
				'wp_lib_give_user'  => get_current_user_id()        // Librarian who marks item as being given
			)
		);
		
		wp_lib_add_meta($meta['wp_lib_item'][0],
			array(
				'wp_lib_member'     => $meta['wp_lib_member'][0],   // Assigns item to member to signify the physical item is in their possession
				'wp_lib_loan'       => $this->ID                    // Adds loan ID to item meta as caching, until item returns to Library possession
			)
		);

		return true;
	}
	
	/**
	 * Checks if given item would be late at given time
	 * @param   int|bool            $date   OPTIONAL Date to base item lateness calculations on, uses current time by default
	 * @return  bool|WP_Lib_Error           If item is/would be late on given date
	 */
	public function isLate($date = false) {
		// Sets date to current time if unspecified
		wp_lib_prep_date($date);
		
		// Fetches number of days late
		$late = $this->cherryPie($date);
		
		// If cherry pie failed, return error that occurred
		if (wp_lib_is_error($late))
			return $late;
		
		// Function returns if item is late as boolean if $boolean is set to true
		if ($late < 0)
			return true;
		else
			return false;
	}
	
	/**
	 * Calculates days until an item needs to be returned
	 * It's the damn fine cherry pie function! amon.jpg
	 * @param   int                 $date   Date to base days left calculation. Use WP's current_time() to base calculation on current date
	 * @return  int|WP_Lib_Error            Days until item needs to be returned, value is negative if item is late
	 */
	public function cherryPie($date) {
		// If date hasn't been given, uses current time
		wp_lib_prep_date($date);
		
		// Fetches item due date from loan meta
		$due_date   = get_post_meta($this->ID, 'wp_lib_end_date', true);
		
		// Converts strings to DateTime objects
		$due_date   = DateTime::createFromFormat('U', $due_date);
		$date       = DateTime::createFromFormat('U', $date);
		
		// Difference between loan's due date and given or current date is calculated
		$diff       = $date->diff($due_date, false);
		
		$sign       = $diff->format('%R');
		$days       = $diff->format('%a');
		
		// If the due date is the date given, return 0
		if ($days === 0)
			return 0;
		// If the item is not due back yet, return positive number
		elseif ($sign === '+')
			return $days;
		// If the item is late, return negative number
		elseif ($sign === '-')
			return -$days;
		// If the result has no sign, return error
		else
			return wp_lib_error(110);
	}
	
	/**
	 * Creates formatted string specifying the days until an item is due back
	 * e.g. 'this item is \d day\p late' --> 'this item is 4 days late'
	 * @param   int|bool        $date       OPTIONAL Date to generate item's lateness from
	 * @param   array           $formatting Array containing formatting details
	 * @return  str|WP_Lib_Error            Item's lateness in readable string e.g. this item is 4 days late
	 * @todo                                Consider using plural() and generally assess for optimisation
	 */
	public function formatDueDate($date = false, $formatting) {
		// Use cherry pie to get due/late
		$due = $this->cherryPie($date);
		
		// If item is due today
		if ($due == 0)
			$text = $formatting['today'];
		// If item is due, but not today
		elseif ($due > 0)
			$text = str_replace('\d', $due, $formatting['due']);
		// If item is late
		elseif ($due < 0)
			$text = str_replace('\d', -$due, $formatting['late']);
		// If cherry pie failed, call error
		else
			return wp_lib_error(212);
		
		// If $due value isn't plural, '\p' is removed      
		if ($due == 1 || $due == -1)
			$text = str_replace('\p', '', $text);
		// If $due value is plural, '\p' is replaced with 's' 
		else
			$text = str_replace('\p', 's', $text);
		
		// Formatted string is returned
		return $text;
	}
	
	/**
	 * Checks if item can be renewed
	 * @param   bool                $display_errors Whether to generate and return an error on failure
	 * @return  bool|WP_Lib_Error                   If item is eligible for renewal
	 */
	public function isRenewable($display_errors = false) {
		$meta = get_post_meta($this->ID);
		
		// If loan is not currently open, call error
		if ($meta['wp_lib_status'][0] !== '1') {
			return $display_errors ? wp_lib_error(208) : false;
		}
		
		// If item has been renewed already
		if (isset($meta['wp_lib_renew'])) {
			// Fetches limit to number of times an item can be renewed
			$limit = (int) get_option('wp_lib_renew_limit')[0];
			
			// If renewing limit is not infinite and item has reached the limit, call error
			if ($limit !== 0 && !($limit > count($meta['wp_lib_renew']))) {
				return $display_errors ? wp_lib_error(209) : false;
			}
		}
		
		return true;
	}
	
	/**
	 * Renews an item currently on loan, giving the member more time before they have to return it
	 * @param   int|bool            $date   OPTIONAL Date to extend loan's due date to, defaults to WP's current_time()
	 * @return  bool|WP_Lib_Error           Success/failure of function
	 */
	public function renewItem($date = false) {
		wp_lib_prep_date($date);
		
		// Checks if item is eligible for renewal, allowing error to be generated on failure
		$renewable = $this->isRenewable(true);
		
		// If item is not renewable
		if (wp_lib_is_error($renewable))
			return $renewable;
		
		$meta = get_post_meta($this->ID);
		
		// Ensures renewal due date is after current due date
		if (!($date > $meta['wp_lib_end_date'][0])) {
			return wp_lib_error(323);
		}
		
		// Creates item object from item ID in loan meta
		$item = WP_Lib_Item::create($this->wp_librarian, $meta['wp_lib_item'][0]);
		
		// Creates list of all loans of item, including future scheduled loans
		$loans = $item::createLoanIndex();
		
		// Removes current loan from loan index
		// This is so that is doesn't interferer with itself during the next check
		$loans = array_filter($loans, function($loan){
			return ($loan['loan_id'] === $this->ID);
		});
		
		// Checks if loan can be extended by checking if 'new' loan would not clash with existing loans, minus current loan
		// Calls error on failure
		if (wp_lib_no_loan_conflict($meta['wp_lib_start_date'][0], $date, $loans)) {
			// Adds new renewal entry, containing the renewal date, the previous loan due date and the librarian who is renewing the item
			add_post_meta($this->ID, 'wp_lib_renew', array(current_time('timestamp'), (int) $meta['wp_lib_end_date'][0], get_current_user_id()));
			
			update_post_meta($this->ID, 'wp_lib_end_date', $date);
			
			return true;
		} else {
			return wp_lib_error(210);
		}
	}
	
	/**
	 * Returns an item that was on loan to the library, charging a fine if appropriate
	 * @param   int|bool                $date           OPTIONAL Date (as UNIX timestamp) to return item, defaults to WP's current_time()
	 * @param   bool                    $charge_fine    If to fine member if the item is late
	 * @return  int|bool|WP_Lib_Error                   Success/failure of function, or fine's post ID if fine was charged
	 */
	public function returnItem($date = false, $charge_fine = null) {
		// Sets date to current date, if unspecified
		wp_lib_prep_date($date);
		
		// Fetches loan meta
		$meta = get_post_meta($this->ID);
		
		// Checks if date is in the future
		if ($date > current_time('timestamp'))
			return wp_lib_error(310);
		
		// Checks if item as actually on loan
		if ($meta['wp_lib_status'][0] !== '1')
			return wp_lib_error(409);
		
		// Checks that return date isn't before item was loaned
		if ($meta['wp_lib_give_date'][0] > $date)
			return wp_lib_error(324);
		
		// Finds out how many days before item is due (negative is item is late)
		$due_in = $this->cherryPie($date);
		
		// If error occurred when calculating item lateness, return said error
		if (wp_lib_is_error($due_in))
			return $due_in;
		
		// If item is late
		if ($due_in < 0) {
			switch ($charge_fine) {
				// If a fine has been allowed
				case true:
					// Creates the fine, a custom post type that holds useful meta about the fine
					$fine_id = wp_insert_post(array(
						'post_status'       => 'publish',
						'post_type'         => 'wp_lib_fines'
					));
					
					// If fine creation failed, call error
					if ($fine_id === 0) {
						return wp_lib_error(407);
					}
					
					// Due in -5 days == 5 days late
					$days_late = -$due_in;
					
					// Fetches daily charge for a late item
					$daily_fine = get_option('wp_lib_fine_daily', array(0))[0];
					
					// Calculates fine based off days late * charge per day
					$fine_amount = $days_late * $daily_fine;
					
					// Fetches member from loan meta
					$member_id = $meta['wp_lib_member'][0];
					
					// Saves information relating to fine to its post meta
					wp_lib_update_meta($fine_id,
						array(
							'wp_lib_item'   => $meta['wp_lib_item'][0],
							'wp_lib_loan'   => $this->ID,
							'wp_lib_member' => $member_id,
							'wp_lib_status' => 1,
							'wp_lib_owed'   => $fine_amount
						)
					);
					
					// Saves fine ID to loan meta
					add_post_meta($this->ID, 'wp_lib_fine', $fine_id);
					
					do_action('wp_lib_fine_created', $fine_id, $meta['wp_lib_item'][0], $member_id, $this);
				break;
				
				// If fine has been wavered, allow late return with no fine
				case false: break;
				
				// Fine has not been specifically wavered or allowed, call error
				default:
					return wp_lib_error(410);
				break;
			}
		}

		// Deletes member and loan IDs from item meta, representing the physical item passing from the member's possession back to the Library
		delete_post_meta($meta['wp_lib_item'][0], 'wp_lib_member');
		delete_post_meta($meta['wp_lib_item'][0], 'wp_lib_loan');

		// Loan status is set according to if:
		// Item was returned late and a fine was charged
		if ($charge_fine)
			$status = 4;
		// Item was returned late but a fine was not charged
		elseif ($late)
			$status = 3;
		// Item was returned on time
		else
			$status = 2;
		
		// Sets loan status
		update_post_meta($this->ID, 'wp_lib_status', $status);

		// Date item was returned to library is set. Note that the end_date is when the the item was scheduled to be returned
		add_post_meta($this->ID, 'wp_lib_return_date', $date);
		
		// Adds ID of librarian who returned item to loan meta
		add_post_meta($this->ID, 'wp_lib_return_user', get_current_user_id());
		
		// Returns fine ID is fine was charged or true if fine was wavered/not needed
		return isset($fine_id)?: true;
	}
}
