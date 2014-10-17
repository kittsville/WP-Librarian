<?php
/*
 * WP-LIBRARIAN FUNCTIONS
 * Various functions used to display or modify parts of the Library.
 * These functions use numerous helpers from wp-librarian-helpers.php
 * and rely on post types and taxonomies set up in wp-librarian.php
 */

// Checks if user has permission to loan/return books
function wp_lib_is_librarian() {
	return true;
}

// Checks if item will be on loan between given dates. Given no dates, checks if item is currently on loan
function wp_lib_on_loan( $item_id, $start_date = false, $end_date = false ) {
	// If dates weren't given then the schedule doesn't need to be checked
	// The simpler method of checking the item for an assigned member can be used
	if ( !( $start_date || $end_date ) ) {
		// Fetches all members assigned to item
		$loan_already = get_post_meta( $item_id, 'wp_lib_member', true );

		// If wp_lib_member is not assigned and thus returns false, then item is not on loan
		$loan_already = ( $loan_already == false ? false : true );
		
		return $loan_already;	
	}
	
	// Fetches all loans assigned to item
	$loans = wp_lib_fetch_loan_index( $item_id );

	// If item has no loans, it'll be available regardless of date
	if ( !$loans )
		return false;

	// Runs scheduling engine to check for conflicts. If engine returns loan ID/string, conflict exists
	if ( wp_lib_recursive_scheduling_engine( $start_date, $end_date, $loans ) )
		return true;
		
	// Otherwise no loan exists during given time frame
	return false;
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

// Looks in the gaps between ranges (loan dates) to see if the proposed loan would fit there.
// Returns array key where loan would fit between two loans, or start/end if loan is after/before all loans
function wp_lib_recursive_scheduling_engine( $proposed_start, $proposed_end, $loans, $current = 0 ) {
	// Creates key for previous and next loans, regardless of if they exist
	$previous = $current - 1;
	$next = $current + 1;

	// Checks if a loan exists before current loan, if so then there is a gap to be checked for suitability
	if ( $loans[$previous] ) {
		// If the proposed loan starts after the $previous loan ends and ends before the $current loan starts, then the proposed loan would work
		if ( $proposed_start > $loans[$previous]['end'] && $proposed_end < $loans[$current]['start'] )
			return $current;
	}
	// Otherwise $current loan is earliest loan, so if proposed loan ends before $current loan starts, proposed loan would work
	elseif ( $proposed_end < $loans[$current]['start'] )
		return 'start';
	
	// Checks if a loan exists after the $current loan, if so then function calls itself on the next loan
	if ( $loans[$next] )
		return wp_lib_recursive_scheduling_engine( $proposed_start, $proposed_end, $loans, $next );
	
	// Otherwise $current loan is last loan, so if proposed loan starts after $current loan ends, proposed loan would work
	elseif ( $proposed_start > $loans[$current]['end'] )
		return 'end';
	
	// If this statement is reached, all loans have been checked and no viable gap has been found, so proposed loan will not work
	return false;
}

// Fetches loan index from item meta and handles errors
function wp_lib_fetch_loan_index( $item_id ) {
	// Fetches loans
	$loans = get_post_meta( $item_id, 'wp_lib_loan_index', true );
	
	// If item has no loans, changes to blank array to avoid errors
	if ( $loans == '' )
		$loans = array();
		
	return $loans;
}

// Searches loan index for loan ID, returns position of loan in array
function wp_lib_fetch_loan_position( $loan_index, $loan_id ) {
	// Iterates through array, checking each loan's ID for a match
	foreach ( $loan_index as $key => $loan ) {
		if ( $loan['loan_id'] == $loan_id )
			return $key;	
	}
	// If the whole index has been searched and the value has not been found, returns false
	return false;
}

// Calculates days until item needs to be returned, returns negative if item is late
function wp_lib_cherry_pie( $loan_id, $date ) {
	// Fetches item due date from loan meta
	$due_date = get_post_meta( $loan_id, 'wp_lib_end_date', true );
	
	// If loan doesn't have a due date, error is thrown
	if ( $due_date == '' || !is_numeric( $due_date ) ) {
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

// Function checks if item is late and returns true if so
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

// Formats item's days late/due
// Array is expected, containing late/due/today key/values with \d and \p for due and plural values
// e.g. 'this item is \d day\p late' --> 'this item is 4 days late'
function wp_lib_prep_item_due( $item_id, $date = false, $array ) {
	// Sets date to current time if unspecified
	wp_lib_prep_date( $date );

	// If item isn't on loan, return an empty string
	if ( !wp_lib_on_loan( $item_id ) )
		return '';
	
	// Fetch loan ID from item meta
	$loan_id = wp_lib_fetch_loan( $item_id );
	
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

// Given item ID, fetches current loan and returns loan ID
function wp_lib_fetch_loan( $item_id, $date = false ) {
	// If a date hasn't been given, assume loan is in progress
	if ( !$date ) {
		// Fetches loan ID from item metadata
		$loan_id = get_post_meta( $item_id, 'wp_lib_loan', true );

	} else {
		// Fetches item loan index
		$loans = get_post_meta( $item_id, 'wp_lib_loan_index' );
		
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

// Loans item to member
function wp_lib_loan_item( $item_id, $member_id, $loan_length = false ) {
	// Sets start date to current date
	$start_date = current_time( 'timestamp' );
	
	// If loan length wasn't given, use default loan length
	if ( !$loan_length )
		$loan_length = get_option( 'wp_lib_loan_length', 12 );
	// If loan length is not a positive integer, call error
	elseif ( !ctype_digit( $loan_length ) ) {
		wp_lib_error( 311 );
		return false;
	}

	// Sets end date to current date + loan length
	$end_date = $start_date + ( $loan_length * 24 * 60 * 60);
	
	// Schedules loan, returns loan's ID on success
	$loan_id = wp_lib_schedule_loan( $item_id, $member_id, $start_date, $end_date );
	
	if ( !$loan_id )
		return $loan_id;
	
	// Passes item to member then checks for success
	if ( !wp_lib_give_item( $item_id, $loan_id, $member_id ) ) {
		wp_lib_error( 411 );
		return false;
	}
	
	// Notifies user of successful loan
	wp_lib_add_notification( 'Loan of ' . get_the_title( $item_id ) . ' to ' . get_the_title( $member_id ) . ' was successful!' );
	
	return true;
}

// Sanitizes and prepares data for loan scheduling function
function wp_lib_schedule_loan_wrapper( $item_id, $member_id, $start_date, $end_date ) {
	// If loan starts before it sends or ends before current time, calls an error and The Doctor
	if ( $start_date > $end_date || $end_date < current_time( 'timestamp' ) ) {
		wp_lib_error( 307 );
		return false;
	}
		
	// Schedules loan of item
	$result = wp_lib_schedule_loan( $item_id, $member_id, $start_date, $end_date );
	
	// Checks if loan scheduling was successful
	if ( $result ) {
		// Notifies user of successful loan
		wp_lib_add_notification( 'A loan of ' . get_the_title( $item_id ) . ' has been scheduled' );
	}
	
	return $result;	
}

// Schedules a loan, without actually giving the item to the member
// If $start_date is not set loan is from current date
// If $end_date is not set loan will be the default length (option 'wp_lib_loan_length')
function wp_lib_schedule_loan( $item_id, $member_id, $start_date, $end_date ) {
	// Checks if item can actually be loaned
	if ( !wp_lib_loanable( $item_id, $start_date, $end_date ) ) {
		wp_lib_error( 401 );
		return false;
	}
	
	// Fetches item's loans index
	$loan_index = wp_lib_fetch_loan_index( $item_id );
	
	// If item has previous loans, proposed loan must be checked for conflicts
	if ( $loan_index ) {
		// Runs scheduling engine to check if space exists for loan
		// Note that $result will be used once the loan has been created, to index the loan in item meta
		$result = wp_lib_recursive_scheduling_engine( $start_date, $end_date, $loan_index );
		
		// If scheduling engine returns false, there is a conflict
		if ( !$result ) {
			wp_lib_error( 401 );
			return false;
		}
	}
	
	// Creates arguments for loan
	$args = array(
		'post_status'		=> 'publish',
		'post_type'			=> 'wp_lib_loans'
	);
	
	// Creates the loan, a custom post type that holds useful meta about the loan
	$loan_id = wp_insert_post( $args, true );
	
	// If loan was not successfully created, call error
	if ( !is_numeric( $loan_id ) ) {
		wp_lib_error( 400 );
		return false;
	}
	
	// Creates item's loan index entry
	$loan_args = array(
		'start'		=> $start_date,
		'end'		=> $end_date,
		'loan_id'	=> $loan_id	
	);
	
	/* Analyses scheduling result to see where in ordered array the new loan should go */
	
	// If loan belongs at start of index
	if ( $result == 'start' )
		array_unshift( $loan_index, $loan_args );
	// If loan belongs at end of index
	elseif ( $result == 'end' )
		$loan_index[] = $loan_args;
	// If loan belongs at a specified position in the array
	elseif ( is_numeric( $result ) ) {
		// Loan index is split and the new entry is added at the correct position
		$loan_index = array_merge(
			array_slice( $loan_index, 0, $result ),
			array( $loan_args ),
			array_slice( $loan_index, $result, null )
		);
	}
	// Otherwise loan index contains no previous loans
	else {
		// Initialises loan index as an array and adds loan
		$loan_index = array( $loan_args );
	}
	
	// Saves updated loan index to item meta
	update_post_meta( $item_id, 'wp_lib_loan_index', $loan_index );
	
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

// Represents the physical passing of the item from Library to Member. Item is registered as outside the library and relevant meta is updated
function wp_lib_give_item( $item_id, $loan_id, $member_id, $date = false ) {
	// Sets date to current time if not set
	wp_lib_prep_date( $date );

	/* Updates item's loan index entry */
	
	// Fetches loan index from item meta
	$loan_index = wp_lib_fetch_loan_index( $item_id );
	
	// Locates position of current loan in item's loan index
	$key = wp_lib_fetch_loan_position( $loan_index, $loan_id );

	// If key was not found, call error
	if ( $key === false ) {
		wp_lib_error( 203 );
		return false;
	}
	
	// Loan's entry in loan index is updated with actual start date 
	$loan_index[$key]['start'] = $date;
	
	// Updated loan index is saved to item meta
	update_post_meta( $item_id, 'wp_lib_loan_index', $loan_index );
	
	/* Updates other meta */
	
	// Updates loan status from 'Scheduled' to 'On Loan'
	update_post_meta( $loan_id, 'wp_lib_status', 1 );	
	
	// Sets date item was loaned
	add_post_meta( $loan_id, 'wp_lib_loaned_date', $date );
	
	wp_lib_add_meta( $item_id,
		array(
			'wp_lib_member'	=> $member_id, // Assigns item to member to signify the physical item is in their possession
			'wp_lib_loan'	=> $loan_id
		)
	);

	return true;
}

// Returns a loaned item, allowing it to be re-loaned. The opposite of wp_lib_give_item
function wp_lib_return_item( $item_id, $date = false, $no_fine = false ) {
	// Sets date to current date, if unspecified
	wp_lib_prep_date( $date );
	
	// Checks if date is in the past
	if ( $date > current_time( 'timestamp' ) ) {
		wp_lib_error( 310 );
		return false;
	}
	
	// Fetches loan ID using item ID
	$loan_id = wp_lib_fetch_loan( $item_id );

	// Checks if item as actually on loan
	if ( get_post_meta( $loan_id, 'wp_lib_status', true ) != 1 ) {
		wp_lib_error( 409 );
		return false;
	}
	
	// Fetches if item is late or not
	$late = wp_lib_item_late( $loan_id, $date );
	
	// Fetches if a fine has been charged
	$fined = get_post_meta( $loan_id, 'wp_lib_fine', true );
	
	// If item is late, a fine hasn't been charged and $no_fine isn't true, render fine resolution page
	if ( $late && !$no_fine && !$fined ) {
		wp_lib_error( 410 );
		return false;
	}
	
	// Fetches loan index from item meta
	$loan_index = wp_lib_fetch_loan_index( $item_id );
	
	// Locates position of current loan in item's loan index
	$key = wp_lib_fetch_loan_position( $loan_index, $loan_id );

	// If key was not found, call error
	if ( $key === false ) {
		wp_lib_error( 203 );
		return false;
	}
		
	// Checks if user is attempting to return item before it was loaned
	if ( $loan_index[$key]['start'] > $date ) {
		wp_lib_error( 310 );
		return false;
	}
		
	// Loan index is updated with item's actual date of return
	$loan_index[$key]['end'] = $date;
	
	// Updated loan index is saved to item meta
	update_post_meta( $item_id, 'wp_lib_loan_index', $loan_index );

	// Deletes member ID from item meta, representing the physical item passing from the member's possession to the Library
	delete_post_meta( $item_id, 'wp_lib_member' );

	// Removes loan ID from item meta
	delete_post_meta( $item_id, 'wp_lib_loan' );

	// Loan status is set according to if:
	// Item was returned late and a fine was charged
	if ( $fined )
		$status = 4;
	// Item was returned late but a fine was not charged
	elseif ( $late )
		$status = 3;
	// Item was returned on time
	else
		$status = 2;
	
	// Sets loan status
	update_post_meta( $loan_id, 'wp_lib_status', $status );

	// Loan returned date set
	// Note: The returned_date is when the item is returned, the end_date is when it is due back
	add_post_meta( $loan_id, 'wp_lib_returned_date', $date );
	
	// Notifies user of item return
	wp_lib_add_notification( get_the_title( $item_id ) . ' has been returned successfully' );
	
	return true;
}

// Allows users to view, manage or create loans from a central dashboard
function wp_lib_dashboard() {
	require_once( plugin_dir_path(__FILE__) . '/wp-librarian-dashboard.php' );
}

// Fines member for returning item late
function wp_lib_create_fine( $item_id, $date = false, $return = true ) {
	// Sets date to current time if unspecified
	wp_lib_prep_date( $date );

	// Fetches loan ID from item meta
	$loan_id = wp_lib_fetch_loan( $item_id );
	
	// Runs cherry pie to check if item is actually late
	$due_in = wp_lib_cherry_pie( $loan_id, $date );
	
	// If cherry pie failed, call error
	if ( !$due_in ) {
		wp_lib_error( 412 );
		return false;
	}
	// If $due_in is positive, item is not late
	elseif ( $due_in >= 0 ) {
		wp_lib_error( 406 );
		return false;
	}
	
	// Creates arguments for fine
	$args = array(

		'post_status'		=> 'publish',
		'post_type'			=> 'wp_lib_fines'
	);
	
	// Creates the fine, a custom post type that holds useful meta about the fine
	$fine_id = wp_insert_post( $args, true );
	
	// Due in -5 days == 5 days late
	$days_late = -$due_in;
	
	// Fetches daily charge for a late item
	$daily_fine = get_option( 'wp_lib_fine_daily' );
	
	// Calculates fine based off days late * charge per day
	$fine = $days_late * $daily_fine;
	
	// If fine creation failed, call error
	if ( !is_numeric( $fine_id ) ) {
		wp_lib_error( 407 );
		return false;
	}
	
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
	
	// Notifies user of successful fine creation
	wp_lib_add_notification( get_the_title( $member_id ) . ' has been charged ' . wp_lib_format_money( $fine ) . ' for the late return of ' . get_the_title( $item_id ) );
	
	// Return item unless otherwise specified
	if ( $return )
		wp_lib_return_item( $item_id );
}

// Changes fine from unpaid to paid
function wp_lib_charge_fine( $fine_id ) {
	// Fetches (unformatted) fine status
	$fine_status = get_post_meta( $fine_id, 'wp_lib_status', true );
	
	// If fine is not currently unpaid, calls error
	if ( $fine_status != 1 ) {
		// Formats desired fine status
		$desired_status = wp_lib_format_fine_status( 1 );
		
		// Formats current fine status for error message
		$actual_status = wp_lib_format_fine_status( $fine_status );
		
		// Calls error
		wp_lib_error( 309, false, array( $desired_status, $actual_status ) );
		return false;
	}
	
	// Changes fine status to paid
	update_post_meta( $fine_id, 'wp_lib_status', 2 );
	
	// Notifies user that fine has been paid
	wp_lib_add_notification( "Fine #{$fine_id} has been marked as paid" );
	
	return true;
}

// Changes fine from paid to unpaid
function wp_lib_revert_fine( $fine_id ) {
	// Fetches (unformatted) fine status
	$fine_status = get_post_meta( $fine_id, 'wp_lib_status', true );
	
	// If fine status is not currently paid, call error
	if ( $fine_status != 2 ) {
		// Formats desired fine status
		$desired_status = wp_lib_format_fine_status( 2 );
		
		// Formats current fine status for error message
		$actual_status = wp_lib_format_fine_status( $fine_status );
		
		// Calls error
		wp_lib_error( 309, false, array( $desired_status, $actual_status ) );
		return false;
	}

	// Changes fine status to paid
	update_post_meta( $fine_id, 'wp_lib_status', 1 );
	
	// Notifies user that fine has been paid
	wp_lib_add_notification( "Fine #{$fine_id} has been reverted from 'Paid' to 'Unpaid'" );
	
	return true;
}

// Cancels fine so that it is no longer is required to be paid
function wp_lib_cancel_fine( $fine_id ) {
	// Fetches (unformatted) fine status
	$fine_status = get_post_meta( $fine_id, 'wp_lib_status', true );

	// If fine has already been cancelled
	if ( $fine_status == 3 ) {
		// Calls error
		wp_lib_error( 313 );
		return false;
	}

	// Changes fine status to Cancelled
	update_post_meta( $fine_id, 'wp_lib_status', 3 );
	
	// Notifies user that fine has been cancelled
	wp_lib_add_notification( "Fine #{$fine_id} has been cancelled" );
}

// Turns numeric loan status into readable string e.g. 1 -> 'On Loan'
function wp_lib_format_loan_status( $status ) {
	// Array of all possible states of the loan
	$strings = array(
		0	=> '',
		1	=> 'On Loan',
		2	=> 'Returned',
		3	=> 'Returned Late',
		4	=> 'Returned Late (with fine)',
		5	=> 'Scheduled'
	);
	
	// If given number refers to a status that doesn't exist, throw error
	if ( empty( $strings[$status] ) )
		wp_lib_error( 201, true, 'Loan' );
	
	// State is looked up in the array and returned
	return $strings[$status];
}

// Turns numeric fine status into readable string e.g. 1 -> 'Unpaid'
function wp_lib_format_fine_status( $status ) {

	// Array of all possible states of the fine
	$strings = array(
		0	=> '',
		1	=> 'Unpaid',
		2	=> 'Paid',
		3	=> 'Cancelled',
	);
	
	// If given number refers to a status that doesn't exist, throw error
	if ( empty( $strings[$status] ) )
		wp_lib_error( 201, true, 'Fine' );
	
	// State is looked up in the array and returned
	return $strings[$status];
}

// Returns explanation of error given error code
function wp_lib_error( $error_id, $die = false, $param = 'NULL' ) {
	// Checks if error code is valid and error exists, if not returns error
	if ( !is_numeric( $error_id ) )
		wp_lib_error( 901, true );
	
	// Array of all error codes and their explanations
	//   0 - Reserved, see wp_lib_add_notification()
	// 1xx - Core functionality failure
	// 2xx - General loan/return systems error
	// 3xx - Invalid loan/return parameters
	// 4xx - Error loaning/returning item or fining user
	// 5xx - AJAX systems error
	// 8xx - JavaScript Errors, stored client-side
	// 9xx - Error processing error
	$all_errors = array(
		110 => 'DateTime neither positive or negative',
		111 => 'Unexpected currency position',
		112 => 'Insufficient permission',
		113 => "Can not delete {$param} as it is currently on loan. Please return the item first.",
		200 => 'Item action not recognised',
		201 => "No {$param} status found for given value",
		202 => 'Loans do not have management pages, but I appreciate your curiosity!',
		203 => 'Loan not found in item\'s loan index',
		204 => 'Multiple items have the same barcode',
		300 => "{$param} ID not given and required",
		301 => "{$param} ID given is not a number",
		302 => 'No loans found for that item ID',
		304 => 'No member found with that ID',
		305 => "No valid item found with ID {$param}, check if item is a draft or in the trash",
		306 => 'No valid loan found with that ID',
		307 => 'Given dates result in an impossible or impractical loan',
		308 => 'No valid fine found with that ID',
		309 => "Cannot complete action given current fine status. Expected: {$param[0]} Actual: {$param[1]}",
		310 => 'Given date not valid',
		311 => 'Given loan length invalid (not a valid number)',
		312 => 'Given date(s) failed to validate',
		313 => 'Fine can not be cancelled if it is already cancelled',
		314 => 'Fine action not recognised',
		315 => 'Library Object type not specified or recognised',
		400 => 'Loan creation failed for unknown reason, sorry :/',
		401 => 'Can not loan item, it is already on loan or not allowed to be loaned.<br/>This can happen if you have multiple tabs open or refresh the loan page after a loan has already been created.',
		402 => 'Item not on loan (Loan ID not found in item meta)<br/>This can happen if you refresh the page having already returned an item',
		403 => 'Loan not found (Loan ID found in item meta but no loan found that ID). The item has now been cleaned of all loan meta to attempt to resolve the issue. Refresh the page.',
		405 => 'Loan is missing due date',
		406 => 'Item is/was not late on given date, mate',
		407 => 'Fine creation failed for unknown reasons, sorry :/',
		408 => 'Recursive Scheduling Engine returned unexpected value',
		409 => 'Loan status reports item is not currently on loan',
		410 => 'Item can not be returned on given date because it would be late. Please resolve late item or return item at an earlier date',
		411 => 'A loan was scheduled but an error occurred when giving the item to the user. The item has not been marked as having left the library!',
		412 => 'Unable to check if item is late',
		500 => "Action requested does not exist. Given action: {$params}",
		501	=> 'No content has been specified for the given page, as such page cannot be rendered',
		502 => 'No page of that name found',
		901 => 'Error encountered while processing error (error code not a number)',
		902 => 'Error encountered while processing error (error does not exist)'
	);
	
	// Checks if error exists, if not returns error
	if ( !array_key_exists( $error_id, $all_errors ) )
		wp_lib_error( 902, true );
	
	// Fetches error explanation from array
	$error_text = $all_errors[$error_id];
	
	// If function is set to die, renders error then kills function
	if ( $die ){
		echo "<div class='wp-lib-error error'><p><strong style=\"color: red;\">WP-Librarian Error {$error_id}: {$error_text}</strong></p></div>";
		die();
	}
	
	// Otherwise adds error to notification buffer
	wp_lib_add_notification( $error_text, $error_id );
}

// Renders item meta box below item description on item creation/editing page
function wp_lib_render_item_meta_box( $item ) {
	require_once (plugin_dir_path(__FILE__) . '/wp-librarian-item-meta-box.php');
}

// Renders member meta box below member name on member creation/editing page
function wp_lib_render_member_meta_box( $member ) {
	require_once (plugin_dir_path(__FILE__) . '/wp-librarian-member-meta-box.php');
}
?>