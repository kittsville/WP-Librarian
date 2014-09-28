<?php
/* 
 * WP-LIBRARIAN HELPERS
 * These are a collection of various useful functions used by WP-Librarian to operate
 */

	/* -- Sanitising Functions -- */

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

	/* -- Data Validation Functions -- */

// AJAX Wrapper for wp_lib_valid_item_id
function wp_lib_check_item_id( $item_id ) {
	if ( !wp_lib_valid_item_id( $item_id ) )
		wp_lib_page_dashboard();
}

// AJAX Wrapper for wp_lib_valid_member_id
function wp_lib_check_member_id( $member_id ) {
	if ( !wp_lib_valid_member_id( $member_id ) )
		wp_lib_page_dashboard();
}

// AJAX Wrapper for wp_lib_valid_fine_id
function wp_lib_check_fine_id( $fine_id ) {
	if ( !wp_lib_valid_fine_id( $fine_id ) )
		wp_lib_page_dashboard();
}

// AJAX Wrapper for wp_lib_valid_loan_id
function wp_lib_check_loan_id( $loan_id ) {
	if ( !wp_lib_valid_loan_id( $loan_id ) )
		wp_lib_page_dashboard();
}

// Checks if member ID is valid
function wp_lib_valid_member_id( $member_id ) {
	// Attempts to fetch member with given ID, fetching function handles sanitization
	if ( wp_lib_fetch_member( $member_id ) )
		return true;
	else
		return false;
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
	if ( get_post_type( $item_id ) != 'wp_lib_items' || !( $item_status == 'publish' || $item_status == 'private' ) ) {
		wp_lib_error( 305, false, $item_id );
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
	if ( get_post_type( $loan_id ) != 'wp_lib_loans' || !get_post_status( $loan_id ) != 'publish' ) {
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
	if ( get_post_type( $fine_id ) != 'wp_lib_fines' || get_post_status( $fine_id ) != 'publish' ) {
		wp_lib_error( 308 );
		return false;
	}

	return true;
}

// Checks if ID belongs to valid Library item, returns object type if true
function wp_lib_get_object_type( $post_id ) {
	// Checks if item ID exists
	if ( !$post_id ) {
		wp_lib_error( 300, false, 'Library Object' );
		return false;
	}

	// Checks if ID is a number
	if ( !is_numeric( $post_id ) ) {
		wp_lib_error( 301, false, 'Library Object' );
		return false;
	}
	
	// Gets object's post type
	$post_type = get_post_type( $post_id );
	
	// Returns Library object type
	switch ( get_post_type( $post_id ) ) {
		case 'wp_lib_items':
			return 'item';
		
		case 'wp_lib_loans':
			return 'loan';
		
		case 'wp_lib_fines':
			return 'fine';
	}
	
	// Otherwise object does not belong to the Library
	wp_lib_error( 305, false, $item_id );
	return false;
}

	/* -- URLs and Slugs -- */
 
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
	$args = array(
		'dash_page'	=> 'manage-member',
		'member_id'	=> $member_id
	);
	
	return wp_lib_format_dash_url( $args );
}

// Formats a URL to manage the item with the given ID
function wp_lib_format_manage_item( $item_id ) {
	$args = array(
		'dash_page'	=> 'manage-item',
		'item_id'	=> $item_id
	);
	
	return wp_lib_format_dash_url( $args );
}

// Formats a URL to manage the fine with the given ID
function wp_lib_format_manage_fine( $fine_id ) {
	$args = array(
		'dash_page'	=> 'manage-fine',
		'fine_id'	=> $fine_id
	);
	
	return wp_lib_format_dash_url( $args );
}

// Formats and returns a Library Dashboard URL with any desired variables formatted as GET parameters
function wp_lib_format_dash_url( $params = false ) {
	// Constructs base Library Dashboard URL
	$url = admin_url( 'edit.php?post_type=wp_lib_items&page=dashboard' );
	
	// Adds all, if any, parameters to the URL
	if ( $params ) {
		foreach ( $params as $key => $value ) {
			$url .= '&' . $key . '=' . $value;		
		}
	}

	return $url;
}

// Formats script's URL using its name. Presumes default script dir is used
function wp_lib_script_url( $name ) {
	return plugins_url( '/scripts/'. $name . '.js', __FILE__ );
}

// Formats CSS file's URL using its name. Presumes default css dir is used
function wp_lib_style_url( $name ) {
	return plugins_url( '/css/'. $name . '.css', __FILE__ );
}

	/* -- Dates and times -- */

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

	/* -- Localisation -- */

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

	/* -- AJAX -- */
	/* Functions that assist preparing data for the client/server */

// Given member object, returns rendered member management header
function wp_lib_prep_member_management_header( $member ) {
	return array();
}

// Given fine ID, returns rendered fine management header
function wp_lib_prep_fine_management_header( $fine_id ) {
	return array();
}

// Given item ID, returns rendered item management header
function wp_lib_prep_item_management_header( $item_id ) {
	// Fetches title of item e.g. 'Moby-Dick'
	$title = get_the_title( $item_id );
	
	// Fetches post meta
	$meta = get_post_meta( $item_id );
	
	// Item meta fields to be displayed in management header
	$meta_fields = array(
		array( 'Item ID', $item_id),
		array( 'Condition', wp_lib_format_item_condition( $meta['wp_lib_item_condition'][0] ) )
	);
	
	// Taxonomy terms to be fetched
	$tax_terms = array(
		'Media Type'=> 'wp_lib_media_type',
		'Author'	=> 'wp_lib_author'
	);
	
	// Iterates through taxonomies, fetching their terms and adding them to the meta field array
	foreach ( $tax_terms as $tax_name => $tax_key ) {
		// Fetches terms for given taxonomy
		$terms = get_the_terms( $item_id, $tax_key );
		
		// If no terms or an error were returned, skip
		if ( !$terms || is_wp_error( $terms ) )
			continue;
			
		// Iterates through tax terms, formatting them
		foreach ( $terms as $term ) {
			// Adds tax term to term array
			$terms_array[] = array( $term->name, get_term_link( $term ) );
		}
		
		// Adds tax terms to meta fields
		$meta_fields[] = array( $tax_name, $terms_array );
		
		unset( $terms_array );
	}
	
	// Adds item status as last meta field
	$meta_fields[] = array( 'Status', wp_lib_prep_item_available( $item_id, true ) );
	
	// Prepares management header
	$header = array(
		array(
			'type'		=> 'div',
			'classes'	=> 'item-man',
			'inner'		=> array(
				array(
					'type'	=> 'metabox',
					'title'	=> 'Details',
					'fields'=> $meta_fields
				)
			)
		)
	);
	
	return $header;
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
		wp_lib_error( 205, array( 'fine', $member_array['name'] ) );
	
	// Fetches item title and if the item still exists
	$title_array = wp_lib_format_item_title( $item_id, $fine_id, true, true );
	
	// If item no longer exists, inform user how this limits their options
	if ( $title_array['deleted'] )
		wp_lib_error( 205, array( 'item', $title_array['title'] ) );
	
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
	// Sets up loan history parameters
	$args = array(
		'post_type' => 'wp_lib_loans',
		'tax_query' => array(
			array(
				'taxonomy'	=> 'wp_lib_member',
				'field'		=> 'term_id',
				'terms'		=> $member_id
			)
		)
	);	
	
	// Creates query of all loans attached to this member
	$query = new WP_Query( $args );
}

// Creates option element for each member in the library, stored as an array
function wp_lib_prep_member_options() {
	// Fetches list of all Members
	$members = get_terms( 'wp_lib_member', 'hide_empty=0' );
	
	// Initialises options with default option
	$options[] = array(
		'value'	=> '',
		'html'	=> 'Member'
	);
	
	// Provided there is at least one member
	if ( $members ) {
		// Iterates through members, creating an option for each
		foreach ($members as $member) {
			$options[] = array(
				'value'	=> $member->term_id,
				'html'	=> $member->name
			);
		}
	}
	
	return $options;
}

	/* -- Miscellaneous -- */

// Fetches member object given member ID
function wp_lib_fetch_member( $member_id ) {
	// Checks if member ID was given and is non 0
	if ( !$member_id ) {
		wp_lib_error( 300, false, 'Member' );
		return false;
	}
	// Checks if member_id is valid
	if ( !is_numeric( $member_id ) ) {
		wp_lib_error( 301, false, 'Member' );
		return false;
	}
	
	// Attempts to fetch member object
	$member = get_term_by( 'id', absint( $member_id ), 'wp_lib_member' );
	
	if ( !$member ) {
		wp_lib_error( 304 );
		return false;
	} else {
		return $member;
	}
}


// Fetches member's name and, if needed, formats as a hyperlink. Uses archived member name on failure
function wp_lib_fetch_member_name( $item_id, $hyperlink = true, $array = false, $ending = true ) {
	// Fetches member taxonomy as an object
	$member = wp_lib_fetch_member( $member_id );
	
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
		$member_name = get_post_meta( $item_id, 'wp_lib_archive', true )['member-name'];
		
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

// Returns formatted item condition given item number
function wp_lib_format_item_condition( $number, $full = true ) {
	// All possible conditions item can be in
	$states = array(
		4 => 'Excellent',
		3 => 'Good',
		2 => 'Fair',
		1 => 'Poor',
		0 => 'Very Poor'
	);
	
	// If item has not been given a state, return placeholder
	if ( !array_key_exists( $number, $states ) )
		return '-';
	
	if ( $full )
		return $number . ' - ' . $states[$number];
	else
		return $states[$number];	
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

// Prepares taxonomy and metabox information for theme use
function wp_lib_fetch_meta( $item_id ) {
	// Metabox data is fetched and relevant functions are called to format the data
	$meta_array = array(
		'media type'	=> wp_lib_prep_meta( get_the_terms( $item_id, 'wp_lib_media_type' ), 'Media Type' ),
		'authors'		=> wp_lib_prep_meta( get_the_terms( $item_id, 'wp_lib_author' ), 'Author' ),
		'donor'			=> wp_lib_prep_meta( get_the_terms( $item_id, 'wp_lib_donor' ), 'Donor' ),
		'isbn'			=> wp_lib_prep_meta( get_post_meta( $item_id, 'wp_lib_item_isbn', true ), 'ISBN' ),
		'available'		=> wp_lib_prep_meta( wp_lib_prep_item_available( $item_id ), 'Status' ),
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
function wp_lib_prep_meta( $tax_array, $bold_name ) {
	// If tax array doesn't exist, return empty string
	if ( $tax_array == false )
		return '';
	
	// If there is one than one of a taxonomy item it makes the term plural (Author -> Authors)
	if ( count( $tax_array ) > 1 )
		$bold_name .= 's';
	
	// Formats meta name
	$item_string = '<strong>' . $bold_name . ': </strong>';
	
	// If $tax_array is not an array, return formatted string before foreach loop
	if ( !is_array( $tax_array ) )
		return $item_string . $tax_array;
	
	// Iterates through tax items 
	foreach ( $tax_array as $tax_item ) {
		// Gets tax term's URL
		$tax_url = get_term_link( $tax_item );
		
		// Skips term if error occurred
		if ( is_wp_error( $tax_url ) )
			continue;
		
		// Formats tax item as link
		$formatted_values[] = '<a href="' . esc_url( $tax_url ) . '">' . $tax_item->name . '</a>';
	}
	
	// If there are no formatted values, return empty string
	if ( !isset( $formatted_values ) )
		return '';
	
	// Implodes array into string separated by users preferred spacer
	return $item_string . implode( get_option( 'wp_lib_taxonomy_spacer', ', ' ), $formatted_values );
}

?>