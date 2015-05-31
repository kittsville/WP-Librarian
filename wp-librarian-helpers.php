<?php
/**
 * WP-LIBRARIAN HELPERS
 * These are a collection of various useful functions used by WP-Librarian to operate
 */

// No direct loading
defined( 'ABSPATH' ) OR die('No');

	/* -- Exception Handling -- */

/**
 * Generates error based on given error code and, if not an AJAX request, kills thread
 * @param int           $error_id   Error that has occurred
 * @param string|array  $param      OPTIONAL Relevant parameters to error to enhance error message (not optional for certain error messages)
 */
function wp_lib_error( $error_id, $param = null ) {
	return new WP_LIB_ERROR( $error_id, $param );
}

/*
 * Determines whether given data is an instance of a library error
 * @param   mixed   $object Data to be checked
 * @return  bool            Whether given data is a library error
 */
function wp_lib_is_error( $object ) {
	return ($object instanceof WP_LIB_ERROR);
}

	/* -- Permissions -- */

/**
* Checks if user is a librarian (or higher)
* A librarian can view and modify items, members, loans and fines, where appropriate
* @link     https://github.com/kittsville/WP-Librarian/wiki/Librarians
* @param    int|null    $user_id    OPTIONAL ID of user to be checked. Defaults to current user's ID
* @return   bool                    Whether user is a librarian
*/
function wp_lib_is_librarian( $user_id = null ) {
	if ($user_id === null and !is_user_logged_in())
		return false;
	
	return ( get_user_meta( ( is_int($user_id)? $user_id : get_current_user_id() ), 'wp_lib_role', true ) >= 5 ) ? true : false;
}

/**
* Checks if user is a library admin
* Admins have the permissions of librarians plus they can modify library settings
* @link     https://github.com/kittsville/WP-Librarian/wiki/Library-Admins
* @param    int|null    $user_id    OPTIONAL ID of user to be checked. Defaults to current user's ID
* @return   bool                    Whether user is a librarian admin
*/
function wp_lib_is_library_admin( $user_id = null ) {
	if ($user_id === null and !is_user_logged_in())
		return false;
	
	return ( get_user_meta( ( is_int($user_id)? $user_id : get_current_user_id() ), 'wp_lib_role', true ) >= 10 ) ? true : false;
}

	/* -- Sanitising Functions -- */

// Sanitizes phone number
function wp_lib_sanitize_phone_number( $string ) {
	// Strips every character from the string that is not a number, a space, + or -
	return preg_replace('/[^0-9|^\+|^\s|^-]/', '', $string );
}

// Sanitizes input to
function wp_lib_sanitize_number( $raw ) {
	return preg_replace('/[^0-9]/', '', $raw );
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

// Sanitizes HTML POST data from a checkbox
function wp_lib_sanitize_checkbox( $raw ) {
	if ( $raw == 'true' )
		return true;
	else
		return false;
}

// Sanitizes HTML POST data from checkbox from options page
function wp_lib_sanitize_option_checkbox( $raw ) {
	return ( $raw === '3' ? 3 : 2 );
}

// Converts database checkbox value to boolean
function wp_lib_prep_boolean_option( $option ) {
	return ( $option === 3 ? true : false );
}

// Sanitizes string then checks if it is a valid ISBN, returns sanitized ISBN on success or empty string on failure.
function wp_lib_sanitize_isbn( $raw ) {
	// Strips all non-numeric characters, excluding x
	$isbn = preg_replace('/[^0-9.x]/', '', strtolower( $raw ) );
	
	// Checks ISBN validity
	return wp_lib_valid_isbn( $isbn ) ? $isbn : '';
}

// Checks if given string is a valid ISBN, returns ISBN type (10/13) on success or false on failure
// Uses Cryptic's (http://stackoverflow.com/users/1592648) answer to a question on ISBN validation (http://stackoverflow.com/questions/14095778) on Stack Overflow
function wp_lib_valid_isbn( $isbn ) {
	// Sets check digit
	$check = 0;
	
	// Performs ISBN validity checks based on ISBN length
	switch ( strlen( $isbn ) ) {
		case 10:
			for ($i = 0; $i < 10; $i++) {
				if ('x' === $isbn[$i]) {
					$check += 10 * (10 - $i);
				} elseif (is_numeric($isbn[$i])) {
					$check += (int)$isbn[$i] * (10 - $i);
				} else {
					return false;
				}
			}

			return (0 === ($check % 11)) ? 10 : false;
		break;
		
		case 13:
			for ($i = 0; $i < 13; $i += 2) {
				$check += (int)$isbn[$i];
			}

			for ($i = 1; $i < 12; $i += 2) {
				$check += 3 * $isbn[$i];
			}

			return (0 === ($check % 10)) ? 13 : false;
		break;
	}
	// If string is not the length of a valid ISBN, return false
	return false;
}

// If member exists, return member ID, otherwise return empty string
function wp_lib_sanitize_donor( $member_id ) {
	return ( get_post_type( (int)$member_id ) === 'wp_lib_members' ) ? $member_id : '';
}

	/* -- URLs and Slugs -- */

function wp_lib_prep_manage_item_params( $item_id ) {
	return array(
		'dash_page' => 'manage-item',
		'item_id'   => $item_id
	);
}

function wp_lib_prep_manage_member_params( $member_id ) {
	return array(
		'dash_page' => 'manage-member',
		'member_id' => $member_id
	);
}

function wp_lib_prep_manage_loan_params( $loan_id ) {
	return array(
		'dash_page' => 'manage-loan',
		'loan_id'   => $loan_id
	);
}

function wp_lib_prep_manage_fine_params( $fine_id ) {
	return array(
		'dash_page' => 'manage-fine',
		'fine_id'   => $fine_id
	);
}

// Formats a URL to manage item with the given ID
function wp_lib_manage_item_url( $item_id ) {
	return wp_lib_format_dash_url( wp_lib_prep_manage_item_params( $item_id ) );
}

// Formats a URL to manage member with the given ID
function wp_lib_manage_member_url( $member_id ) {
	return wp_lib_format_dash_url( wp_lib_prep_manage_member_params( $member_id ) );
}

// Formats a URL to manage loan with the given ID
function wp_lib_manage_loan_url( $loan_id ) {
	return wp_lib_format_dash_url( wp_lib_prep_manage_loan_params( $loan_id ) );
}

// Formats a URL to manage fine with the given ID
function wp_lib_manage_fine_url( $fine_id ) {
	return wp_lib_format_dash_url( wp_lib_prep_manage_fine_params( $fine_id ) );
}

// Formats and returns a Library Dashboard URL with any desired variables formatted as GET parameters
function wp_lib_format_dash_url( $params = array() ) {
	// Constructs base Library Dashboard URL
	$url = admin_url( 'edit.php?post_type=wp_lib_items&page=dashboard' );
	
	// Adds any additional parameters to the base URL
	foreach ( $params as $key => $value ) {
		$url .= '&' . $key . '=' . $value;      
	}

	return $url;
}

// Returns item's title formatted as a hyperlink to manage that item
function wp_lib_manage_item_hyperlink( $item_id ) {
	return wp_lib_hyperlink( wp_lib_manage_item_url( $item_id ), get_the_title( $item_id ) );
}

// Returns member's name formatted as a hyperlink to manage that member
function wp_lib_manage_member_hyperlink( $member_id ) {
	return wp_lib_hyperlink( wp_lib_manage_member_url( $member_id ), get_the_title( $member_id ) );
}

// Creates a hyperlink given a url and some text
function wp_lib_hyperlink( $link, $text ) {
	return '<a href="' . $link . '">' . $text . '</a>';
}

// Creates dash URL element given a set of parameters
function wp_lib_prep_dash_hyperlink( $name, $params ) {
	return array(
		'type'  => 'dash-url',
		'params'=> $params,
		'html'  => $name
	);
}

// Creates Dash page element that appears as a hyperlink and dynamically loads item management page
function wp_lib_manage_item_dash_hyperlink( $item_id ) {
	return wp_lib_prep_dash_hyperlink(
		get_the_title( $item_id ),
		wp_lib_prep_manage_item_params( $item_id )
	);
}

// Creates Dash page element that appears as a hyperlink and dynamically loads member management page
function wp_lib_manage_member_dash_hyperlink( $member_id ) {
	return wp_lib_prep_dash_hyperlink(
		get_the_title( $member_id ),
		wp_lib_prep_manage_member_params( $member_id )
	);
}

// Creates Dash page element that appears as a hyperlink and dynamically loads loan management page
function wp_lib_manage_loan_dash_hyperlink( $loan_id ) {
	return wp_lib_prep_dash_hyperlink(
		'#' . $loan_id,
		wp_lib_prep_manage_loan_params( $loan_id )
	);
}

// Creates Dash page element that appears as a hyperlink and dynamically loads fine management page
function wp_lib_manage_fine_dash_hyperlink( $fine_id ) {
	return wp_lib_prep_dash_hyperlink(
		'#' . $fine_id,
		wp_lib_prep_manage_fine_params( $fine_id )
	);
}

// Creates URL for items archive
function wp_lib_item_archive_url() {
	return site_url( get_option( 'wp_lib_slugs', array('wp-librarian'))[0] );
}

	/* -- Dates and times -- */

// Validates given date, checking if it meets any given requirements
function wp_lib_convert_date( &$date ) {
	// Attempts to convert date into Unix timestamp
	$date = strtotime( $date );
}

function wp_lib_format_unix_timestamp( $timestamp ) {
	// If date is valid returns formatted date
	if ( (int) $timestamp !== 0 )
		return '<abbr title="' . date( 'Y/m/d g:i:s A', $timestamp ) . '">' . date( 'Y/m/d', $timestamp ) . '</abbr>';
	// Otherwise return dash to indicate missing/unknown information
	else
		return '-';
}

// If a date is false, sets to current date
function wp_lib_prep_date( &$date ) {
	if ( !$date )
		$date = current_time( 'timestamp' );
}

	/* -- Language -- */

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
function wp_lib_format_money( $value, $html_ent = true ) {
	// Fetches user's preferred currency symbol and currency position (symbol before or after number)
	$settings = get_option( 'wp_lib_currency', array('&pound;',2) );
	
	// Sets friendly variable names
	$symbol = $settings[0];
	$position = wp_lib_prep_boolean_option( $settings[1] );
	
	// If output doesn't need to use html entities (e.g. &pound; ), converts to actual characters (e.g. £ )
	if ( !$html_ent ) {
		$symbol = html_entity_decode( $symbol );
	}
	
	// For the silly event that a fine of nothing managed to get charged
	$value = $value == '' ? 0 : $value;
	
	// Ensures number has correct number of decimal places
	$value = number_format( $value, 2 );
	
	// Formats $value with currency symbol at preferred position
	if ( $position ) {
		return $value . $symbol;                        // 0.40EUR
	} else {
		// If currency is negative, display negation symbol before currency symbol
		if ( $value < 0 )
			return '-' . $symbol . substr( $value, 1 ); // -£0.40
		else
			return $symbol . $value;                    // £0.40
	}
}

	/* -- Debugging -- */

// Renders current plugin's version, update channel and similar information
function wp_lib_render_plugin_version() {
	// Fetches plugin's current version/update channel/build
	$version = get_option( 'wp_lib_version' );
	
	?>
	<div id="version-wrap">
		<span>
			<?php echo 'Running WP-Librarian ' .  $version['channel']; ?>
		</span>
		<span>
			<?php echo 'Version: ' . $version['version'] . ' (' . $version['nickname'] . ') Build: ' . $version['subversion']; ?>
		</span>
		<?php
			if ( WP_LIB_DEBUG_MODE === true ) {
				?>
					<span>
						<?php echo 'DEBUGGING MODE ON'; ?>
					</span>
				<?php
			}
		?>
	</div>
	<?php
}

// Dumps any number of given variables between <pre> tags
function wp_lib_var_dump() {
	// Gets all given params
	$args = func_get_args();
	
	// Wraps all dumped variables in a div
	echo '<div class="wp-lib-debug-wrap">';
	
	// For each param, var_dump between <pre> tags to format code properly in browser
	foreach ( $args as $arg ){
		echo '<pre style="background:white;">';
		var_dump( $arg );
		echo '</pre>';
	}
	
	echo '</div>';
}

// Calculates time taken to calculate a number of given functions
function wp_lib_test_functions() {
	$functions = func_get_args();
	$times_ran = 10000;
	echo 'Each function was run ' . $times_ran . ' times<br/>';
	foreach ( $functions as $key => $function ) {
		$time_start = microtime(true);
		
		$i = 0;
		
		while ( $i++ < $times_ran ) {
			$function();
		}
		
		$time_end = microtime(true);
		
		echo '<strong>Function #' . $key . ' </strong> took: ' . ( ($time_end - $time_start)/$times_ran * 1000 ) . ' milliseconds';
	}
	
	
}

	/* -- Statuses -- */

// Turns numeric loan status into readable string e.g. 1 -> 'On Loan'
function wp_lib_format_loan_status( $status ) {
	// Array of all possible states of the loan
	$strings = array(
		0   => '',
		1   => 'On Loan',
		2   => 'Returned',
		3   => 'Returned Late',
		4   => 'Returned Late (with fine)',
		5   => 'Scheduled'
	);
	
	// If given number refers to a status that doesn't exist, throw error
	if ( empty( $strings[$status] ) ) {
		return wp_lib_error( 201, 'Loan' );
	}
	
	// State is looked up in the array and returned
	return $strings[$status];
}

// Turns numeric fine status into readable string e.g. 1 -> 'Unpaid'
function wp_lib_format_fine_status( $status ) {
	// Array of all possible states of the fine
	$strings = array(
		0   => '',
		1   => 'Active',
		2   => 'Cancelled'
	);
	
	// If given number refers to a status that doesn't exist, throw error
	if ( empty( $strings[$status] ) ) {
		return wp_lib_error( 201, 'Fine' );
	}
	
	// State is looked up in the array and returned
	return $strings[$status];
}

// Turns numeric user status into readable string e.g. 5 -> Librarian
function wp_lib_format_user_permission_status( $status ) {
	// Array of all possible user permissions
	$strings = WP_LIBRARIAN::getUserRoles();
	
	// If given number refers to a status that doesn't exist, throw error
	if ( !array_key_exists( $status, $strings ) ) {
		return wp_lib_error( 201, 'User' );
	}
	
	// State is looked up in the array and returned
	return $strings[$status];
}

// Fetches and formats user permission status
function wp_lib_fetch_user_permission_status( $user_id ) {
	// Fetches status
	$status = get_user_meta( $user_id, 'wp_lib_role', true );
	
	if ( !$status )
		return '';
	else
		return wp_lib_format_user_permission_status( $status );
}

// Returns formatted item condition given item number
function wp_lib_format_item_condition( $number ) {
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
	else
		return $number . ' - ' . $states[$number];
}

	/* -- Miscellaneous -- */

/**
 * Given an array of loans, checks if a proposed loan would be viable
 * Use create_loan_index to generate and sort the ordered loan index necessary for the function
 * @param   int     $proposed_start Proposed start of new loan as a UNIX timestamp
 * @param   int     $proposed_end   Proposed end of new loan as a UNIX timestamp
 * @param   array   $loans          List of existing loans, ordered chronologically. Can't be empty array
 * @param   int     $current        Current position in array being checked by recursive_scheduling_engine()
 * @return  bool                    Whether the proposed loan would be viable
 * @todo                            Move to dedicated class or create wrapper than handles empty $loans cases
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

function wp_lib_prep_member_options( $default_option = true ) {
	// Initialises options
	$option = array();
	
	// Adds default option, if specified
	if ( $default_option ) {
		$options[] = array(
			'value' => '',
			'html'  => 'Member'
		);
	}
	
	$args = array(
		'post_type'     => 'wp_lib_members',
		'post_status'   => 'publish'
	);
	
	// Fetches all, if any, members
	$query = NEW WP_Query( $args );
	
	// Checks for any loans attached to member
	if ( $query->have_posts() ){
		// Iterates through loans
		while ( $query->have_posts() ) {
			$query->the_post();
			
			// Fetches member ID
			$member_id = get_the_ID();
			
			// Skips displaying member if member has been archived
			if ( get_post_meta( $member_id, 'wp_lib_member_archive', true ) )
				continue;
			
			// Adds member's details to the options array
			$options[] = array(
				'value' => get_the_ID(),
				'html'  => get_the_title()
			);
		}
	}
	return $options;
}

// Returns the number of items currently on loan by the member
function wp_lib_prep_members_items_out( $member_id ) {
	// Queries post table for all items marked as currently in member's possession
	$query = NEW WP_Query(array(
		'post_type'     => 'wp_lib_items',
		'post_status'   => 'publish',
		'meta_query'    => array(
			array(
				'key'       => 'wp_lib_member',
				'value'     => $member_id,
				'compare'   => 'IN'
			)
		)
	));

	// Returns number of items to post table
	return $query->post_count;
}

// Updates multiple meta values of a post
function wp_lib_update_meta( $post_id, $meta_array ) {
	foreach ( $meta_array as $key => $value ) {
		update_post_meta( $post_id, $key, $value );
	}
}

// Adds multiple meta values of a post
function wp_lib_add_meta( $post_id, $meta_array ) {
	foreach ( $meta_array as $key => $value ) {
		add_post_meta( $post_id, $key, $value );
	}
}

// Fetches post's meta and then fetches the desired meta values from the meta array
function wp_lib_prep_admin_meta( $post_id, $formatting ) {
	// Fetches all post meta
	$all_meta = get_post_meta( $post_id );
	
	// Initialises output
	$meta = array();

	// Iterates through meta formatting and fetches needed meta values for all item meta
	foreach ( $formatting as $meta_area ) {
		foreach ( $meta_area['fields'] as $field ) {
			if (isset($all_meta[$field['name']]))
				$meta[$field['name']] = $all_meta[$field['name']][0];
		}
	}
	
	// Returns prepared meta
	return $meta;
}

// Recursively searches for any Library objects connected directly or indirectly to a given object. Uses depth first searching
function wp_lib_fetch_dependant_objects( $post_id, $post_type = false, $connected_posts = array() ) {
	// If post type has not been given, fetches
	if ( !$post_type )
		$post_type = get_post_type( $post_id );
	
	// If post type requires post query
	if ( $post_type == 'wp_lib_items' || $post_type == 'wp_lib_members' ) {
		// Sets meta key to use in search
		switch ( $post_type ) {
			case 'wp_lib_items':
				$key = 'wp_lib_item';
			break;
			
			case 'wp_lib_members':
				$key = 'wp_lib_member';
			break;
		}
		
		// Sets query args
		$args = array(
			'post_type'     => 'wp_lib_loans',
			'post_status'   => 'publish',
			'meta_query'    => array(
				array(
					'key'       => $key,
					'value'     => $post_id,
					'compare'   => 'IN'
				)
			)
		);
		
		// Queries for connected posts
		$query = NEW WP_Query( $args );
		
		// If any connected loans are found, iterates through them adding them to list then searching the loans themselves
		if ( $query->have_posts() ) {
			while( $query->have_posts() ) {
				$query->the_post();
				
				// Fetches loan's ID
				$loan_id = get_the_ID();
				
				// Adds loan to connected posts list
				$connected_posts[] = array( $loan_id, get_post_type( $loan_id ) );
				
				// Calls function to check loan for connected objects
				$connected_posts = wp_lib_fetch_dependant_objects( $loan_id, 'wp_lib_loans', $connected_posts );
			}
		}
	} elseif ( $post_type == 'wp_lib_loans' ) {
		// Fetches fine ID from loan meta
		$fine_id = get_post_meta( $post_id, 'wp_lib_fine', true );
		
		// If fine ID was found, add to connected posts list
		if ( $fine_id )
			$connected_posts[] = array( $fine_id, get_post_type( $fine_id ) );
	} elseif ( $post_type == 'wp_lib_fines' ) {
		// Fetches loan ID from fine meta
		$loan_id = get_post_meta( $post_id, 'wp_lib_loan', true );
		
		// Checks if loan is already in the array
		if ( !in_array( $loan_id, $connected_posts ) )
			$connected_posts[] = array( $loan_id, get_post_type( $loan_id ) );
	}
	
	return $connected_posts;
}
