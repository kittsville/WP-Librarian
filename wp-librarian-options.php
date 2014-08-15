<?php
/*
 * All default option are set here
 * configure them via the settings page
 */


/* General - Miscellaneous options relating to WP-Librarian's operation */
// Loan length in days
add_option( 'wp_lib_loan_length', 12 );

// Fine length (per day)
add_option( 'wp_lib_fine_daily', 0.20 );

// Currency Symbol
add_option( 'wp_lib_currency_symbol', '£' );

// Currency position (1 - before, 0 - after)
add_option( 'wp_lib_currency_position', 1 );

/* Slugs - Sections of site urls used when accessing plugin pages e.g. my-site.com/wp-librarian */
// The slug for library items
add_option( 'wp_lib_main_slug', 'wp-librarian' );

// The slug for viewing authors
add_option( 'wp_lib_authors_slug', 'authors' );

// The slug for viewing media types
add_option( 'wp_lib_media_type_slug', 'type' );

// The slug for viewing donors
add_option( 'wp_lib_donors_slug', 'donors' );

// The slug for viewing members
add_option( 'wp_lib_members_slug', 'members' );

// The slug for viewing members
add_option( 'wp_lib_loans_slug', 'loans' );

// The slug for viewing fines
add_option( 'wp_lib_fines_slug', 'fines' );

/* Formatting and Customisation - Small tweaks to plugin presentation */
// Whether to create default media types if they don't already exist
add_option( 'wp_lib_default_media_types', true );

// The separator for item taxonomies (the comma between authors)
add_option( 'wp_lib_taxonomy_spacer', ', ' );

?>