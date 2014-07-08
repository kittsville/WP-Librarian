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
	// If item can be loaned and is available, url is made to take user to loans Dashboard to loan item
	if ( wp_lib_loanable( $item_id ) ) {
		$status = 'Available';
	}
	
	// If item is on loan link is composed to return item
	elseif ( wp_lib_on_loan( $item_id ) ) {
		$status = 'On Loan';
		if ( wp_lib_is_librarian() ) {
			if ( !$short ) {
				$details = ' to ' . array_values( get_the_terms( $item_id, 'wp_lib_member' ) )[0]->name;
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
	if ( wp_lib_is_librarian() && !$no_url ) {
		// String preparation
		$url = admin_url( "edit.php?post_type=wp_lib_items&page=dashboard&item_id={$item_id}&item_action=manage" );
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

// Checks if item is currently on loan, returns true if so
function wp_lib_on_loan( $item_id ) {
	// Fetches all members assigned to item
	$loan_already = get_the_terms( $item_id, 'wp_lib_member' );
	
	// If wp_lib_member is not assigned and returns false, then item is not on loan
	$loan_already = ( $loan_already == false ? false : true );
	
	return $loan_already;
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

// Checks if item is allowed to be loaned and not currently on loan
function wp_lib_loanable( $item_id ) {
	if ( wp_lib_loan_allowed( $item_id ) && !wp_lib_on_loan( $item_id ) )
		return true;
	else
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
		
	// Checks if ID belongs to a published/private library item
	if ( !get_post_type( $item_id ) == 'wp_lib_items' || !( get_post_status( $item_id ) == 'publish' || get_post_status( $item_id ) == 'private' ) )
		wp_lib_error( 305, true );

	return $item_id;
}

// Validates loan ID
function wp_lib_check_loan_id( $loan_id ) {
	// Checks if ID is actually a number
	if ( !is_numeric( $loan_id ) )
		wp_lib_error( 301, true, 'Loan' );
	
	// Checks if ID belongs to a published library item
	if ( !get_post_type( $loan_id ) == 'wp_lib_loans' || !get_post_status( $loan_id ) == 'publish' )
		wp_lib_error( 306, true );

	return $loan_id;
}

// Calculates days until item needs to be returned, returns negative if item is late
function wp_lib_cherry_pie( $loan_id, $date ) {
	// If no date is given, use current date
	if ( $date == false )
		$date = new DateTime();

	// Fetches item due date from loan meta
	$due_date = get_post_meta( $loan_id, 'wp_lib_due_date', true );

	// If loan doesn't have a due date, error is thrown
	if ( $due_date == '' )
		wp_lib_error( 405, true );

	// Converts string to DateTime object
	$due_date = DateTime::createFromFormat( 'U', $due_date);
	
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
function wp_lib_fetch_loan( $item_id ) {
	// Fetches loan ID from item metadata
	$loan_id = get_post_meta( $item_id, 'wp_lib_loan_id', true );
	
	// Validates loan ID
	if ( $loan_id == '' )
		wp_lib_error( 402, true );
	
	// Checks if loan with that ID actually exists
	if ( get_post_status( $loan_id ) == false ) {
		wp_lib_clean_item( $item_id );
		wp_lib_error( 403, true );
	}
		
	return $loan_id;
}

// Creates an item loan and attaches the member to the item so it can not be re-loaned
// If $start_date is not set loan is from current date
// If $loan_duration is not set loan will be the default length (option 'wp_lib_loan_length')
function wp_lib_create_loan( $item_id, $member_id, $loan_duration = false, $start_date = false ) {
	// Checks if $item_id is valid
	wp_lib_check_item_id( $item_id );

	// Checks if item can actually be loaned
	if ( !wp_lib_loanable( $item_id ) )
		wp_lib_error( 401, true );
	
	// Checks if given member ID is valid
	wp_lib_check_member_id( $member_id );
	
	// As member ID is valid, member is fetched
	$member = get_term_by( 'id', absint( $member_id ), 'wp_lib_member' );
		
	// Assigns the member to the item, so that it can not be loaned twice
	wp_set_post_terms( $item_id, $member->name, 'wp_lib_member' );
	
	// Gets the item's name and the current date
	$title = get_the_title( $item_id );
	$date = date('d-m-y');
	
	echo "Loaning {$title} to {$member->name}<br />";
	
	// Creates arguments for loan
	$post = array(

		'post_status'		=> 'publish',
		'post_type'			=> 'wp_lib_loans',
		'ping_status'		=> 'closed',
		'tax_input'			=> [ array( 'wp_lib_member' => "{$member->name}" ) ],
	);
	
	// Creates the loan, a custom post type that holds useful meta about the loan
	$loan_id = wp_insert_post( $post, true );
	
	// If post was not successfully created, item is unassigned to member and function is killed with error message and sad face
	if ( !is_numeric( $loan_id ) ) {
		wp_delete_object_term_relationships( $item_id, 'wp_lib_member' );
		wp_lib_error( 400, die );
	}

	// Creates item due date. Adds days to current date using either the specified loan duration or the default loan length
	// || $loan_duration < 1
	if ( !is_numeric( $loan_duration ) ) {
		// If loan length was not provided or is invalid, default is used
		$length = get_option( 'wp_lib_loan_length', 12 );
		$due_date = time() + ($length * 24 * 60 * 60);
		
	} elseif ( $loan_duration > 25550 ) {
		// If loan length is longer than 70 years, error is thrown
		wp_lib_error( 307, true );
		
	} else {
		// Otherwise loan duration is suitable and is used
		$due_date = time() + ($loan_duration * 24 * 60 * 60);
	}
	
	// Stores member/item information in case either is deleted
	$archive = array(
		'member-name'	=> $member->name,
		'item-name'		=> $title,
	);
	
	// Saves item due date, loan holder, item id to the loan's post meta
	add_post_meta( $loan_id, 'wp_lib_start_date', time() );
	add_post_meta( $loan_id, 'wp_lib_due_date', $due_date );
	add_post_meta( $loan_id, 'wp_lib_archive', $archive );
	add_post_meta( $loan_id, 'wp_lib_item', $item_id );
	add_post_meta( $loan_id, 'wp_lib_status', 1 );
	
	// Saves loan's post ID to the item's post meta
	add_post_meta( $item_id, 'wp_lib_loan_id', $loan_id );
	
	echo "Loan of {$title} to {$member->name} was successful!<br />";
	
}

// Returns a loaned item, allowing it to be re-loaned
function wp_lib_return_item( $item_id, $date = false, $override = false ) {
	// Sanitizes $date
	if ( !is_numeric( $date ) )
		$date = false;
	
	// Fetches loan ID using item ID
	$loan_id = wp_lib_fetch_loan( $item_id );
	
	// If item is late, render resolution page
	if ( wp_lib_item_late( $loan_id, $date ) && !$override )
		wp_lib_render_resolution( $item_id, $date );
	else {
		// Clears item's Member taxonomy
		wp_delete_object_term_relationships( $item_id, 'wp_lib_member' );
		
		// Removes loan ID from item meta
		delete_post_meta($item_id, 'wp_lib_loan_id' );
		
		// Sets loan status to 'closed'
		update_post_meta( $loan_id, 'wp_lib_status', 2 );
		
		// Sets loan return meta to given/current date
		add_post_meta( $loan_id, 'wp_lib_end_date', time() );
		
		// Informs user of successful item return
		echo "Loan has been successfully returned";
	}
}

// Allows users to view, manage or create loans from a central dashboard
function wp_lib_loans_dashboard() {
	require_once( plugin_dir_path(__FILE__) . '/wp-librarian-loans-dashboard.php' );
}

// Fines member for returning item late
function wp_lib_create_fine( $item_id, $date ) {
	// Fetches loan ID from item meta
	$loan_id = wp_lib_fetch_loan( $item_id );
	
	// Checks if item if actually late
	if ( !wp_lib_item_late )
		wp_lib_error( 406, true );
	
	// Fetches daily charge for a late item
	$daily_fine = get_option( 'wp_lib_fine_daily' );
	
	// Fetches days item is late
	$days_late = -wp_lib_cherry_pie( $loan_id, false );
	
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

		'post_title'		 => "Fine of {$fine_formatted} to {$member->name} on {$date}",
		'post_status'		=> 'publish',
		'post_type'			=> 'wp_lib_fines',
		'ping_status'		=> 'closed',
		'tax_input'			=> [ array( 'wp_lib_member' => "{$member->name}" ) ],
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
	add_post_meta( $loan_id, 'wp_lib_archive', $archive );
	add_post_meta( $loan_id, 'wp_lib_item', $item_id );
	add_post_meta( $loan_id, 'wp_lib_member', $member->term_id );
	add_post_meta( $loan_id, 'wp_lib_status', 3 );
	add_post_meta( $loan_id, 'wp_lib_fine', $fine );
	
	echo "Fine of {$fine} to {$member->name} for {$title} was successful!<br />";
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
		$value = $value . $position;
	else
		wp_lib_error( 111, true );
		
	return $value;
}

// Fetches date from meta then formats
function wp_lib_process_date_column( $id, $key ) {
	// Fetches date from post meta using given key
	$date = get_post_meta( $id, $key, true );
	
	// If date is valid returns formatted date
	if ( is_numeric( $date ) )
		return date( 'd-m-y', $date );
	// Otherwise return dash to show missing information
	else
		return '-';
}

// Cancels loan of item that has a since corrupted loan attached to it
// This function should not be called under regular operation and should definitely not used to return an item
function wp_lib_clean_item( $item_id ){
	// Clears item's Member taxonomy
	wp_delete_object_term_relationships( $item_id, 'wp_lib_member' );

	// Removes loan ID from item meta
	delete_post_meta($item_id, 'wp_lib_loan_id' );
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
function wp_lib_error( $error_id, $die = false, $param = '' ) {
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
		200 => 'No instructions known for given action',
		301 => "{$param} ID failed to validate (not an integer)",
		304 => 'No member found with that ID',
		305 => 'No valid item found with that ID. Check if item is a draft or in the trash.',
		306 => 'No loan found with that ID',
		307 => 'Loan length longer than member is likely to live for',
		400 => 'Loan creation failed for unknown reason, sorry :/',
		401 => 'Can not loan item, it is already on loan or not allowed to be loaned.<br/>This can happen if you have multiple tabs open or refresh the loan page after a loan has already been created.',
		402 => 'Item not on loan (Loan ID not found in item meta)<br/>This can happen if you refresh the page having already returned an item',
		403 => 'Loan not found (Loan ID found in item meta but no loan found that ID). The item has now been cleaned of all loan meta to attempt to resolve the issue. Refresh the page.',
		405 => 'Loan is missing due date',
		406 => 'Item is/was not late on given date, mate',
		407 => 'Fine creation failed for unknown reasons, sorry :/',
	);
	
	// Checks if error exists, if not returns error
	if ( !array_key_exists( $error_id, $all_errors ) )
		wp_lib_error( 902, true );
	
	// Fetches error explanation from array
	$error_text = $all_errors[$error_id];
	
	// Formats error
	$formatted_error = "<strong style=\"color: red;\">Error {$error_id}: {$error_text}</strong>";
	
	// If error necessitates killing the script, error kills script
	if ( $die )
		die( $formatted_error );

	return $formatted_error;
}

// Dumps variables in a pretty way
function wp_lib_var_dump( $var ) {
	echo '<pre>';
	echo var_dump( $var );
	echo '</pre>';
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
	$main_slug = get_option( 'wp_lib_slug', 'wp-librarian' );
	$sub_slug = get_option( $option, $slug );
	$full_slug = $main_slug . '/' . $sub_slug;
	return $full_slug;
}

// Renders the header for the Item management page
function wp_lib_render_management_header( $item_id ) {
	$title = get_the_title( $item_id );
	$status = wp_lib_prep_item_available( $item_id, true );
	?>
	<h2>Managing: <?= $title ?></h2>
	<p><strong>Status:</strong> <?= $status ?></p>
	
	<?php
}

?>