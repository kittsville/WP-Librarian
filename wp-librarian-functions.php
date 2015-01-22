<?php
/**
 * WP-LIBRARIAN FUNCTIONS
 * Various functions used to display or modify parts of the Library.
 * These functions use numerous helpers from wp-librarian-helpers.php
 * and rely on post types and taxonomies set up in wp-librarian.php
 */

// No direct loading
defined( 'ABSPATH' ) OR die('No');

/**
 * Checks if item item is scheduled to be on loan between given dates
 * Given no dates, checks if item is currently on loan
 * @param	int			$item_id	Post ID of library item to be checked
 * @param	int|bool	$start_date	OPTIONAL Start of date range to check if item is on loan between
 * @param	int|bool	$end_date	OPTIONAL End of date range to check if item is on loan between
 * @return	bool					If item is on loan currently or between given dates
 * @todo							Improve checking of start/end date
 */
function wp_lib_on_loan( $item_id, $start_date = false, $end_date = false ) {
	// If dates weren't given then the schedule doesn't need to be checked
	// The simpler method of checking the item for an assigned member can be used
	if ( $start_date === false || $end_date === false ) {
		// Fetches all members assigned to item
		$loan_already = get_post_meta( $item_id, 'wp_lib_member', true );

		// If a member is not assigned to the item (meta value is an empty string) then item is not on loan
		$loan_already = ( $loan_already !== '' ? true : false );
		
		return $loan_already;
	}
	
	// Fetches all loans assigned to item
	$loans = wp_lib_create_loan_index( $item_id );
	
	// If item has no loans, it'll be available regardless of date
	if ( !$loans )
		return false;
	
	// Runs scheduling engine to check for conflicts. If engine returns true, no conflicts exist.
	return !wp_lib_recursive_scheduling_engine( $start_date, $end_date, $loans );
}

// Checks if item is allowed to be loaned
// tvtropes.org/pmwiki/pmwiki.php/Main/ExactlyWhatItSaysOnTheTin
function wp_lib_loan_allowed( $item_id ) {
	// Fetches item's meta for if loan is allowed
	$loan_allowed = get_post_meta( $item_id, 'wp_lib_item_loanable', true );
	
	//Sanitizes $loan_allowed to boolean
	$loan_allowed = ( $loan_allowed == "1" ? true : false );
	
	return $loan_allowed;
}

// Checks if item is allowed to be loaned and not on loan between given dates
function wp_lib_loanable( $item_id, $start_date = false, $end_date = false ) {
	if ( wp_lib_loan_allowed( $item_id ) && !wp_lib_on_loan( $item_id, $start_date, $end_date ) )
		return true;
	else
		return false;
}

/**
 * Given an array of loans, checks if a proposed loan would be viable
 * Use create_loan_index to generate and sort the ordered loan index necessary for the function
 * @param	int		$proposed_start	Proposed start of new loan as a UNIX timestamp
 * @param	int		$proposed_end	Proposed end of new loan as a UNIX timestamp
 * @param	array	$loans			List of existing loans, ordered chronologically. Can't be empty array
 * @param	int		$current		Current position in array being checked by recursive_scheduling_engine()
 * @return	bool					Whether the proposed loan would be viable
 * @todo							Move to dedicated class or create wrapper than handles empty $loans cases
 */
function wp_lib_recursive_scheduling_engine( $proposed_start, $proposed_end, $loans, $current = 0 ) {
	// Creates key for previous and next loans, regardless of if they exist
	$previous = $current - 1;
	$next = $current + 1;
	
	// Checks if a loan exists before current loan, if so then there is a gap to be checked for suitability
	if ( isset($loans[$previous]) ) {
		// If the proposed loan starts after the $previous loan ends and ends before the $current loan starts, then the proposed loan would work
		if ( $proposed_start > $loans[$previous]['end'] && $proposed_end < $loans[$current]['start'] )
			return true;
	}
	// Otherwise $current loan is earliest loan, so if proposed loan ends before $current loan starts, proposed loan would work
	elseif ( $proposed_end < $loans[$current]['start'] )
		return true;
	
	// Checks if a loan exists after the $current loan, if so then function calls itself on the next loan
	if ( isset($loans[$next]) )
		return wp_lib_recursive_scheduling_engine( $proposed_start, $proposed_end, $loans, $next );
	
	// Otherwise $current loan is last loan, so if proposed loan starts after $current loan ends, proposed loan would work
	elseif ( $proposed_start > $loans[$current]['end'] )
		return true;
	
	// If this statement is reached, all loans have been checked and no viable gap has been found, so proposed loan will not work
	return false;
}

/**
 * Creates chronologically ordered list of loans associated with an item
 * @param	int			$item_id	Post ID of library item to be checked
 * @return	array					Ordered list of item's loans
 */
function wp_lib_create_loan_index( $item_id ) {
	// Initialises output
	$loan_index = array();
	
	// Sets all query params
	$args = array(
		'post_type'		=> 'wp_lib_loans',
		'post_status'	=> 'publish',
		'meta_query'	=> array(
			array(
				'key'		=> 'wp_lib_item',
				'value'		=> $item_id,
				'compare'	=> 'IN'
			)
		)
	);
	
	// Searches post table for all loans of the item. Note that loans are returned in creation order which isn't necessarily loan start/end order
	$query = NEW WP_Query( $args );
	
	if ( $query->have_posts() ) {
		// Iterates through loans
		while ( $query->have_posts() ) {
			// Selects current post (loan)
			$query->the_post();
			
			// Fetches loan meta
			$meta = get_post_meta( get_the_ID() );
			
			// Sets start date to date item was given to member, falls back to scheduled start date
			if ( isset( $meta['wp_lib_give_date'] ) )
				$start_date = $meta['wp_lib_give_date'][0];
			else
				$start_date = $meta['wp_lib_start_date'][0];
			
			// Sets end date to date item was returned, falls back to scheduled end date
			if ( isset( $meta['wp_lib_return_date'] ) )
				$end_date = $meta['wp_lib_return_date'][0];
			else
				$end_date = $meta['wp_lib_end_date'][0];
			
			// Adds loan index entry
			$loan_index[] = array(
				'start'		=> (int)$start_date,
				'end'		=> (int)$end_date,
				'loan_id'	=> get_the_ID()
			);
		}
		
		// Sorts array by start/end date rather than post creation order
		usort( $loan_index, function( $a, $b ) {
			if ( $a['start'] == $b['start'])
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
 * Calculates days until an item needs to be returned
 * It's the damn fine cherry pie function! amon.jpg
 * @param	int 	$loan_id	Post ID of a loan that is currently open (item is with member)
 * @param	int		$date		Date to base days left calculation. Use WP's current_time() to base calculation on current date
 * @return	int					Days until item needs to be returned, value is negative if item is late
 */
function wp_lib_cherry_pie( $loan_id, $date ) {
	// Fetches item due date from loan meta
	$due_date = get_post_meta( $loan_id, 'wp_lib_end_date', true );
	
	// If loan doesn't have a due date, error is thrown
	if ( !is_numeric( $due_date ) ) {
		wp_lib_error( 405 );
		return false;
	}

	// Converts strings to DateTime objects
	$due_date = DateTime::createFromFormat( 'U', $due_date);
	$date = DateTime::createFromFormat( 'U', $date);
	
	// Difference between loan's due date and given or current date is calculated
	$diff = $date->diff( $due_date, false );
	
	$sign = $diff->format( '%R' );
	$days = $diff->format( '%a' );
	
	// If the due date is the date given, return 0
	if ( $days == 0 )
		return 0;
	
	// If the item is not due back yet, return positive number
	elseif ( $sign == '+' )
		return $days;
		
	// If the item is late, return negative number
	elseif ( $sign == '-' )
		return -$days;
	// If the result has no sign, return error
	else {
		wp_lib_error( 110 );
		return false;
	}
}

/**
 * Checks if given item would be late at given time
 * @param	int			$item_id	Post ID of library item to be checked
 * @param	int|bool	$date		OPTIONAL Date to base item lateness calculations on, uses current time by default
 * @return	bool					If item is/would be late on given date
 */
function wp_lib_item_late( $loan_id, $date = false ) {
	// Sets date to current time if unspecified
	wp_lib_prep_date( $date );
	
	// Fetches number of days late
	$late = wp_lib_cherry_pie( $loan_id, $date );
	
	// If cherry pie failed, kill thread
	if ( $late === false )
		die();
	
	// Function returns if item is late as boolean if $boolean is set to true
	if ( $late < 0 )
		return true;
	else
		return false;
}

/**
 * Creates formatted string specifying item lateness
 * e.g. 'this item is \d day\p late' --> 'this item is 4 days late'
 * @param	int			$item_id	Post ID of library item to be checked
 * @param	int|bool	$date		OPTIONAL Date to generate item's lateness from
 * @param	array		$array		Array containing formatting details
 * @return	str						Item's lateness in readable string e.g. this item is 4 days late
 * @todo							Consider using plural() and generally assess for optimisation
 */
function wp_lib_prep_item_due( $item_id, $date = false, $array ) {
	// Sets date to current time if unspecified
	wp_lib_prep_date( $date );

	// If item isn't on loan, return an empty string
	if ( !wp_lib_on_loan( $item_id ) )
		return '';
	
	// Fetch loan ID from item meta
	$loan_id = wp_lib_fetch_loan_id( $item_id );
	
	// Use cherry pie to get due/late
	$due = wp_lib_cherry_pie( $loan_id, $date );
	
	// If item is due today
	if ( $due == 0 )
		$text = $array['today'];
	// If item is due, but not today
	elseif ( $due > 0 )
		$text = str_replace( '\d', $due, $array['due'] );
	// If item is late
	elseif ( $due < 0 )
		$text = str_replace( '\d', -$due, $array['late'] );
	// If cherry pie failed, kill execution
	else
		die();
	
	// If $due value isn't plural, '\p' is removed		
	if ( $due == 1 || $due == -1 )
		$text = str_replace( '\p', '', $text );
	// If $due value is plural, '\p' is replaced with 's' 
	else
		$text = str_replace( '\p', 's', $text );
	
	// Formatted string is returned
	return $text;
}

/**
 * Fetches loan ID of current loan item is on, or 
 * @param	int	$item_id	Post ID of item from which loan will be fetched
 * @param	int	$date		OPTIONAL Date (as UNIX timestamp) to check for loan on. Uses current time if unspecified
 * @return	int|bool		Loan's post ID on success, false on failure
 */
function wp_lib_fetch_loan_id( $item_id, $date = false ) {
	// If a date hasn't been given, assume loan is in progress
	if ( $date == false ) {
		// Fetches loan ID from item metadata
		$loan_id = get_post_meta( $item_id, 'wp_lib_loan', true );

	} else {
		// Fetches item loan index
		$loans = wp_lib_create_loan_index( $item_id );
		
		// If $loans is empty or the given date is after the last loan ends, call error
		if ( !$loans || end( $loans )['end'] <= $date ) {
			wp_lib_error( 302 );
			return false;
		}
			
		// Searches loan index for loan that matches $date
		foreach ( $loans as $loan ) {
			if ( $loan['start'] <= $date && $date <= $loan['end'] ) {
				$loan_id = $loan['loan_id'];
				break;
			}
		}
	}
	
	// Validates loan ID
	if ( !is_numeric( $loan_id ) ) {
		wp_lib_error( 402 );
		return false;
	}

	// Checks if loan with that ID actually exists
	if ( get_post_status( $loan_id ) == false ) {
		wp_lib_clean_item( $item_id );
		wp_lib_error( 403 );
		return false;
	}
	
	return $loan_id;
}

/**
 * Loans given item to given member for the given number of days
 * Uses schedule_loan() and give_item() to achieve most functionality
 * @param	int			$item_id		Post ID of item to loan to member
 * @param	int			$member_id		Post ID of member who is loaning the item
 * @param	int|bool	$loan_length	OPTIONAL Number of days loan should last, uses default loan length is unspecified
 * @return	bool						Success of function
 */
function wp_lib_loan_item( $item_id, $member_id, $loan_length = false ) {
	// Sets start date to current date
	$start_date = current_time( 'timestamp' );
	
	// If loan length wasn't given, use default loan length
	if ( $loan_length === false )
		$loan_length = get_option( 'wp_lib_loan_length', array(12) )[0];
	// If loan length is not a positive integer, call error
	elseif ( !ctype_digit( $loan_length ) ) {
		wp_lib_error( 311 );
		return false;
	}

	// Sets end date to current date + loan length
	$end_date = $start_date + ( $loan_length * 24 * 60 * 60);
	
	// Schedules loan, returns loan's ID on success
	$loan_id = wp_lib_schedule_loan( $item_id, $member_id, $start_date, $end_date );
	
	// If scheduling function failed, return failure (schedule_loan will have already called errors)
	if ( $loan_id === false )
		return $loan_id;
	
	// Passes item to member then checks for success
	if ( !wp_lib_give_item( $loan_id ) ) {
		wp_lib_error( 411 );
		return false;
	}
	
	return true;
}

/**
 * Schedules a loan of an item to a member
 * For an item to be marked as having left the library use give_item after calling this function
 * @param	int			$item_id	Post ID of item for which will be scheduled
 * @param	int			$member_id	Post ID of member to whom the loan will be
 * @param	int			$start_date	Date proposed loan will start
 * @param	int			$end_date	Date proposed loan will end
 * @return	int|bool				New loan's post ID on success, false on failure
 */
function wp_lib_schedule_loan( $item_id, $member_id, $start_date, $end_date ) {
	// Checks if member is allowed to be loaned items
	if ( get_post_meta( $member_id, 'wp_lib_member_archive', true ) ) {
		wp_lib_error( 316 );
		return false;
	}
	
	// Checks if item can actually be loaned
	if ( !wp_lib_loanable( $item_id, $start_date, $end_date ) ) {
		wp_lib_error( 401 );
		return false;
	}
	
	// Creates arguments for loan
	$args = array(
		'post_status'		=> 'publish',
		'post_type'			=> 'wp_lib_loans'
	);
	
	// Creates the loan, a custom post type that holds useful meta about the loan
	$loan_id = wp_insert_post( $args );
	
	// If loan was not successfully created, call error
	if ( $loan_id === 0 ) {
		wp_lib_error( 400 );
		return false;
	}
	
	// Saves important information about the fine to its post meta
	wp_lib_update_meta( $loan_id,
		array(
			'wp_lib_item'		=> $item_id,
			'wp_lib_member'		=> $member_id,
			'wp_lib_start_date'	=> $start_date,
			'wp_lib_end_date'	=> $end_date,
			'wp_lib_status'		=> 5
		)
	);
	
	return $loan_id;
}

/**
 * Marks item as having left the library
 * @param	int			$loan_id	Post ID of loan of item to member
 * @param	int|bool	$date		OPTIONAL Date (as UNIX timestamp) to give item to member on, deafaults to WP's current_time()
 * @return	bool					Success/failure of giving item to member
 */
function wp_lib_give_item( $loan_id, $date = false ) {
	// Sets date to current time if not set
	wp_lib_prep_date( $date );
	
	// Checks to make sure loan is currently scheduled
	if ( get_post_meta( $loan_id, 'wp_lib_status', true ) !== '5' ) {
		wp_lib_error( 211 );
		return false;
	}
	
	// Fetches item and member IDs from loan meta
	$item_id = get_post_meta( $loan_id, 'wp_lib_item', true );
	$member_id = get_post_meta( $loan_id, 'wp_lib_member', true );
	
	/* Updates other meta */
	
	// Updates loan status from 'Scheduled' to 'On Loan'
	update_post_meta( $loan_id, 'wp_lib_status', 1 );
	
	// Sets date item was loaned
	wp_lib_add_meta( $loan_id,
		array(
			'wp_lib_give_date'	=> $date,				// Date/time item is passed to member
			'wp_lib_give_user'	=> get_current_user_id()// Librarian who marks item as being given
		)
	);
	
	wp_lib_add_meta( $item_id,
		array(
			'wp_lib_member'	=> $member_id,	// Assigns item to member to signify the physical item is in their possession
			'wp_lib_loan'	=> $loan_id		// Adds loan ID to item meta as caching, until item returns to Library possession
		)
	);

	return true;
}

/**
 * Returns an item that was on loan to the library, charging a fine if appropriate
 * @param	int			$item_id	Post ID of item being returned
 * @param	int|bool	$date		OPTIONAL Date (as UNIX timestamp) to return item, defaults to WP's current_time()
 * @param	bool		$fine		If to fine member if the item is late
 * @return	int|bool				Success/failure of function, or fine's post ID if fine was charged
 */
function wp_lib_return_item( $item_id, $date = false, $fine = null ) {
	// Sets date to current date, if unspecified
	wp_lib_prep_date( $date );
	
	// Checks if date is in the future
	if ( $date > current_time( 'timestamp' ) ) {
		wp_lib_error( 310 );
		return false;
	}
	
	// Fetches loan ID from item meta
	$loan_id = wp_lib_fetch_loan_id( $item_id );
	
	// If loan ID fetching failed, return failure (error will have been generated by fetch_loan_id)
	if ( $loan_id === false )
		return false;
	
	// Checks if item as actually on loan
	if ( get_post_meta( $loan_id, 'wp_lib_status', true ) != 1 ) {
		wp_lib_error( 409 );
		return false;
	}
	
	// Finds out how many days before item is due (negative is item is late)
	$due_in = wp_lib_cherry_pie( $loan_id, $date );
	
	// If item is late
	if ( $due_in < 0 ) {
		switch ( $fine ) {
			// If a fine has been allowed
			case true:
				// Creates arguments for fine
				$args = array(
					'post_status'		=> 'publish',
					'post_type'			=> 'wp_lib_fines'
				);
				
				// Creates the fine, a custom post type that holds useful meta about the fine
				$fine_id = wp_insert_post( $args );
				
				// If fine creation failed, call error
				if ( $fine_id === 0 ) {
					wp_lib_error( 407 );
					return false;
				}
				
				// Due in -5 days == 5 days late
				$days_late = -$due_in;
				
				// Fetches daily charge for a late item
				$daily_fine = get_option( 'wp_lib_fine_daily', array(0) )[0];
				
				// Calculates fine based off days late * charge per day
				$fine = $days_late * $daily_fine;
				
				// Fetches member object from item tax
				$member_id = get_post_meta( $item_id, 'wp_lib_member', true );
				
				// Saves information relating to fine to its post meta
				wp_lib_update_meta( $fine_id,
					array(
						'wp_lib_item'	=> $item_id,
						'wp_lib_loan'	=> $loan_id,
						'wp_lib_member'	=> $member_id,
						'wp_lib_status'	=> 1,
						'wp_lib_fine'	=> $fine
					)
				);
				
				// Saves fine ID to loan meta
				add_post_meta( $loan_id, 'wp_lib_fine', $fine_id );
				
				// Fetches member's current fine total and adds fine to it
				$fine_total = wp_lib_fetch_member_owed( $member_id ) + $fine;
				
				// Saves new total to member meta
				update_post_meta( $member_id, 'wp_lib_owed', $fine_total );
			break;
			
			// If fine has been wavered, allow late return with no fine
			case false: break;
			
			// Fine has not been specifically wavered or allowed, call error
			default:
				wp_lib_error( 410 );
				return false;
			break;
		}
	}

	// Deletes member ID from item meta, representing the physical item passing from the member's possession to the Library
	delete_post_meta( $item_id, 'wp_lib_member' );

	// Removes loan ID from item meta
	delete_post_meta( $item_id, 'wp_lib_loan' );

	// Loan status is set according to if:
	// Item was returned late and a fine was charged
	if ( $fine )
		$status = 4;
	// Item was returned late but a fine was not charged
	elseif ( $late )
		$status = 3;
	// Item was returned on time
	else
		$status = 2;
	
	// Sets loan status
	update_post_meta( $loan_id, 'wp_lib_status', $status );

	// Date item was returned to library is set. Note that the end_date is when the the item was scheduled to be returned
	add_post_meta( $loan_id, 'wp_lib_return_date', $date );
	
	// Adds ID of librarian who returned item to loan meta
	add_post_meta( $loan_id, 'wp_lib_return_user', get_current_user_id() );
	
	// Returns fine ID is fine was charged or true if fine was wavered/not needed
	return isset( $fine_id ) ? $fine_id : true;
}

/**
 * Renews an item currently on loan, giving the member more time before they have to return it
 * @param	int			$loan_id		Post ID of loan for which due date is being extended
 * @param	int|bool	$date			OPTIONAL Date to extend loan's due date to, defaults to WP's current_time()
 * @return	bool						Success/failure of function
 */
function wp_lib_renew_item( $loan_id, $date = false ) {
	wp_lib_prep_date( $date );
	
	$meta = get_post_meta( $loan_id );
	
	// If loan is not currently open, call error
	if ( $meta['wp_lib_status'][0] !== '1' ) {
		wp_lib_error( 208 );
		return false;
	}
	
	// If item has been renewed already
	if ( isset($meta['wp_lib_renew']) ) {
		// Fetches limit to number of times an item can be renewed
		$limit = (int) get_option( 'wp_lib_renew_limit' )[0];
		
		// If renewing limit is not infinite and item has reached the limit, call error
		if ( $limit !== 0 && !( $limit > count($meta['wp_lib_renew']) ) ) {
			wp_lib_error( 209 );
			return false;
		}
	}
	
	// Ensures renewal due date is after current due date
	if (!( $date > $meta['wp_lib_end_date'][0] )) {
		wp_lib_error( 323 );
		return false;
	}
	
	// Creates list of all loans of item, including future scheduled loans
	$item_loans = wp_lib_create_loan_index( $meta['wp_lib_item'][0] );
	
	// Removes current loan from loan index
	// This is so that is doesn't interferer with itself during the next check
	$item_loans = array_filter( $item_loans, function($loan){
		return ( $loan['loan_id'] === $loan_id );
	});
	
	// Checks if loan can be extended by checking if 'new' loan would not clash with existing loans, minus current loan
	// Calls error on failure
	if ( wp_lib_recursive_scheduling_engine( $meta['wp_lib_start_date'][0], $date, $item_loans ) ) {
		// Adds new renewal entry, containing the renewal date, the previous loan due date and the librarian who is renewing the item
		add_post_meta( $loan_id, 'wp_lib_renew', array( current_time('timestamp'), (int)$meta['wp_lib_end_date'][0], get_current_user_id() ) );
		
		update_post_meta( $loan_id, 'wp_lib_end_date', $date );
		
		return true;
	} else {
		wp_lib_error( 210 );
		return false;
	}
}

/**
 * Cancels fine, removing fine amount from member's total debt
 * @param	int 	$fine_id	Post ID of fine to be cancelled
 * @return	bool				Success/failure of fine cancellation
 */
function wp_lib_cancel_fine( $fine_id ) {
	// Fetches (unformatted) fine status
	$fine_status = get_post_meta( $fine_id, 'wp_lib_status', true );

	// If fine has already been cancelled, calls error
	if ( $fine_status == 2 ) {
		wp_lib_error( 313 );
		return false;
	}
	
	// Fetches member ID
	$member_id = get_post_meta( $fine_id, 'wp_lib_member', true );
	
	// Fetches current amount owed by member
	$owed = wp_lib_fetch_member_owed( $member_id );
	
	// Fetches fine total
	$fine_total = get_post_meta( $fine_id, 'wp_lib_fine', true );
	
	// If cancelling fine would leave member with negative money owed, call error
	if ( $owed - $fine_total < 0 ) {
		wp_lib_error( 207 );
		return false;
	}
	
	// Removes fine from member's debt
	$owed -= $fine_total;
	
	// Updates member debt
	update_post_meta( $member_id, 'wp_lib_owed', $owed );

	// Changes fine status to Cancelled
	update_post_meta( $fine_id, 'wp_lib_status', 2 );
	
	return true;
}

/**
 * Generates error based on given error code and, if not an AJAX request, kills thread
 * @param int			$error_id	Error that has occurred
 * @param string|array	$param		OPTIONAL Relevant parameters to error to enhance error message (not optional for certain error messages)
 */
function wp_lib_error( $error_id, $param = null ) {
	wp_lib_load_helper('error');
	
	return new WP_LIB_ERROR( $error_id, $param );
}
?>