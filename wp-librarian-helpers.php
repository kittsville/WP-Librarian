<?php
/* 
 * WP-LIBRARIAN HELPERS
 * These are a collection of various useful functions used by WP-Librarian to operate
 */

	/* Sanitising Functions */

// Sanitizes phone number
function wp_lib_sanitize_phone_number( $string ) {
	// Strips every character from the string that is not a number, a space, + or -
	return preg_replace('/[^0-9|^\+|^\s|^-]/', '', $string );
}

// Sanitizes input to
function wp_lib_sanitize_number( $raw ) {
	return ereg_replace('[^0-9]', '', $raw );
}

// Sanitizes item cover type
function wp_lib_sanitize_item_cover( $raw ) {
	if ( $raw == 'hardcover' )
		return 2;
	elseif ( $raw == 'softcover' )
		return 3;
	else
		return '';
}

	/* URLs and Slugs */
 
// Adds plugin url to front of string (e.g. authors -> library/authors)
function wp_lib_prefix_url( $option, $slug ) {
	// Gets main public slug ('wp-librarian' by default, usually something like 'library')
	$main_slug = get_option( 'wp_lib_slug', 'wp-librarian' );
	
	// Fetches specific slug e.g. 'authors'
	$sub_slug = get_option( $option, $slug );
	
	// Constructs and returns concatenation of the two slugs
	return $main_slug . '/' . $sub_slug;
}

// Formats a URL to manage the member with the given ID
function wp_lib_format_manage_member( $member_id ) {
	return admin_url( "edit.php?post_type=wp_lib_items&page=dashboard&member_id={$member_id}&dash_page=manage-member" );
}

// Formats a URL to manage the item with the given ID
function wp_lib_format_manage_item( $item_id ) {
	return admin_url( "edit.php?post_type=wp_lib_items&page=dashboard&item_id={$item_id}&dash_page=manage-item" );
}

// Formats a URL to manage the fine with the given ID
function wp_lib_format_manage_fine( $fine_id ) {
	return admin_url( "edit.php?post_type=wp_lib_items&page=dashboard&fine_id={$fine_id}&dash_page=manage-fine" );
}

// Formats script's URL using its name. Presumes default script dir is used
function wp_lib_script_url( $name ) {
	return plugins_url( '/scripts/'. $name . '.js', __FILE__ );
}

// Formats CSS file's URL using its name. Presumes default css dir is used
function wp_lib_style_url( $name ) {
	return plugins_url( '/css/'. $name . '.css', __FILE__ );
}

	/* Dates and times */

// Validates given date, checking if it meets any given requirements
function wp_lib_convert_date( &$date ) {
	// Attempts to convert date into Unix timestamp
	return strtotime( $date );
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

// If a date is false, sets to current date
function wp_lib_prep_date( &$date ) {
	if ( !$date )
		$date = current_time( 'timestamp' );
}

	/* Language */

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

	/* Localisation */

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

	/* Miscellaneous */

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





























?>