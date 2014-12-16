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
		'default'	=> 12
	),
	array(
		// Fine length (per day)
		'key'		=> 'wp_lib_fine_daily',
		'default'	=> 0.20
	),
	/* -- Slugs -- */
	/* Sections of site urls used when accessing plugin pages e.g. my-site.com/wp-librarian */
	array(
		// The slug for library items
		'key' 		=> 'wp_lib_main_slug',
		'default'	=> 'wp-librarian'
	),
	array(
		// The sub-slug for single items e.g. library/item/moby-dick
		'key'		=> 'wp_lib_single_slug',
		'default'	=> 'item'
	),
	array(
		// The slug for viewing authors
		'key' 		=> 'wp_lib_authors_slug',
		'default'	=> 'authors'
	),
	array(
		// The slug for viewing media types
		'key' 		=> 'wp_lib_media_type_slug',
		'default'	=> 'type'
	),
	
	/* -- Formatting -- */
	/* Settings relating to plugin presentation */
	array(
		// Whether to create the default media types (Books, DVDs etc. )
		'key' 		=> 'wp_lib_default_media_types',
		'default'	=> true
	),
	array(
		// The separator for item taxonomies (the comma between authors)
		'key' 		=> 'wp_lib_taxonomy_spacer',
		'default'	=> ', '
	),
	array(
		// Currency Symbol
		'key' 		=> 'wp_lib_currency_symbol',
		'default'	=> '&pound;'
	),
	array(
		// Currency position relative to the numerical value (1 - Before: £20, 0 - After: 20£)
		'key' 		=> 'wp_lib_currency_position',
		'default'	=> '2'
	),
	
	/* -- Dashboard -- */
	/* Settings relating to the Library Dashboard */
	
	array(
		// Whether to automatically search for an item with the given barcode when the given length is reached
		'key'		=> 'wp_lib_barcode_config',
		'default'	=> array(
			'autoFetch'	=> false,
			'length'	=> 8
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