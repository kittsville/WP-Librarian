<?php
/*
 * The generally useful hooks and functions file
 *
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
	//echo var_dump( $meta_array );
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
				$tax_slug = apply_filters( 'wp_lib_prefix_url', $option_name, $option_default_slug );
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
function wp_lib_on_loan( $item_id, $start_date = false, $end_date = false ){
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
		
	// Interates through 
	
	// Else check if existing loans will conflict, if not then it is available
	elseif ( wp_lib_recursive_scheduling_engine( $start_date, $end_date, $loans ) )
		return false;
	
	// Else loan exists during this time and item will conflict
	return true;
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

// If a date is false, sets to current date
function wp_lib_prep_date( &$date ) {
	if ( !$date )
		$date = time();
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

// Validates member ID
function wp_lib_check_member_id( $member_id ) {
	// Checks if member_id is valid. Kills script if not
	if ( !is_numeric( $member_id ) )
		wp_lib_error( 301, true, 'Member' );
	
	// Changes ID to number if it is
	$member_id = absint( $member_id );
	
	// Checks if member exists with that ID
	if ( !term_exists( $member_id, 'wp_lib_member' ) )
		wp_lib_error( 304, true );
	
	return $member_id;
}

// Validates item ID
function wp_lib_check_item_id( $item_id ) {
	// Checks if ID is actually a number
	if ( !is_numeric( $item_id ) )
		wp_lib_error( 301, true, 'Item' );
		
	// Fetches item status
	$item_status = get_post_status( $item_id );
		
	// Checks if ID belongs to a published/private library item
	if ( !get_post_type( $item_id ) == 'wp_lib_items' || !$item_status == 'publish' || !$item_status == 'private' )
		wp_lib_error( 305, true );

	return $item_id;
}

// Validates loan ID
function wp_lib_check_loan_id( $loan_id ) {
	// Checks if ID is actually a number
	if ( !is_numeric( $loan_id ) )
		wp_lib_error( 301, true, 'Loan' );
	
	// Checks if ID belongs to a published loan (a loan in any other state is not valid)
	if ( !get_post_type( $loan_id ) == 'wp_lib_loans' || !get_post_status( $loan_id ) == 'publish' )
		wp_lib_error( 306, true );

	return $loan_id;
}

function wp_lib_check_fine_id( $fine_id ){
	// Checks if ID is actually a number
	if ( !is_numeric( $fine_id ) )
		wp_lib_error( 301, true, 'Fine' );
	
	// Checks if ID belongs to a published loan (a loan in any other state is not valid)
	if ( !get_post_type( $fine_id ) == 'wp_lib_fines' || !get_post_status( $fine_id ) == 'publish' )
		wp_lib_error( 308, true );

	return $fine_id;
}

// Calculates days until item needs to be returned, returns negative if item is late
function wp_lib_cherry_pie( $loan_id, $date ) {
	// Fetches item due date from loan meta
	$due_date = get_post_meta( $loan_id, 'wp_lib_end_date', true );
	
	// If loan doesn't have a due date, error is thrown
	if ( $due_date == '' )
		wp_lib_error( 405, true );

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
	
	else
		wp_lib_error( 110, true );
}

// Function checks if item is late and returns true if so
function wp_lib_item_late( $loan_id, $date = false ) {
	// Sets date to current time if unspecified
	wp_lib_prep_date( $date );

	// Fetches number of days late
	$late = wp_lib_cherry_pie( $loan_id, $date );
	
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
		if ( !$loans || end( $loans )['end'] <= $date )
			wp_lib_error( 302, true );
			
		// Searches loan index for loan that matches $date
		foreach ( $loans as $loan ) {
			if ( $loan['start'] <= $date && $date <= $loan['end'] ) {
				$loan_id = $loan['loan_id'];
				break;
			}
		}
	}
	wp_lib_var_dump( $loan_id );
	// Validates loan ID
	if ( !is_numeric( $loan_id ) )
		wp_lib_error( 402, true );

	// Checks if loan with that ID actually exists
	if ( get_post_status( $loan_id ) == false ) {
		wp_lib_clean_item( $item_id );
		wp_lib_error( 403, true );
	}
	
	return $loan_id;
}

// Loans item to member
function wp_lib_loan_item( $item_id, $member_id, $loan_length = false ) {
	// Checks if $item_id is valid
	wp_lib_check_item_id( $item_id );
	
	// Checks if given member ID is valid
	wp_lib_check_member_id( $member_id );
	
	// Sets start date to current date
	$start_date = time();
	
	// If loan length wasn't given, use default loan length
	if ( !$loan_length )
		$loan_length = get_option( 'wp_lib_loan_length', 12 );

	// Sets end date to current date + loan length
	$end_date = $start_date + ( $loan_length * 24 * 60 * 60);
	
	// Schedules loan, returns loan's ID on success
	$loan_id = wp_lib_schedule_loan( $item_id, $member_id, $start_date, $end_date );
	
	// Passes item to member
	wp_lib_give_item( $item_id, $loan_id, $member_id );
	
	// Fetches item title
	$title = get_the_title( $item_id );
	
	// Fetches member object
	$member = get_term_by( 'id', absint( $member_id ), 'wp_lib_member' );
	
	// Notifies user of successful loan
	$GLOBALS[ 'wp_lib_notification_buffer' ][] = "Loan of {$title} to {$member->name} was successful!";
}

// Schedules a loan, without actually giving the item to the member
// If $start_date is not set loan is from current date
// If $end_date is not set loan will be the default length (option 'wp_lib_loan_length')
function wp_lib_schedule_loan( $item_id, $member_id, $start_date, $end_date ) {
	// Checks if $item_id is valid
	wp_lib_check_item_id( $item_id );
	
	// Checks if given member ID is valid
	wp_lib_check_member_id( $member_id );

	// Checks if item can actually be loaned
	if ( !wp_lib_loanable( $item_id, $start_date, $end_date ) )
		wp_lib_error( 401, true );
		
	// Fetches item's loans index
	$loan_index = wp_lib_fetch_loan_index( $item_id );
	
	// If item has previous loans, proposed loan must be checked for conflicts
	if ( $loan_index ) {
		// Runs scheduling engine to check if space exists for loan
		// Note that $result will be used once the loan has been created, to index the loan in item meta
		$result = wp_lib_recursive_scheduling_engine( $start_date, $end_date, $loan_index );
		
		// If scheduling engine returns false, there is a conflict
		if ( !$result )
			wp_lib_error( 401, true );
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
	if ( !is_numeric( $loan_id ) )
		wp_lib_error( 400, die );
	
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
	
	// Sets date item was loaned
	add_post_meta( $loan_id, 'wp_lib_loaned_date', time() );
}

// Returns a loaned item, allowing it to be re-loaned. The opposite of wp_lib_give_item
function wp_lib_return_item( $item_id, $date = false, $no_fine = false ) {
	// Sets date to current date, if unspecified
	wp_lib_prep_date( $date );
	
	// Fetches loan ID using item ID
	$loan_id = wp_lib_fetch_loan( $item_id );
	
	// Checks if item as actually on loan
	if ( get_post_meta( $loan_id, 'wp_lib_status', true ) != 1 )
		wp_lib_error( 409, true );
	
	// Fetches if item is late or not
	$late = wp_lib_item_late( $loan_id, $date );
	
	// Fetches if a fine has been charged
	$fined = get_post_meta( $loan_id, 'wp_lib_fine', true );
	
	// If item is late, a fine hasn't been charged and $no_fine isn't true, render fine resolution page
	if ( $late && !$no_fine && !$fined )
		wp_lib_render_resolution( $item_id, $date );
	else {
		// Fetches loan index from item meta
		$loan_index = wp_lib_fetch_loan_index( $item_id );
		
		// Locates position of current loan in item's loan index
		$key = wp_lib_fetch_loan_position( $loan_index, $loan_id );
		
		// If key was not found, call error
		if ( !$key )
			wp_lib_error( 203, true );
			
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
		$GLOBALS[ 'wp_lib_notification_buffer' ][] = "{$title} has been returned successfully";
	}
}

// Allows users to view, manage or create loans from a central dashboard
function wp_lib_dashboard() {
	require_once( plugin_dir_path(__FILE__) . '/wp-librarian-dashboard.php' );
}

// Outputs any buffered notifications or errors then cleans URL before displaying the Library Dashboard
function wp_lib_pre_dashboard() {
	// Fetches notifications and errors
	$notifications = $GLOBALS[ 'wp_lib_notification_buffer' ];
	$errors = $GLOBALS[ 'wp_lib_error_buffer' ];

	// If an array of notifications has been given, notifications are rendered and buffer is cleared
	if ( is_array( $notifications ) ) {
		foreach ( $notifications as $notification ) wp_lib_render_notification( $notification );
		unset( $GLOBALS[ 'wp_lib_notification_buffer' ] );
	}
	
	// If an array of errors has been given, errors are rendered and buffer is cleared
	if ( is_array( $errors ) ) {
		foreach ( $errors as $error ) wp_lib_render_error( $error );
		unset( $GLOBALS[ 'wp_lib_error_buffer' ] );
	}
	
	// Cleans URL
	?>
	<script type="text/javascript">
		var state	= {},
			title	= "",
			path	= "edit.php?post_type=wp_lib_items&page=dashboard";
		window.onload=function(){
			history.pushState( state, title, path );
		};
	</script>
	<?php
	
	// Renders Dashboard
	wp_lib_dashboard();
}

// Fines member for returning item late
function wp_lib_create_fine( $item_id, $date = false, $return = true ) {
	// Sets date to current time if unspecified
	wp_lib_prep_date( $date );

	// Fetches loan ID from item meta
	$loan_id = wp_lib_fetch_loan( $item_id );
	
	// Checks if item if actually late
	if ( !wp_lib_item_late )
		wp_lib_error( 406, true );
	
	// Fetches daily charge for a late item
	$daily_fine = get_option( 'wp_lib_fine_daily' );
	
	// Fetches days item is late
	$days_late = -wp_lib_cherry_pie( $loan_id, $date );
	
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
	if ( !is_numeric( $fine_id ) )
		wp_lib_error( 407, die );
	
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
	$GLOBALS[ 'wp_lib_notification_buffer' ][] = "{$member->name} has been charged {$fine_formatted} for the late return of {$title}";
	
	// Return item unless otherwise specified
	if ( $return )
		wp_lib_return_item( $item_id );
}

// Changes fine from unpaid to paid
function wp_lib_charge_fine( $fine_id ) {
	// Fetches (unformatted) fine status
	$fine_status = get_post_meta( $fine_id, 'wp_lib_status', true );
	
	// Checks if fine is unpaid
	if ( $fine_status == 1 ) {
		// Changes fine status to paid
		update_post_meta( $fine_id, 'wp_lib_status', 2 );
		
		// Notifies user that fine has been paid
		$GLOBALS[ 'wp_lib_notification_buffer' ][] = "Fine #{$fine_id} has been marked as paid";
	}
	// If fine is not unpaid, call error
	else {
		wp_lib_error( 309, false, 'Unpaid' );
	}
}

// Changes fine from paid to unpaid
function wp_lib_revert_fine( $fine_id ) {
	// Fetches (unformatted) fine status
	$fine_status = get_post_meta( $fine_id, 'wp_lib_status', true );
	
	// Checks if fine status is Paid
	if ( $fine_status == 2 ) {
		// Changes fine status to paid
		update_post_meta( $fine_id, 'wp_lib_status', 1 );
		
		// Notifies user that fine has been paid
		$GLOBALS[ 'wp_lib_notification_buffer' ][] = "Fine #{$fine_id} has been reverted to being marked as Unpaid";
	}
	// If the fine is not paid, call error
	else {
		wp_lib_error( 309, false, 'Paid' );
	}
}

// Cancels fine so that it is no longer is required to be paid
function wp_lib_cancel_fine( $fine_id ) {
	// Changes fine status to Cancelled
	update_post_meta( $fine_id, 'wp_lib_status', 3 );
	
	// Notifies user that fine has been cancelled
	$GLOBALS[ 'wp_lib_notification_buffer' ][] = "Fine #{$fine_id} has been cancelled";
}

// Makes string plural if needed, returns un-pluralised string otherwise
function wp_lib_plural( $value, $string, $plural = 's' ) {
	// If string does not need pluralising, return string with value only
	if ( $value == 1 || $value == -1 ) {
		$string = str_replace( '\v', $value, $string );
		$string = str_replace( '\p', '', $string );
	}
	// If string needs pluralising, replace \p with chosen plural ('s' by default)
	else {
		$string = str_replace( '\v', $value, $string );
		$string = str_replace( '\p', $plural, $string );
	}
	
	return $string;
}

// Formats money according to user's preferences
function wp_lib_format_money( $value ) {
	// Fetches user's preferred currency symbol
	$symbol = '&pound';
	
	// Fetches user's preferred currency position (before or after the value)
	$position = get_option( 'wp_lib_currency_position' );
	
	// Ensures number has correct number of decimal places
	$value = number_format( $value, 2 );
	
	// Formats $value with currency symbol at preferred position
	if ( $position == 1 )
		$value = $symbol . $value;
	elseif ( $position == 0 )
		$value = $value . $symbol;
	else
		wp_lib_error( 111, true );
		
	return $value;
}

// Fetches date from meta then formats
function wp_lib_prep_date_column( $id, $key ) {
	// Fetches date from post meta using given key
	$date = get_post_meta( $id, $key, true );
	
	// If date is valid returns formatted date
	if ( is_numeric( $date ) )
		return date( 'd-m-y', $date );
	// Otherwise return dash to show missing information
	else
		return '-';
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

// Formats a URL to manage the member with the given ID
function wp_lib_format_manage_member( $member_id ) {
	return admin_url( "edit.php?post_type=wp_lib_items&page=dashboard&member_id={$member_id}&item_action=manage-member" );
}

// Formats a URL to manage the item with the given ID
function wp_lib_format_manage_item( $item_id ) {
	return admin_url( "edit.php?post_type=wp_lib_items&page=dashboard&item_id={$item_id}&item_action=manage-item" );
}

// Formats a URL to manage the fine with the given ID
function wp_lib_format_manage_fine( $fine_id ) {
	return admin_url( "edit.php?post_type=wp_lib_items&page=dashboard&fine_id={$fine_id}&item_action=manage-fine" );
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

// Fetches member's name and, if needed, formats as a hyperlink. Falls back to archived name if needs be
function wp_lib_fetch_member_name( $post_id, $hyperlink = true, $array = false, $ending = true ) {
	// Fetches member taxonomy as an object
	$member = get_the_terms( $post_id, 'wp_lib_member' )[0];
	
	// If member exists
	if ( $member ) {
		// Fetches member name from taxonomy object
		$member_name = $member->name;
		
		// If array if to be returned, set member status
		if ( $array )
			$output['deleted'] = false;
	}
	
	// If member has been deleted
	if ( !$member ) {
		// Fetches archive from fallback (loan/fine) meta and fetches member's name from that archive
		$member_name = get_post_meta( $post_id, 'wp_lib_archive', true )['member-name'];
		
		// Suffixes member name explaining that the member was deleted
		if ( $ending )
			$member_name .= ' (member deleted)';
		
		// If array is to be returned, adds to array
		if ( $array )
			$output['deleted'] = true;
	}
	elseif ( $hyperlink ) {
		// Fetches url to manage item
		$url = wp_lib_format_manage_member( $member->term_id );
		
		// Formats title as hyperlink
		$member_name = "<a href=\"{$url}\">{$member_name}</a>";
	}
	
	// If array was wanted, returns array including member status
	if ( $array ) {
		$output['name'] = $member_name;
		return $output;
	}
	
	// Otherwise just returns member's name (as a hyperlink or otherwise)
	else
		return $member_name;
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

// Sanitizes phone number
function wp_lib_sanitize_num( $string ){
	// Strips every character from the string that is not a number, a space, + or -
	return preg_replace('/[^0-9|^\+|^\s|^-]/', '', $string );
}

// Checks for appropriate template in current theme, loads plugin's default template on failure
function wp_lib_template( $template ) {
	if ( get_post_type() == 'wp_lib_items' ) {
		if ( is_archive() ) {
			$theme_file = locate_template( array ( 'archive-wp_lib_items.php' ) );
			if ($theme_file != ''){
				return $theme_file;
			}
			else {
				return plugin_dir_path(__FILE__) . '/templates/archive-wp_lib_items.php';
			}
		}
		elseif ( is_single() ) {
			$theme_file = locate_template( array ( 'single-wp_lib_items.php' ) );
			if ($theme_file != ''){
				return $theme_file;
			}
			else {
				return plugin_dir_path(__FILE__) . '/templates/single-wp_lib_items.php';
			}
		}
	}
	return $template;
}

// Returns explanation of error given error code
function wp_lib_error( $error_id, $die = false, $param = 'PARAM NOT GIVEN' ) {
	// Checks if error code is valid and error exists, if not returns error
	if ( !is_numeric( $error_id ) )
		wp_lib_error( 901, true );
	
	// Array of all error codes and their explanations
	// 1xx - Core functionality failure
	// 2xx - General loan/return systems error
	// 3xx - Invalid loan/return parameters
	// 4xx - Error loaning/returning item or fining user
	// 9xx - Error processing error
	$all_errors = array(
		901 => 'Error encountered while processing error (error code not a number)',
		902 => 'Error encountered while processing error (error does not exist)',
		110 => 'DateTime neither positive or negative',
		111 => 'Unexpected currency position',
		112 => 'Insufficient permission',
		200 => 'No instructions known for given action',
		201 => "No {$param} status found for given value",
		202 => 'Loans do not have management pages, but I appreciate your curiosity!',
		203 => 'Loan not found in item\'s loan index',
		301 => "{$param} ID failed to validate (not an integer)",
		302 => 'No loans found for that item ID',
		304 => 'No member found with that ID',
		305 => 'No valid item found with that ID, check if item is a draft or in the trash',
		306 => 'No valid loan found with that ID',
		307 => 'Given dates result in an impossible or impractical loan length',
		308 => 'No valid fine found with that ID',
		309 => "Fine has unexpected status. Was expecting status {$param}",
		400 => 'Loan creation failed for unknown reason, sorry :/',
		401 => 'Can not loan item, it is already on loan or not allowed to be loaned.<br/>This can happen if you have multiple tabs open or refresh the loan page after a loan has already been created.',
		402 => 'Item not on loan (Loan ID not found in item meta)<br/>This can happen if you refresh the page having already returned an item',
		403 => 'Loan not found (Loan ID found in item meta but no loan found that ID). The item has now been cleaned of all loan meta to attempt to resolve the issue. Refresh the page.',
		405 => 'Loan is missing due date',
		406 => 'Item is/was not late on given date, mate',
		407 => 'Fine creation failed for unknown reasons, sorry :/',
		408 => 'Recursive Scheduling Engine returned unexpected value',
		409 => 'Loan status reports item is not currently on loan'
	);
	
	// Checks if error exists, if not returns error
	if ( !array_key_exists( $error_id, $all_errors ) )
		wp_lib_error( 902, true );
	
	// Fetches error explanation from array
	$error_text = $all_errors[$error_id];
	
	// Formats and renders error
	wp_lib_render_error( "<strong style=\"color: red;\">Error {$error_id}: {$error_text}</strong>" );

	// If error necessitates killing the script, error kills script
	if ( $die )
		die();
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

// Adds plugin url to front of string (e.g. authors -> library/authors)
function wp_lib_prefix_url( $option, $slug ) {
	// Gets main public slug ('wp-librarian' by default, usually something like 'library')
	$main_slug = get_option( 'wp_lib_slug', 'wp-librarian' );
	
	// Fetches specific slug e.g. 'authors'
	$sub_slug = get_option( $option, $slug );
	
	// Constructs and returns concatenation of the two slugs
	return $main_slug . '/' . $sub_slug;
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

function wp_lib_render_error( $message ){
	?>
	<div class="wp-lib-error error">
		<p><?= $message ?></p>
	</div>
	<?php
}

function wp_lib_render_notification( $message ){
	?>
	<div class="wp-lib-notification updated">
		<p><?= $message ?></p>
	</div>
	<?php
}
?>