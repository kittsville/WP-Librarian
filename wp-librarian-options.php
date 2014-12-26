<?php
/*
 * WP-LIBRARIAN OPTIONS
 * Default WP-Librarian Options
 * These are added when WP-Librarian is first activated
 */
	
$options = array(
	/* -- Library Options -- */
	/* Settings relating to loaning/returning systems */
	array(
		// Loan length in days
		'key'		=> 'wp_lib_loan_length',
		'default'	=> array(12)
	),
	array(
		// Fine length (per day)
		'key'		=> 'wp_lib_fine_daily',
		'default'	=> array(0.20)
	),
	/* -- Slugs -- */
	/* Sections of site urls used when accessing plugin pages e.g. my-site.com/wp-librarian */
	array(
		'key' 		=> 'wp_lib_slugs',
		'default'	=> array(
			'wp-librarian',	// The slug for library items
			'item', 		// The sub-slug for single items e.g. library/item/moby-dick
			'author',		// The slug for viewing authors
			'type'			// The slug for viewing media types
		)
	),
	
	/* -- Formatting -- */
	/* Settings relating to plugin presentation */
	array(
		// Whether to create the default media types (Books, DVDs etc. )
		'key' 		=> 'wp_lib_default_media_types',
		'default'	=> array(3)
	),
	array(
		// The separator for item taxonomies (the comma between authors)
		'key' 		=> 'wp_lib_taxonomy_spacer',
		'default'	=> array(', ')
	),
	array(
		// Currency Symbol and position relative to the numerical value (1 - Before: £20, 0 - After: 20£)
		'key' 		=> 'wp_lib_currency',
		'default'	=> array('&pound;',2)
	),
	
	/* -- Dashboard -- */
	/* Settings relating to the Library Dashboard */
	
	array(
		// Whether to automatically search for an item with the given barcode when the given length is reached
		'key'		=> 'wp_lib_barcode_config',
		'default'	=> array(
			false,
			8
		)
	)
);

// Iterates over all options, adding they if they do not exist or are invalid
foreach ( $options as $option ) {
	// Fetches existing option
	$existing_option = get_option( $option['key'] );
	
	// If existing option is false (no option found) or empty (user has updated option to an empty string), reset option to default
	if ( $existing_option === false || $existing_option === '' )
		update_option( $option['key'], $option['default'] );
}

?>