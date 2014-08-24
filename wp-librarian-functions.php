<?php
/*
 * WP-LIBRARIAN FUNCTIONS
 * Various functions used to display or modify parts of the Library.
 * These functions use numerous helpers from wp-librarian-helpers.php
 * and rely on post types and taxonomies set up in wp-librarian.php
 */

// Prepares taxonomy and metabox information for theme use
function wp_lib_fetch_meta( $the_post_id ) {
	// Metabox data is fetched and relevant functions are called to format the data
	$meta_array = array(
		'media type'	=> wp_lib_prep_meta( get_the_terms( $the_post_id, 'wp_lib_media_type' ), 'wp_lib_media_type_slug', 'media-type', 'Media Type' ),
		'authors'		=> wp_lib_prep_meta( get_the_terms( $the_post_id, 'wp_lib_author' ), 'wp_lib_authors_slug', 'authors', 'Author' ),
		'donor'			=> wp_lib_prep_meta( get_the_terms( $the_post_id, 'wp_lib_donor' ), 'wp_lib_donors_slug', 'donors', 'Donor' ),
		'isbn'			=> wp_lib_prep_meta( get_post_meta( $the_post_id, 'wp_lib_item_isbn', true ), false, false, 'ISBN' ),
		'available'		=> wp_lib_prep_meta( wp_lib_prep_item_available( $the_post_id ), false, false, 'Status' ),
	);
	$all_meta = '';
	// Runs through each meta value and, if the meta exists, adds it to the end of the $all_meta string
	foreach ( $meta_array as $value ) {
		if ( $value != false )
			$all_meta .= $value . '<br />';
	}
	
	return $all_meta;
}

// Formats author/media type/donor arrays and formats them as a comma separated list with hyperlinks
function wp_lib_prep_meta( $raw_array, $option_name, $option_default_slug, $bold_name ) {
	if ( $raw_array != false ){
		// If there is one than one of a taxonomy item it makes the term plural (Author -> Authors)
		if ( count( $raw_array ) > 1 ) {
			$plural = 's:</strong> ';
		} else {
			$plural = ':</strong> ';
		}
		
		// The beginning of a meta value is composed using the meta name e.g. 'ISBN: '
		$item_string = '<strong>' . $bold_name . $plural;
		
		// If $raw_array is not an array, return formatted string before foreach loop
		if ( !is_array( $raw_array ) )
			return $item_string . $raw_array;

		// Each taxonomy item is formatted as a hyperlink
		// Every item after the first is preceded with (by default) a comma via $spacer
		$count = 0;
		foreach ( $raw_array as $item ) {
			$count++;
			$spacer = '';
			if ( $count > 1 )
				$spacer .= get_option( 'wp_lib_taxonomy_spacer', ', ' );
			if ( isset( $item->slug ) ) {
				$tax_url = get_option( 'siteurl', 'example.com/' );
				$tax_slug = wp_lib_prefix_url( $option_name, $option_default_slug );
				$item_string .= "{$spacer}<a href=\"{$tax_url}/{$tax_slug}/{$item->slug}\">{$item->name}</a>";
			}
			else
				$item_string .= $spacer . $item;
		}
		return $item_string;
	}
}

// Checks if user has permission to loan/return books
function wp_lib_is_librarian() {
	return true;
}

// Creates "Available" or "Unavailable" string depending on if item if available to loan
function wp_lib_prep_item_available( $item_id, $no_url = false, $short = false ) {
	// Checks if the current user was the permissions of a Librarian
	$librarian = wp_lib_is_librarian();
	
	// Fetches if item is allowed to be loaned
	$loan_allowed = wp_lib_loan_allowed( $item_id );
	
	// Fetches if item is currently on loan
	$on_loan = wp_lib_on_loan( $item_id );
	
	// If item can be loaned and is available, url is made to take user to loans Dashboard to loan item
	if ( $loan_allowed && !$on_loan )
		$status = 'Available';
	
	// If item is on loan link is composed to return item
	elseif ( $on_loan ) {
		// Sets item status accordingly
		$status = 'On Loan';
		
		// Checks if user has permission to see full details of current loan
		if ( $librarian ) {
			// If user wants full item status, member that item is loaned to is fetched
			if ( !$short ) {
				$loan_id = wp_lib_fetch_loan( $item_id );
				$details = ' to ' . array_values( get_the_terms( $loan_id, 'wp_lib_member' ) )[0]->name;
			}
			$args = array(
			'due'	=> 'due in \d day\p',
			'today'	=> 'due today',
			'late'	=> '\d day\p late',
			);
			$details .= ' (' . wp_lib_prep_item_due( $item_id, false, $args ) . ')';
		}
	}
	
	// If item isn't allowed to be loaned item is marked as unavailable
	else {
		$use_url = false;
		$status = 'Unavailable';
	}
	
	// If user has the relevant permissions, availability will contain link to loan/return item
	if ( $librarian && !$no_url ) {
		// String preparation
		$url = wp_lib_format_manage_item( $item_id );
		$url = "<a href=\"{$url}\">";
		$end = '</a>';
	}
	else {
		$url = '';
		$end = '';
	}
	
	// String is concatenated and returned
	return $url . $status . $details . $end;
}

// Checks if item will be on loan between given dates. Given no dates, checks if item is currently on loan
function wp_lib_on_loan( $item_id, $start_date = false, $end_date = false ) {
	// If dates weren't given then the schedule doesn't need to be checked
	// The simpler method of checking the item for an assigned member can be used
	if ( !( $start_date || $end_date ) ) {
		// Fetches all members assigned to item
		$loan_already = get_the_terms( $item_id, 'wp_lib_member' );

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

function wp_lib_check_item_id( $item_id ) {
	if ( !wp_lib_valid_item_id( $item_id ) )
		wp_lib_page_dashboard();
}

function wp_lib_check_member_id( $member_id ) {
	if ( !wp_lib_valid_member_id( $member_id ) )
		wp_lib_page_dashboard();
}

function wp_lib_check_fine_id( $fine_id ) {
	if ( !wp_lib_valid_fine_id( $fine_id ) )
		wp_lib_page_dashboard();
}

function wp_lib_check_loan_id( $loan_id ) {
	if ( !wp_lib_valid_loan_id( $loan_id ) )
		wp_lib_page_dashboard();
}

// Checks if member ID is valid
function wp_lib_valid_member_id( $member_id ) {
	// Checks if member ID exists
	if ( !$member_id ) {
		wp_lib_error( 300, false, 'Member' );
		return false;
	}
	// Checks if member_id is valid. Kills script if not
	if ( !is_numeric( $member_id ) ) {
		wp_lib_error( 301, false, 'Member' );
		return false;
	}
	// Changes ID to number if it is
	$member_id = absint( $member_id );
	
	// Checks if member exists with that ID
	if ( !term_exists( $member_id, 'wp_lib_member' ) ) {
		wp_lib_error( 304 );
		return false;
	}
	
	return true;
}

// Checks if item ID is valid
function wp_lib_valid_item_id( $item_id ) {
	// Checks if item ID exists
	if ( !$item_id ) {
		wp_lib_error( 300, false, 'Item' );
		return false;
	}

	// Checks if ID is a number
	if ( !is_numeric( $item_id ) ) {
		wp_lib_error( 301, false, 'Item' );
		return false;
	}
		
	// Fetches item status
	$item_status = get_post_status( $item_id );
		
	// Checks if ID belongs to a published/private library item
	if ( !get_post_type( $item_id ) == 'wp_lib_items' || !$item_status == 'publish' || !$item_status == 'private' ) {
		wp_lib_error( 305 );
		return false;
	}

	return true;
}

// Checks if loan ID is valid
function wp_lib_valid_loan_id( $loan_id ) {
	// Checks if loan ID exists
	if ( !$loan_id ) {
		wp_lib_error( 300, false, 'Loan' );
		return false;
	}
	
	// Checks if ID is actually a number
	if ( !is_numeric( $loan_id ) ) {
		wp_lib_error( 301, false, 'Loan' );
		return false;
	}
	
	// Checks if ID belongs to a published loan (a loan in any other state is not valid)
	if ( !get_post_type( $loan_id ) == 'wp_lib_loans' || !get_post_status( $loan_id ) == 'publish' ) {
		wp_lib_error( 306 );
		return false;
	}

	return true;
}

// Checks if fine ID is valid
function wp_lib_valid_fine_id( $fine_id ){
	// Checks if fine ID exists
	if ( !$fine_id ) {
		wp_lib_error( 300, false, 'Fine' );
		return false;
	}
	
	// Checks if ID is actually a number
	if ( !is_numeric( $fine_id ) ) {
		wp_lib_error( 301, false, 'Fine' );
		return false;
	}
	
	// Checks if ID belongs to a published loan (a loan in any other state is not valid)
	if ( !get_post_type( $fine_id ) == 'wp_lib_fines' || !get_post_status( $fine_id ) == 'publish' ) {
		wp_lib_error( 308 );
		return false;
	}

	return true;
}

// Calculates days until item needs to be returned, returns negative if item is late
function wp_lib_cherry_pie( $loan_id, $date ) {
	// Fetches item due date from loan meta
	$due_date = get_post_meta( $loan_id, 'wp_lib_end_date', true );
	
	// If loan doesn't have a due date, error is thrown
	if ( $due_date == '' ) {
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
	
	// If cherry pie failed, kill execution
	if ( !$late )
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
		$loan_id = get_post_meta( $item_id, 'wp_lib_loan_id', true );

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
	
	// Fetches item title
	$title = get_the_title( $item_id );
	
	// Fetches member object
	$member = get_term_by( 'id', absint( $member_id ), 'wp_lib_member' );
	
	// Notifies user of successful loan
	wp_lib_add_notification( "Loan of {$title} to {$member->name} was successful!" );
	
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
		// Fetches item title
		$title = get_the_title( $item_id );
		
		// Notifies user of successful loan
		wp_lib_add_notification( "A loan of {$title} has been scheduled" );
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

	// Fetches member object
	$member = get_term_by( 'id', absint( $member_id ), 'wp_lib_member' );
		
	// Creates arguments for loan
	$args = array(

		'post_status'		=> 'publish',
		'post_type'			=> 'wp_lib_loans',
		'ping_status'		=> 'closed',
		'tax_input'			=> array( 'wp_lib_member' => "{$member->name}" ),
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
	
	// Gets the item's title
	$title = get_the_title( $item_id );
	
	// Stores member/item information in case either is deleted
	$archive = array(
		'member-name'	=> $member->name,
		'item-name'		=> $title,
	);
	
	// Saves item due date, loan holder, item id to the loan's post meta
	add_post_meta( $loan_id, 'wp_lib_start_date', $start_date );
	add_post_meta( $loan_id, 'wp_lib_end_date', $end_date );
	add_post_meta( $loan_id, 'wp_lib_archive', $archive );
	add_post_meta( $loan_id, 'wp_lib_item', $item_id );
	add_post_meta( $loan_id, 'wp_lib_status', 5 );
	
	return $loan_id;
}

// Represents the physical passing of the item from Library to Member. Item is registered as outside the library and relevant meta is updated
function wp_lib_give_item( $item_id, $loan_id, $member_id ) {

	// Fetches member object
	$member = get_term_by( 'id', absint( $member_id ), 'wp_lib_member' );

	// Assigns the member to the item, to signify their current position of the Library item
	wp_set_post_terms( $item_id, $member->name, 'wp_lib_member' );
	
	// Updates loan status from 'Scheduled' to 'On Loan'
	update_post_meta( $loan_id, 'wp_lib_status', 1 );	
	
	// Saves loan ID to the item's meta
	add_post_meta( $item_id, 'wp_lib_loan_id', $loan_id );
	
	// Fetches current time
	$time = current_time( 'timestamp' );
	
	// Sets date item was loaned
	add_post_meta( $loan_id, 'wp_lib_loaned_date', $time );
	
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
	$loan_index[$key]['start'] = $time;
	
	// Updated loan index is saved to item meta
	update_post_meta( $item_id, 'wp_lib_loan_index', $loan_index );
	
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

	// Clears item's Member taxonomy
	wp_delete_object_term_relationships( $item_id, 'wp_lib_member' );

	// Removes loan ID from item meta
	delete_post_meta($item_id, 'wp_lib_loan_id' );

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
	// Note: The returned date is when the item is returned, the end date is when it is due back
	add_post_meta( $loan_id, 'wp_lib_returned_date', $date );
	
	// Fetches item title
	$title = get_the_title( $item_id );
	
	// Notifies user of item return
	wp_lib_add_notification( "{$title} has been returned successfully" );
	
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
	
	// Due in -5 days == 5 days late
	$days_late = -$due_in;
	
	// Fetches daily charge for a late item
	$daily_fine = get_option( 'wp_lib_fine_daily' );
	
	// Calculates fine based off days late * charge per day
	$fine = $days_late * $daily_fine;
	
	// Fetches member object from item tax
	$member = wp_get_post_terms( $item_id, 'wp_lib_member' )[0];
	
	// Formats fine for item title
	$fine_formatted = wp_lib_format_money( $fine );
	
	// Formats $date
	$date = date('d-m-y');
	
	// Fetches item title
	$title = get_the_title( $item_id );
	
	// Creates arguments for fine
	$post = array(

		'post_status'		=> 'publish',
		'post_type'			=> 'wp_lib_fines',
		'ping_status'		=> 'closed',
		'tax_input'			=> array( 'wp_lib_member' => "{$member->name}" ),
	);
	
	// Creates the fine, a custom post type that holds useful meta about the fine
	$fine_id = wp_insert_post( $post, true );
	
	// If fine creation failed, call error
	if ( !is_numeric( $fine_id ) ) {
		wp_lib_error( 407 );
		return false;
	}
	
	// Stores member/item information for reference, in case either is deleted
	$archive = array(
		'member-name'	=> $member->name,
		'item-name'		=> $title,
	);
	
	// Saves item ID, member ID, fine status and amount to post meta
	// Also saves archive of member/item names
	add_post_meta( $fine_id, 'wp_lib_archive', $archive );
	add_post_meta( $fine_id, 'wp_lib_item', $item_id );
	add_post_meta( $fine_id, 'wp_lib_status', 1 );
	add_post_meta( $fine_id, 'wp_lib_fine', $fine );
	add_post_meta( $fine_id, 'wp_lib_loan', $loan_id );
	
	// Saves fine ID to loan meta
	add_post_meta( $loan_id, 'wp_lib_fine', $fine_id );
	
	// Fetches member meta, saved as an option owing to WordPress limitations
	$meta = get_option( "wp_lib_tax_{$member->term_id}", false );
	
	// If option did not exist, it is set as a blank array
	if ( !$meta )
		$meta = array();
	
	// Member's current total debt is fetched from member meta
	$debt = $meta['debt'];
	
	// If user has never had any debt, debt key/value is initialised
	if ( !$meta['debt'] )
		$meta['debt'] = 0;
	
	// Fine is added to member's total debt
	$meta['debt'] += $fine;
	
	// Member meta is saved
	update_option( "wp_lib_tax_{$member->term_id}", $meta );
	
	// Debugging
	wp_lib_add_notification( "{$member->name} has been charged {$fine_formatted} for the late return of {$title}" );
	
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

// Fetches and returns item title with optional link to manage item, falling back to archived title if needed
/* Params:
 * $item_id - Item's ID
 * $fallback_id - ID of loan or fine to use as a fallback and fetch the item's title from
 * $hyperlink - (Optional) If to format the title as a hyperlink
 * $array - If to return an array containing both the title and if the item existed (otherwise return title as a string)
 * $ending - If to suffix title with '(item deleted)' if item no longer exists
 */
function wp_lib_format_item_title( $item_id, $fallback_id, $hyperlink = true, $array = false, $ending = true ) {
	// Fetches item title
	$title = get_the_title( $item_id );
	
	// If array is to be returned, set item status
	if ( $title && $array )
		$output['deleted'] = false;
	
	// If item no longer exists
	if ( !$title ) {
		// Fetches fallback archive from loan/fine meta and retrieves item name from said archive
		$title = get_post_meta( $fallback_id, 'wp_lib_archive', true )['item-name'];
		
		// Informs user item no longer exists
		if ( $ending )
			$title .= ' (item deleted)';
		
		// Used if array is returned
		$output['deleted'] = true;
	}
	// If item exists and is to be formatted as a hyperlink
	elseif ( $hyperlink ) {
		// Fetches url to manage item
		$url = wp_lib_format_manage_item( $item_id );
		
		// Formats title as hyperlink
		$title = "<a href=\"{$url}\">{$title}</a>";
	}
	
	// If array was wanted, returns array including item status
	if ( $array ) {
		$output['title'] = $title;
		return $output;
	}
	// Otherwise just returns title (as a hyperlink or otherwise)
	else
		return $title;

}

// Cancels loan of item that has a since corrupted loan attached to it
// This function should not be called under regular operation and should definitely not used to return an item
function wp_lib_clean_item( $item_id ){
	// Checks if given ID is valid
	wp_lib_check_item_id( $item_id );

	// Clears item's Member taxonomy
	wp_delete_object_term_relationships( $item_id, 'wp_lib_member' );

	// Removes loan ID from item meta
	delete_post_meta($item_id, 'wp_lib_loan_id' );
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
		300 => "{$param} ID not given and required",
		301 => "{$param} ID given is not a number",
		302 => 'No loans found for that item ID',
		304 => 'No member found with that ID',
		305 => 'No valid item found with that ID, check if item is a draft or in the trash',
		306 => 'No valid loan found with that ID',
		307 => 'Given dates result in an impossible or impractical loan',
		308 => 'No valid fine found with that ID',
		309 => "Cannot complete action given current fine status. Expected: {$param[0]} Actual: {$param[1]}",
		310 => 'Given date not valid',
		311 => 'Given loan length invalid (not a valid number)',
		312 => 'Given date(s) failed to validate',
		313 => 'Fine can not be cancelled if it is already cancelled',
		314 => 'Fine action not recognised',
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
		wp_lib_render_error( "<strong style=\"color: red;\">WP-Librarian Error {$error_id}: {$error_text}</strong>" );
		die();
	}
	
	// Otherwise adds error to notification buffer
	wp_lib_add_notification( $error_text, $error_id );
}

// Dumps variables in a pretty way
function wp_lib_var_dump() {
	// Gets all given params
	$args = func_get_args();
	
	// For each param, var_dump between <pre> tags to format code properly in browser
	foreach ( $args as $arg ){
		echo '<pre>';
		echo var_dump( $arg );
		echo '</pre>';
	}
}

// Santizes HTML POST data from a checkbox
function wp_lib_sanitize_checkbox( $raw ) {
	if ( $raw == 'true' )
		return true;
	else
		return false;
}

// Removes unwanted description field from taxonomies list
function wp_lib_no_tax_description() { ?>
	<script type="text/javascript">
    jQuery(document).ready( function($) {
        $('#tag-description').parent().remove();
    });
    </script>
	<?php
}

// Removes unwanted description field from taxonomy item edit page
function wp_lib_no_tax_edit_description() { ?>
	<script type="text/javascript">
    jQuery(document).ready( function($) {
        $('#description').parents().eq(1).remove();
    });
    </script>
	<?php
}

// Clears taxonomy metadata (stored as options) on deletion
function wp_lib_clear_tax_options( $tt_id ) {
	delete_option( "wp_lib_tax_{$tt_id}" );
}

// Renders meta box below item description on item editing page
function wp_lib_render_meta_box( $item ) {
	require_once (plugin_dir_path(__FILE__) . '/wp-librarian-meta-box.php');
}

// Renders the header for the Item management page
function wp_lib_render_item_management_header( $item_id ) {
	// Fetches title of item e.g. 'Moby-Dick'
	$title = get_the_title( $item_id );
	
	// Fetches status of item e.g. 'On Loan (2 days remaining)'
	$status = wp_lib_prep_item_available( $item_id, true );
	?>
	<!-- Management Header -->
	<h2>Managing: <?= $title ?></h2>
	<p>
		<strong>Item ID:</strong> <?= $item_id ?><br />
		<strong>Status:</strong> <?= $status ?>
	</p>
	<?php
}

// Renders the header for the Fine management page, displaying information about the fine
function wp_lib_render_fine_management_header( $fine_id ) {
	// Fetches and formats fine amount ( e.g. £0.40 )
	$fine_formatted = wp_lib_format_money( get_post_meta( $fine_id, 'wp_lib_fine', true ) );
	
	// Fetches fine status, unformatted ( 1 rather than 'Unpaid' )
	$fine_status = get_post_meta( $fine_id, 'wp_lib_status', true );

	// Formats fine status
	$formatted_status = wp_lib_format_fine_status( $fine_status );
	
	// Fetches item and loan IDs
	$item_id = get_post_meta( $fine_id, 'wp_lib_item', true );
	$loan_id = get_post_meta( $fine_id, 'wp_lib_loan', true );
	
	// Fetches member name and if member still exists
	$member_array = wp_lib_fetch_member_name( $fine_id, true, true );
	
	// If member no longer exists, fetches member name from fine meta
	if ( $member_array['deleted'] )
		wp_lib_render_error( "The member {$member_array['name']} has since been deleted, this limits fine management" );
	
	// Fetches item title and if the item still exists
	$title_array = wp_lib_format_item_title( $item_id, $fine_id, true, true );
	
	// If item no longer exists, inform user how this limits their options
	if ( $title_array['deleted'] )
		wp_lib_render_error( "The item {$title_array['title']} has since been deleted, this limits fine management" );
	
	?>
	<h2>Managing: Fine #<?= $fine_id ?></h2>
	<p>
		<strong>Item: </strong><?= $title_array['title'] ?><br />
		<strong>Member: </strong><?= $member_array['name'] ?><br />
		<strong>Amount: </strong><?= $fine_formatted ?><br />
		<strong>Status: </strong><?= $formatted_status ?><br />
		<strong>Created: </strong><?= get_the_date( '', $fine_id ) ?>
	</p>
	<?php
}

// Renders the header of the member management page, displaying information about the member
function wp_lib_render_member_management_header( $member_id ) {
	// Fetches member object using member ID
	$member = get_term( $member_id, 'wp_lib_member' );
	
	?>
	<h2>Managing: <?= $member->name ?></h2>
	<strong>Member ID: </strong><?= $member->term_id ?><br />
	<?php
}

// Formats error message in HTML and returns as a string
function wp_lib_format_error( $message ) {
	return "<div class='wp-lib-error error'><p>{$message}</p></div>";
}

// Formats notification in HTML and returns as a string
function wp_lib_format_notification( $message ) {
	return "<div class='wp-lib-notification updated'><p>{$message}</p></div>";
}

// Wrapper function that renders an error to the page
function wp_lib_render_error( $message ) {
	echo wp_lib_format_error( $message );
}

// Wrapper function that renders a notification to the page
function wp_lib_render_notification( $message ) {
	echo wp_lib_format_notification( $message );
}


?>