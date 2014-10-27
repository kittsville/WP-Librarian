<?php
/*
 * WP-LIBRARIAN OPTIONS
 * Default WP-Librarian Options
 * These are added when WP-Librarian is first activated
 */


	/* -- Library Options -- */
	/* Settings relating to loaning/returning systems */
	
// Loan length in days
add_option( 'wp_lib_loan_length', 12 );

// Fine length (per day)
add_option( 'wp_lib_fine_daily', 0.20 );

	/* -- Slugs -- */
	/* Sections of site urls used when accessing plugin pages e.g. my-site.com/wp-librarian */

// The slug for library items
add_option( 'wp_lib_main_slug', 'wp-librarian' );

// The slug for viewing authors
add_option( WP_LIB_AUTHORS . '_slug', 'authors' );

// The slug for viewing media types
add_option( WP_LIB_MEDIA_TYPE . '_slug', 'type' );

// The slug for viewing members
add_option( WP_LIB_MEMBERS . '_slug', 'members' );

// The slug for viewing members
add_option( WP_LIB_LOANS . '_slug', 'loans' );

// The slug for viewing fines
add_option( WP_LIB_FINES . '_slug', 'fines' );

	/* -- Formatting -- */
	/* Settings relating to plugin presentation */
	
// Whether to create the default media types (Books, DVDs etc. )
add_option( 'wp_lib_default_media_types', true );

// The separator for item taxonomies (the comma between authors)
add_option( 'wp_lib_taxonomy_spacer', ', ' );

// Currency Symbol
add_option( 'wp_lib_currency_symbol', '£' );

// Currency position relative to the numerical value (1 - Before: £20, 0 - After: 20£)
add_option( 'wp_lib_currency_position', 1 );

?>