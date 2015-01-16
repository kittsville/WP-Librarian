<?php
/**
 * Plugin Name: WP Librarian
 * Description: Use WP-Librarian to manage a library of books/media. Loan, return and schedule with WP-Librarian.
 * Version: 0.0.1
 * Author: Kit Maywood
 * Text Domain: wp-librarian
 * Author URI: https://github.com/kittsville
 * License: GPL2
 */

/*
	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as 
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

	/* -- Constants -- */

// Defines whether or not 'Debugging Mode' is enabled. A mode which displays additional information during library operation
define( 'WP_LIB_DEBUG_MODE', true );

// Defines whether to ensure a Library object can't be deleted without deleting its connected objects. Turning this off is a great way to break your Library!
define( 'WP_LIB_MAINTAIN_INTEGRITY', true );


	/* -- Current Plugin Version -- */

// Updates WordPress's current listed version of WP-Librarian running
register_activation_hook( __FILE__, function() {
	update_option( 'wp_lib_version',
		array(
			'channel'	=> 'Alpha',
			'version'	=> '0.1',
			'subversion'=> '003',
			'nickname'	=> 'Fox Paw'
		)
	);
});


	/* -- External Files used -- */
	
require_once (dirname( __FILE__ ) . '/wp-librarian-functions.php');
require_once (dirname( __FILE__ ) . '/wp-librarian-helpers.php');
require_once (dirname( __FILE__ ) . '/wp-librarian-ajax.php');


	/* -- Custom Post Types and Taxonomies -- */
	/* Registers custom post types and taxonomies */
	
// Creates custom post types to store library items and loans
add_action( 'init', 'wp_lib_register_post_and_tax' );
function wp_lib_register_post_and_tax() {
	// Fetches slugs
	$slugs = get_option( 'wp_lib_slugs', array('wp-librarian','item','authors','type'));

	/* Registers Items as custom post type */
	/* Items represent physical item in the Library, such as a single copy of a book */
	
	register_post_type( 'wp_lib_items',
		array(
			'labels' => array(
				'name'					=> 'Library',
				'singular_name'			=> 'Library Item',
				'name_admin_bar'		=> 'Add New Item',
				'all_items'				=> 'All Items',
				'add_new'				=> 'New Item',
				'add_new_item'			=> 'New Item',
				'edit'					=> 'Edit',
				'edit_item'				=> 'Edit Item',
				'new_item'				=> 'New Item',
				'view_item'				=> 'View Library Item',
				'search_items'			=> 'Search Library Items',
				'not_found'				=> 'No Items found',
				'not_found_in_trash'	=> 'No Items found in Trash',
			),
			'public'				=> true,
			'menu_position'			=> 15,
			'capability_type'		=> 'wp_lib_items_cap',
			'map_meta_cap'			=> true,
			'supports'				=> array( 'title', 'editor', 'thumbnail'),
			'taxonomies'			=> array( '' ),
			'menu_icon'				=> 'dashicons-book-alt',
			'has_archive'			=> true,
			'rewrite'				=> array('slug' => $slugs[0].'/'.$slugs[1]),
			'register_meta_box_cb'	=> 'wp_lib_setup_item_meta_box'
		)
	);
	
	/* Registers Members as custom post type */
	/* Members represent people who may borrow items from the Library, or donate items to the Library */
	
	register_post_type( 'wp_lib_members',
		array(
			'labels' => array(
				'name'					=> 'Members',
				'singular_name'			=> 'Member',
				'add_new'				=> 'Add New',
				'add_new_item'			=> 'Add New',
				'edit'					=> 'Edit',
				'edit_item'				=> 'Edit Member',
				'new_item'				=> 'New Member',
				'view_item'				=> 'View Member',
				'search_items'			=> 'Search All Members',
				'not_found'				=> 'No Members found',
				'not_found_in_trash'	=> 'No Members found in Trash',
			),
			'public'				=> true,
			'capability_type'		=> 'wp_lib_members_cap',
			'map_meta_cap'			=> true,
			'exclude_from_search'	=> true,
			'publicly_queryable'	=> false,
			'show_in_menu' 			=> 'edit.php?post_type=wp_lib_items',
			'supports'				=> array( 'title' ),
			'register_meta_box_cb'	=> 'wp_lib_setup_member_meta_box'
		)
	);
	
	/* Registers Loans as custom post type */
	/* Loans represent the lending of an Item to a Member */
	
	register_post_type( 'wp_lib_loans',
		array(
			'labels' => array(
				'name'				=> 'Loans',
				'singular_name'		=> 'Loan',
				'add_new'			=> 'Add New Loan',
				'add_new_item'		=> 'Add New Loan',
				'edit'				=> 'Edit',
				'edit_item'			=> 'Edit Loan',
				'new_item'			=> 'New Loan',
				'view_item'			=> 'View Loan',
				'search_items'		=> 'Search Loans',
				'not_found'			=> 'No Loans found',
				'not_found_in_trash'=> 'No Loans found in Trash',
			),
			'public'				=> true,
			'public'				=> true,
			'capability_type'		=> 'wp_lib_loans_cap',
			'capabilities'			=> array(
				'create_posts'		=> false
			),
			'map_meta_cap'			=> true,
			'exclude_from_search'	=> true,
			'publicly_queryable'	=> true,
			'show_in_menu'		 	=> 'edit.php?post_type=wp_lib_items',
			'supports'				=> array( '' ),
		)
	);
	
	/* Registers Fines as custom post type */
	/* Fines represent the monetary cost incurred if an Item is returned late (after the Loan specified) */
	
	register_post_type( 'wp_lib_fines',
		array(
			'labels' => array(
				'name'				=> 'Fines',
				'singular_name'		=> 'Fine',
				'add_new'			=> 'Add New Fine',
				'add_new_item'		=> 'Add New Fine',
				'edit'				=> 'Edit',
				'edit_item'			=> 'Edit Fine',
				'new_item'			=> 'New Fine',
				'view_item'			=> 'View Fine',
				'search_items'		=> 'Search Fines',
				'not_found'			=> 'No Fines found',
				'not_found_in_trash'=> 'No Fines found in Trash',
			),
			'public'				=> true,
			'capability_type'		=> 'wp_lib_fines_cap',
			'capabilities'			=> array(
				'create_posts'		=> false
			),
			'map_meta_cap'			=> true,
			'exclude_from_search'	=> true,
			'publicly_queryable'	=> true,
			'show_in_menu'			=> 'edit.php?post_type=wp_lib_items',
			'supports'				=> array( '' ),
		)
	);
	
	/* Registers Authors as a taxonomy */
	/* Authors are the creators of the item, such as the author of a book */
	
	register_taxonomy( 'wp_lib_author', 'wp_lib_items',
		array(
			'capabilities'		=> array(	
				'manage_terms'	=> 'wp_lib_manage_taxs',
				'edit_terms'	=> 'wp_lib_manage_taxs',
				'delete_terms'	=> 'wp_lib_manage_taxs',
				'assign_terms'	=> 'wp_lib_manage_taxs'
			),
			'hierarchical'			=> false,
			'show_ui'				=> true,
			'show_admin_column'		=> true,
			'update_count_callback'	=> '_update_post_term_count',
			'query_var'				=> true,
			'rewrite'				=> array(
				'slug'			=> $slugs[0].'/'.$slugs[2],
				'with_front'	=> false,
				'hierarchical'	=> true
			),
			'labels'				=> array(
				'name'						=> 'Authors',
				'singular_name'				=> 'Author',
				'search_items'				=> 'Search Authors',
				'popular_items'				=> 'Popular Authors',
				'all_items'					=> 'All Authors',
				'edit_item'					=> 'Edit Author',
				'update_item'				=> 'Update Author',
				'add_new_item'				=> 'Add New Author',
				'new_item_name'				=> 'New Author Name',
				'separate_items_with_commas'=> 'Separate authors with commas',
				'add_or_remove_items'		=> 'Add or remove authors',
				'choose_from_most_used'		=> 'Choose from the most used authors',
				'not_found'					=> 'No authors found.',
				'menu_name'					=> 'Authors'
			)
		)
	);
	
	/* Registers media type as a taxonomy */
	/* An item's media type defines wether it is a book, CD, comic etc. */
	
	register_taxonomy( 'wp_lib_media_type', 'wp_lib_items',
		array(
			'capabilities'		=> array(	
				'manage_terms'	=> 'wp_lib_manage_taxs',
				'edit_terms'	=> 'wp_lib_manage_taxs',
				'delete_terms'	=> 'wp_lib_manage_taxs',
				'assign_terms'	=> 'wp_lib_manage_taxs'
			),
			'hierarchical'		=> true,
			'show_ui'			=> true,
			'show_admin_column'	=> true,
			'query_var'			=> true,
			'rewrite'			=> array(
				'slug'			=> $slugs[0].'/'.$slugs[3],
				'with_front'	=> false,
				'hierarchical'	=> true
			),
			'labels'			=> array(
				'name'				=> 'Media Types',
				'singular_name'		=> 'Media Type',
				'search_items'		=> 'Search Media Types',
				'all_items'			=> 'All Media Types',
				'parent_item'		=> 'Parent Media Type',
				'parent_item_colon'	=> 'Parent Media Type:',
				'edit_item'			=> 'Edit Media Type',
				'update_item'		=> 'Update Media Type',
				'add_new_item'		=> 'Add New Media Type',
				'new_item_name'		=> 'New Media Type Name',
				'menu_name'			=> 'Media Types'
			)
		)
	);
	
	// Creates Default Media Type entries if they don't already exist, unless configured otherwise
	if ( wp_lib_prep_boolean_option( get_option( 'wp_lib_default_media_types', array(3) )[0] ) ) {
		$default_media_types = array(
			array(
				'name' => 'Book',
				'slug' => 'books',
			),
			array(
				'name' => 'DVD',
				'slug' => 'dvds',
			),
			array(
				'name' => 'Graphic Novel',
				'slug' => 'graphic-novels',
			),
		);
		// Iterates through default media types and creates them if they do not already exist
		foreach ( $default_media_types as $type ) {
			if ( get_term_by( 'name', $type['name'], 'wp_lib_media_type' ) == false){
				wp_insert_term(
					$type['name'],
					'wp_lib_media_type',
					array( 'slug' => $type['slug'] )
				);
			}
		}
	}
}

	/* -- Rewrite Rules -- */
	/* Modifies permalink rules to allow pretty URLs for Library archives and single items */

add_filter( 'generate_rewrite_rules', function( $wp_rewrite ) {
	// Fetches single/archive slugs
	$slugs = get_option( 'wp_lib_slugs', array('wp-librarian','item','authors','type'));
	$archive = trailingslashit($slugs[0]);
	$single = $archive . trailingslashit($slugs[1]);
	
	$new_rules = array();
	
	$new_rules[$archive.'?$']								= 'index.php?post_type=wp_lib_items';
	$new_rules[$archive.'page/?([0-9]{1,})/?$']				= 'index.php?post_type=wp_lib_items&paged=' . $wp_rewrite->preg_index( 1 );
	$new_rules[$archive.'(feed|rdf|rss|rss2|atom)/?$']		= 'index.php?post_type=wp_lib_items&feed=' . $wp_rewrite->preg_index( 1 );
	$new_rules[$archive.'feed/(feed|rdf|rss|rss2|atom)/?$']	= 'index.php?post_type=wp_lib_items&feed=' . $wp_rewrite->preg_index( 1 );
	
	$wp_rewrite->rules = $new_rules + $wp_rewrite->rules;
	
	return $wp_rewrite;
});

	/* -- Custom Post Table Columns -- */
	/* Adds/removes columns from item/loan/fine post tables then populates said columns */

// Adds custom columns to wp-admin item table and removes unneeded ones
add_filter( 'manage_wp_lib_items_posts_columns', function ( $columns ) {
	// Adds item status and item condition columns
	$new_columns = array(
		'item_status'		=> 'Loan Status',
		'item_condition'	=> 'Item Condition'
	);
	
	// Adds new columns between existing ones
	$columns = array_slice( $columns, 0, 4, true ) + $new_columns + array_slice( $columns, 4, NULL, true );
	
	return $columns;
});

// Adds data to custom columns in item table
add_action( 'manage_wp_lib_items_posts_custom_column' , function ( $column, $item_id ) {
	switch ( $column ) {
		// Displays the current status of the item (On Loan/Available/Late)
		case 'item_status':
			echo wp_lib_prep_item_status( $item_id, false, true );
		break;
		
		// Fetches and formats the condition the item is in, using a placeholder if the condition is unspecified
		case 'item_condition':
			echo wp_lib_format_item_condition( get_post_meta( $item_id, 'wp_lib_item_condition', true ) );
		break;
	}
}, 10, 2 );

// Adds custom columns to wp-admin member table and removes unneeded ones
add_filter( 'manage_wp_lib_members_posts_columns', function ( $columns ) {
	// Initialises new columns
	$new_columns = array(
		'member_name'		=> 'Name',
		'member_loans'		=> 'Items on Loan',
		'member_donated'	=> 'Items Donated',
		'member_fines'		=> 'Owed in Fines'
	);
	
	// Adds new columns between existing ones
	return array_slice( $columns, 0, 1, true ) + $new_columns;
});

// Adds data to custom columns in member table
add_action( 'manage_wp_lib_members_posts_custom_column' , function ( $column, $member_id ) {
	switch ( $column ) {
		// Displays the current status of the item (On Loan/Available/Late)
		case 'member_name':
			echo wp_lib_manage_member_hyperlink( $member_id );
		break;
		
		// Displays number of items currently in the possession of the member
		case 'member_loans':
			echo wp_lib_prep_members_items_out( $member_id );
		break;
		
		// Displays total amount currently owed to the Library in late item fines
		case 'member_fines':
			echo wp_lib_format_money( wp_lib_fetch_member_owed( $member_id ) );
		break;
		
		// Displays total number of items donated by the member to the Library
		case 'member_donated':
			echo (new WP_Query(array(
				'post_type' 		=> 'wp_lib_items',
				'meta_query'		=> array(
					array(
						'key'			=> 'wp_lib_item_donor',
						'value'			=> $member_id,
						'compare'		=> '='
					)
				)
			)))->post_count;
		break;
	}
}, 10, 2 );

// Add custom columns to wp-admin loans table and removes unneeded ones
add_filter( 'manage_wp_lib_loans_posts_columns', function ( $columns ) {
	// As no existing columns are needed, returns new columns, ignoring previous columns
	return array(
		'loan_loan'			=> 'Loan',
		'loan_item'			=> 'Item',
		'loan_member'		=> 'Member',
		'loan_status'		=> 'Status',
		'loan_start'		=> 'Loaned',
		'loan_end'			=> 'Expected',
		'loan_returned'		=> 'Returned',
	);
});

// Removes bulk actions from loans table
add_filter('bulk_actions-edit-wp_lib_loans',function(){ return array(); });

// Adds data to custom columns in loans table
add_action( 'manage_wp_lib_loans_posts_custom_column' , function ( $column, $loan_id ) {
	switch ( $column ) {
		// Displays link to manage loan
		case 'loan_loan':
			echo wp_lib_hyperlink( wp_lib_manage_loan_url( $loan_id ), '#' . $loan_id );
		break;
		
		// Displays title of loaned item with link to view item
		case 'loan_item':
			// Fetches item ID from loan meta
			$item_id = get_post_meta( $loan_id, 'wp_lib_item', true );
			
			// Renders item title formatted as hyperlink to manage item
			echo wp_lib_manage_item_hyperlink( $item_id );
		break;
		
		// Displays member that item has been loaned to
		case 'loan_member':
			// Renders member's name as a link to manage that member
			echo wp_lib_fetch_member_name( $loan_id, true );
		break;
		
		// Displays loan status (Open/Closed)
		case 'loan_status':
			// Fetches status from loan meta
			$status = get_post_meta( $loan_id, 'wp_lib_status', true );
			
			// Formats status
			$status_formatted = wp_lib_format_loan_status( $status );
			
			// If loan status indicates a fine was charged
			if ( $status == 4 ){
				// Fetches fine ID from loan meta
				$fine_id = get_post_meta( $loan_id, 'wp_lib_fine', true );
				
				// Creates and displays hyperlink
				echo wp_lib_hyperlink( wp_lib_manage_fine_url( $fine_id ), $status_formatted );
			}
			else {
				// Otherwise displays status (no hyperlink)
				echo $status_formatted;
			}
		break;
		
		// Displays date item was loaned
		case 'loan_start':
			echo wp_lib_prep_date_column( $loan_id, 'wp_lib_start_date' );
		break;
		
		// Displays date item should be returned by
		case 'loan_end':
			echo wp_lib_prep_date_column( $loan_id, 'wp_lib_end_date' );
		break;
		
		// Displays date item was actually returned
		case 'loan_returned':
			echo wp_lib_prep_date_column( $loan_id, 'wp_lib_returned_date' );
		break;
	}
}, 10, 2 );

// Add custom columns to wp-admin fines table and removes unneeded ones
add_filter( 'manage_wp_lib_fines_posts_columns', function ( $columns ) {
	// As no existing columns are needed, returns new columns, ignoring previous columns
	return array(
		'fine_fine'			=> 'Fine',
		'fine_item'			=> 'Item',
		'fine_member'		=> 'Member',
		'fine_status'		=> 'Status',
		'fine_amount'		=> 'Amount'
	);
});

// Removes bulk actions from fines table
add_filter('bulk_actions-edit-wp_lib_fines',function(){ return array(); });

// Adds data to custom columns in fines table
add_action( 'manage_wp_lib_fines_posts_custom_column', function ( $column, $fine_id ) {
	switch ( $column ) {
		// Displays link to manage fine
		case 'fine_fine':
			echo wp_lib_hyperlink( wp_lib_manage_fine_url( $fine_id ), '#' . $fine_id );
		break;
		
		// Displays title of item fine is for with link to view item
		case 'fine_item':
			// Fetches item ID from loan meta
			$item_id = get_post_meta( $fine_id, 'wp_lib_item', true );
			
			// Renders item title with hyperlink to manage item
			echo wp_lib_manage_item_hyperlink( $item_id );
		break;
		
		// Displays member that has been fined
		case 'fine_member':
			// Renders member's name with link to manage said member
			echo wp_lib_fetch_member_name( $fine_id, true );
		break;
		
		// Displays status of fine (whether it is unpaid/paid/cancelled)
		case 'fine_status':
			// Fetches fine status then formats as readable string e.g. 1 => 'Unpaid'
			$status = wp_lib_format_fine_status( get_post_meta( $fine_id, 'wp_lib_status', true ) );
			
			// Renders fine status 
			echo wp_lib_hyperlink( wp_lib_manage_fine_url( $fine_id ), $status );
		break;
		
		// Displays total charge to member
		case 'fine_amount':
			// Fetches fine amount from fine's post meta
			$fine = get_post_meta( $fine_id, 'wp_lib_fine', true );
			
			// Formats fine with local currency and displays it
			echo wp_lib_format_money( $fine );
		break;
	}
}, 10, 2 );

// Adds custom columns to user table
add_filter( 'manage_users_columns', function( $columns ) {
	// Adds user's WP-Librarian permissions to user table
	$new_columns = array(
		'library-role'	=> 'Library Role'
	);
	
	// Fits new columns between existing columns
	$columns = array_slice( $columns, 0, 5, true ) + $new_columns + array_slice( $columns, 5, NULL, true );
	
	return $columns;
});

// Fills custom column 'Library Role' with user's Library role (or lack thereof)
add_action( 'manage_users_custom_column', function ( $value='', $column, $user_id ) {
	switch( $column ) {
		case 'library-role':
			return wp_lib_fetch_user_permission_status( $user_id );
		break;
	}
}, 10, 3 );

	/* -- Post Permissions -- */
	/* Controls if user if allowed to edit/view custom post types created by WP-Librarian */

// Sets plugin activating user as a Library Admin
register_activation_hook( __FILE__, function() {
	wp_lib_update_user_meta( get_current_user_id(), 10 );
});

// Removes "Additional Capabilities" section from user profile page
add_filter('additional_capabilities_display', function() {
	return false;
});

	/* -- Post/Tax Messages -- */
	/* Changes 'Post Updated' and 'Item Updated' messages to more post type/taxonomy relevant messages for items/members/authors/etc. */

add_filter( 'post_updated_messages', function( $messages ) {
	// Fetches post and post type
	$post             = get_post();
	$post_type        = get_post_type( $post );
	
	// Adds messages based on current post's post type
	switch( $post_type ) {
		case 'wp_lib_items':
			// Creates hyperlink to view or preview item
			$permalink = get_permalink( $post->ID );
			$view_link = ' <a href="' . esc_url( $permalink ) . '">' . 'View Item' . '</a>';
			$preview_link = ' <a target="_blank" href="' . esc_url( add_query_arg( 'preview', 'true', $permalink ) ) . '">' . 'Preview Item' . '</a>';

			$messages['wp_lib_items'] = array(
				1  => 'Item Updated.' . $view_link,
				6  => 'Item Published.' . $view_link,
				7  => 'Item Saved.',
				8  => 'Item Submitted.' . $preview_link,
				9  => 'Item\'s publishing scheduled for: <strong>' . date_i18n( 'M j, Y @ G:i', strtotime( $post->post_date ) ) . '</strong>'. $view_link,
				10 => 'Item draft updated.' . $preview_link
			);
		break;
		
		case 'wp_lib_members':
			// Creates hyperlink to manage Member
			$manage_member_link = ' <a href="' . wp_lib_manage_member_url( $post->ID ) . '">' . 'Manage Member' . '</a>';
			
			$messages['wp_lib_members'] = array(
				1  => 'Member\'s details updated.' . $manage_member_link,
				6  => 'Member published.' . $manage_member_link,
				7  => 'Member\'s details saved.',
				8  => 'Member Submitted.',
				9  => 'Member will be published at: <strong>' . date_i18n( 'M j, Y @ G:i', strtotime( $post->post_date ) ) . '</strong>',
				10 => 'Member details draft updated.'
			);
		break;
	}

	return $messages;
});

// Modifies tax updated/deleted messages for Library's own taxonomies
add_filter( 'term_updated_messages', function( $messages ) {
	// Customises messages based on current taxonomy
	switch( $_GET['taxonomy'] ) {
		case 'wp_lib_author':
			$messages['_item'] = array(
				1 => 'Author Created',
				2 => 'Author Deleted',
				3 => 'Author Updated',
				4 => 'Author not deleted',
				5 => 'Author not deleted',
				6 => 'Authors deleted'
			);
		break;
		
		case 'wp_lib_media_type':
			$messages['_item'] = array(
				1 => 'Media Type Created',
				2 => 'Media Type Deleted',
				3 => 'Media Type Updated',
				4 => 'Media Type not deleted',
				5 => 'Media Type not deleted',
				6 => 'Media Types deleted'
			);
		break;
	}
	return $messages;
});

	/* -- Meta boxes -- */
	/* Adds and populates new meta boxes and removes unneeded ones from post type edit pages */

// Creates meta box below Library Item description on edit/create item pages
function wp_lib_setup_item_meta_box() {
	add_meta_box(
		'library_items_meta_box',
		'Item Details',
		function($item){require_once (dirname( __FILE__ ) . '/wp-librarian-item-meta-box.php');},
		'wp_lib_items',
		'normal',
		'high'
	);
}

// Creates meta box for member's details on the edit/create member page
function wp_lib_setup_member_meta_box() {
	add_meta_box(
		'library_members_meta_box',
		'Member Details',
		function($member){require_once (dirname( __FILE__ ) . '/wp-librarian-member-meta-box.php');},
		'wp_lib_members',
		'normal',
		'high'
	);
}

// Updates a post's meta using new values from its meta box
add_action( 'save_post', function ( $post_id, $post ) {
	// Check if the current user has permission to change a Library item's meta
	if ( !wp_lib_is_librarian() )
		return $post_id;
	
	// Loads meta to be updated based on post type
	switch ( $post->post_type ) {
		case 'wp_lib_items':
			// Verifies meta box nonce
			if ( !isset( $_POST['wp_lib_item_meta_nonce'] ) || !wp_verify_nonce( $_POST['wp_lib_item_meta_nonce'], "Updating item {$post_id} meta" ) )
				return $post_id;
			
			// Stores all meta box fields and sanitization methods
			$meta_array = array(
				array(
					'key'		=> 'wp_lib_item_isbn',
					'sanitize'	=> 'wp_lib_sanitize_isbn'
				),
				array(
					'key'		=> 'wp_lib_item_loanable',
					'sanitize'	=> 'wp_lib_sanitize_checkbox'
				),
				array(
					'key'		=> 'wp_lib_item_delist',
					'sanitize'	=> 'wp_lib_sanitize_checkbox'
				),
				array(
					'key'		=> 'wp_lib_display_donor',
					'sanitize'	=> 'wp_lib_sanitize_checkbox'
				),
				array(
					'key'		=> 'wp_lib_item_condition',
					'sanitize'	=> 'wp_lib_sanitize_number'
				),
				array(
					'key'		=> 'wp_lib_item_barcode',
					'sanitize'	=> 'wp_lib_sanitize_number'
				),
				array(
					'key'		=> 'wp_lib_item_cover_type',
					'sanitize'	=> 'wp_lib_sanitize_number'
				),
				array(
					'key'		=> 'wp_lib_media_type',
					'sanitize'	=> 'sanitize_title',
					'tax'		=> true
				),
				array(
					'key'		=> 'wp_lib_item_donor',
					'sanitize'	=> 'wp_lib_sanitize_donor'
				)
			);
		break;
		
		case 'wp_lib_members':
			// Verifies meta box nonce
			if ( !isset( $_POST['wp_lib_member_meta_nonce'] ) || !wp_verify_nonce( $_POST['wp_lib_member_meta_nonce'], "Updating member {$post_id} meta" ) )
				return $post_id;
			
			// Stores all meta box fields and sanitization methods
			$meta_array = array(
				array(
					'key'		=> 'wp_lib_member_phone',
					'sanitize'	=> 'wp_lib_sanitize_phone_number'
				),
				array(
					'key'		=> 'wp_lib_member_mobile',
					'sanitize'	=> 'wp_lib_sanitize_phone_number'
				),
				array(
					'key'		=> 'wp_lib_member_email',
					'sanitize'	=> 'sanitize_email'
				),
				array(
					'key'		=> 'wp_lib_member_archive',
					'sanitize'	=> 'wp_lib_sanitize_checkbox'
				)
			);
		break;
		
		default:
			return;
	}
	
	// Iterates through each meta field, fetching and sanitizing it then saving/updating/deleting as appropriate
	foreach ( $meta_array as $meta ) {
		// Checks if sanitizing function exists
		if ( !is_callable( $meta['sanitize'] ) )
			return $post_id;
	
		// Get the posted data and sanitize it for use as an HTML class
		$new_meta_value = ( isset( $_POST[$meta['key']] ) ? $meta['sanitize']( $_POST[$meta['key']] ) : '' );
		
		// If data is a taxonomy term and not a meta value
		if ( $meta['tax'] ) {
			// If new meta value is not empty
			if ( $new_meta_value ) {
				// Attempts to fetch term from its respective taxonomy
				$term = get_term_by( 'slug', $new_meta_value, $meta['key'] );
				
				// If term exists
				if ( $term )
					// Updates post's term for that taxonomy
					wp_set_object_terms( $post_id, $term->name, $meta['key'] );
			} else {
				// Removes any term(s) attached to post for current taxonomy
				wp_delete_object_term_relationships( $post_id, $meta['key'] );
			}
			// Skips rest of current loop
			continue;
		}
		
		// Get the meta key
		$meta_key = $meta['key'];

		// Get the meta value of the custom field key
		$meta_value = get_post_meta( $post_id, $meta_key, true );

		// If a new meta value was added and there was no previous value, add it
		if ( $new_meta_value && '' == $meta_value )
			add_post_meta( $post_id, $meta_key, $new_meta_value, true );

		// If the new meta value does not match the old value, update it
		elseif ( $new_meta_value && $new_meta_value != $meta_value )
			update_post_meta( $post_id, $meta_key, $new_meta_value );

		// If there is no new meta value but an old value exists, delete it
		elseif ( '' == $new_meta_value && $meta_value )
			delete_post_meta( $post_id, $meta_key, $meta_value );
	}
}, 10, 2 );

// Removes media type from item edit page, as it's in the meta box
add_action( 'admin_menu', function() {
	remove_meta_box( 'wp_lib_media_typediv', 'wp_lib_items', 'side' );
});

add_action( 'personal_options_update', 'wp_lib_update_user_meta' );
add_action( 'edit_user_profile_update', 'wp_lib_update_user_meta' );

function wp_lib_update_user_meta( $user_id, $new_role = false ) {
	// If new role wasn't specified, function has been called from user profile and nonce checking/sanitization is needed
	// Otherwise function has been called from plugin activation hook and new role will be passed directly to the function
	if ( !$new_role ) {
		// If user is not allowed to edit user meta or nonce fails, stops
		if ( !current_user_can( 'edit_users' ) || !wp_verify_nonce( $_POST['wp_lib_profile_nonce'], 'Editing User: ' . $user_id ) )
			return;
		
		// Fetches new role
		$new_role = $_POST['wp_lib_role'];
		
		// If role wasn't specified or is invalid, stops
		if ( !$new_role || !ctype_digit( $new_role ) )
			return;
	}
	
	// Checks if given role exists
	if ( !array_key_exists( $new_role, wp_lib_fetch_user_roles() ) )
		return;
	
	// Updates user's meta with new role
	update_user_meta( $user_id, 'wp_lib_role', $new_role );
	
	// Updates user's capabilities based on their new role
	wp_lib_update_user_capabilities( $user_id, $new_role );
}

	/* -- Custom Post Type Customisation -- */
	/* Modifying the edit pages fro custom post types to better suit their needs */

// Changes "Post title" greyed out text of title field on Member/Item edit pages with custom text
add_filter( 'enter_title_here', function() {
	switch ( get_current_screen()->post_type ) {
		case 'wp_lib_members':
			return 'Member Name';
		break;
		
		case 'wp_lib_items':
			return 'Item Title';
		break;
	}
});

	/* -- Admin Menus -- */
	/* Registers sub-menu items to wp-admin menu */

// Registers Settings page and Management Dashboard
add_action( 'admin_menu', function() {
	if ( wp_lib_is_library_admin() ) {
		// Adds settings page to Library submenu of wp-admin menu
		$hook = add_submenu_page('edit.php?post_type=wp_lib_items', 'WP Librarian Settings', 'Settings', 'wp_lib_change_settings', 'wp-lib-settings', 'wp_lib_render_settings');
		
		// Registers hook to flush permalinks on successful settings update
		add_action('load-' . $hook, function() {
			// If settings have been updated (or failed to do so)
			if ( isset( $_GET['settings-updated'] ) ) {
				// Loads helper to manage settings sections
				wp_lib_load_helper( 'settings' );
				
				// Checks that all plugin settings are valid, resets any settings that aren't
				WP_LIB_SETTINGS::checkPluginSettingsIntegrity();
			}
		});
	}

	// Registers Library Dashboard and saves handle to variable
	if ( wp_lib_is_librarian() ) {
		add_submenu_page('edit.php?post_type=wp_lib_items', 'Library Dashboard', 'Dashboard', 'edit_wp_lib_items_cap', 'dashboard', function(){require_once( dirname( __FILE__ ) . '/wp-librarian-dashboard-template.php' );});
	}
});

	/* -- Settings API -- */
	/* Registers WP-Librarian settings fields so WordPress handles their rendering and saving */

// Sets up plugin settings
add_action( 'admin_init', function () {
	// Loads helper to manage settings sections
	wp_lib_load_helper( 'settings' );
	
	/* -- General Library Settings -- */
	
	// Registers general settings section, settings and fields with sanitization callbacks
	new WP_LIB_SETTINGS_SECTION(array(
		'name'		=> 'wp_lib_library_group',
		'title'		=> 'General Settings',
		'page'		=> 'wp_lib_library_group-options',
		'settings'	=> array(
			array(
				'name'			=> 'wp_lib_loan_length',
				'sanitize'		=>
					function( $raw ) {
						// Ensures loan length is an integer between 1-100 (inclusive)
						return array( min( max( (int) abs( trim( $raw[0] ) ), 1 ), 100 ) );
					},
				'fields'		=> array(
					array(
						'name'			=> 'Default Loan Length',
						'field_type'	=> 'textInput',
						'args'			=> array(
							'alt'		=> 'The default number of days to loan an item'
						)
					)
				)
			),
			array(
				'name'		=> 'wp_lib_renew_limit',
				'sanitize'	=>
					function( $raw ) {
						// Ensures input is a positive integer between 0-10 (inclusive)
						return array( min( (int) abs( trim( $raw[0] ) ), 10 ) );
					},
				'fields'	=> array(
					array(
						'name'		=> 'Renewing Limit',
						'field_type'=> 'textInput',
						'args'		=> array(
							'alt'	=> 'The maximum number of times an item can be renewed. 0 = no limit'
						)
					)
				)
			),
			array(
				'name'		=> 'wp_lib_fine_daily',
				'sanitize'	=>
					function( $raw ) {
						// Ensures fine amount is a positive float with no more than 2 decimal places
						return array(round(max((float)trim($raw[0]),0),2));
					},
				'fields'	=> array(
					array(
						'name'			=> 'Late Fine',
						'field_type'	=> 'textInput',
						'args'			=> array(
							'alt'		=> 'Amount to charge a member, per day, for a late item',
							'filter'	=> function( $input ) { return number_format( $input, 2 ); }
						)
					)
				)
			),
			array(
				'name'		=> 'wp_lib_currency',
				'sanitize'	=>
					function( $raw ) {
						return array(
							htmlentities( substr( iconv('UTF-8', 'ISO-8859-15', trim( $raw[0] )), 0, 4 ), ENT_QUOTES, 'ISO-8859-15' ),
							wp_lib_sanitize_option_checkbox( $raw[1] )
						);
					},
				'fields'	=> array(
					array(
						'name'		=> 'Currency Symbol',
						'field_type'=> 'textInput',
						'args'		=> array(
							'alt'	=> 'Set the symbol to be used before or after money is displayed'
						)
					),
					array(
						'name'		=> 'Currency Position',
						'field_type'=> 'checkboxInput',
						'args'		=> array(
							'alt'	=> 'Check box to display currency symbol after value e.g. 0.40EUR'
						)
					)
				)
			)
		)
	));

	/* -- Slug Settings -- */

	// Registers settings groups and their sanitization callbacks for the slugs used on the front-end of the plugin
	new WP_LIB_SETTINGS_SECTION(array(
		'name'		=> 'wp_lib_slug_group',
		'title'		=> 'Front-end Slugs',
		'callback'	=> function(){
			echo '<p>These form the URLs of the front-end pages of your library</p>';
		},
		'page'		=> 'wp_lib_slug_group-options',
		'settings'	=> array(
			array(
				'name'			=> 'wp_lib_slugs',
				'classes'		=> array('slug-input'),
				'field_type'	=> 'textInput',
				'sanitize'		=>
					function( $raw ) {
						foreach( range( 0, 3 ) as $position ) {
							$output[$position] = sanitize_title(trim($raw[$position]));
							
							// If there were no valid characters left in the slug, cancels saving
							if ( $output[$position] === '' )
								return;
						}
						
						// If author and media type slugs are identical, cancels saving
						if ( $output[2] === $output[3] )
							return;
						
						return $output;
					},
				'html_filter'	=>
					function( $output, $args ) {
						// Initialises url output preview
						$url = '<span>' . site_url() . '</span>/<span name="main-slug-text"></span>/';
						
						// If slug is not the main slug, add to preview
						if ( isset( $args['end'] ) )
							$url .= '<span class="slug-preview"></span>/' . $args['end'] . '/';
						
						// Inserts preview of slug between input and description
						array_splice( $output, 1, 0, '<label class="slug-label" for="'.$args['setting_name'].'['.$args['position'].']'.'">' . $url . '</label>' );
						
						return $output;
					},
				'fields'	=> array(
					array(
						'name'	=> 'Main',
						'args'	=> array(
							'alt'	=> 'This forms the base of all public Library pages'
						)
					),
					array(
						'name'	=> 'Single Item',
						'args'	=> array(
							'alt'	=> 'This indicates the user is viewing a single item',
							'end'	=> 'war-and-peace'
						)
					),
					array(
						'name'	=> 'Authors',
						'args'	=> array(
							'alt'	=> 'This forms the url for browsing items by author',
							'end'	=> 'terry-pratchett'
						)
					),
					array(
						'name'	=> 'Media Type',
						'args'	=> array(
							'alt'	=> 'This forms the url for browsing items by media type',
							'end'	=> 'comic-books'
						)
					)
				)
			)
		)
	));

	/* -- Dashboard Settings -- */
	
	// Registers Dashboard Settings section with all relevant settings/fields
	new WP_LIB_SETTINGS_SECTION(array(
		'name'		=> 'wp_lib_dash_group',
		'title'		=> 'Dashboard',
		'callback'	=>
			function(){
				echo '<p>These settings modify how the ' . wp_lib_hyperlink( wp_lib_format_dash_url(), 'Dashboard' ) . ' behaves</a></p>';
			},
		'page'		=> 'wp_lib_dash_group-options',
		'settings'	=> array(
			array(
				'name'			=> 'wp_lib_barcode_config',
				'sanitize'		=> function($raw){
					// Sanitizes triggering barcode length
					$raw[1] = wp_lib_sanitize_number( $raw[1] );
					
					return array(
						wp_lib_sanitize_option_checkbox( $raw[0] ),
						( ( $raw[1] > 30 ) ? 30 : ( $raw[1] < 1 ) ? 1 : $raw[1] ) // Rounds barcode length to between 1 and 30
					);
				},
				'fields'		=> array(
					array(
						'name'			=> 'Barcode Auto-fetch',
						'field_type'	=> 'checkboxInput',
						'args'			=> array(
							'alt'		=> 'If to automatically lookup an item when the barcode reaches a given length'
						)
					),
					array(
						'name'			=> 'Auto-fetch Length',
						'field_type'	=> 'textInput',
						'args'			=> array(
							'alt'		=> 'Length at which to automatically look up an item\'s barcode'
						)
					),
				)
			)
		)
	));
});

// Renders plugin settings page
function wp_lib_render_settings() {
	require_once( dirname( __FILE__ ) . '/wp-librarian-admin-settings.php' );
}

	/* -- Scripts and Styles -- */
	/* Registers and enqueues JavaScript and CSS files on the relevant pages, including their dependencies */

// Enqueues scripts and styles needed on different wp-admin pages
add_action( 'admin_enqueue_scripts', function( $hook ) {
	// Registers core JavaScript file for WP-Librarian, a collection of various essential functions
	wp_register_script( 'wp_lib_core', wp_lib_script_url( 'admin-core' ), array( 'jquery', 'jquery-ui-datepicker' ), '0.2' );
	
	// Registers meta core script, an extension of wp_lib_core with functions useful specifically to meta boxes
	wp_register_script( 'wp_lib_meta_core', wp_lib_script_url( 'admin-meta-core' ),  array( 'jquery', 'jquery-ui-datepicker', 'wp_lib_core' ), '0.2' );
	
	// Registers meta core style, this adds the base styling of meta boxes to post edit pages
	wp_register_style( 'wp_lib_meta_core_styles', wp_lib_style_url( 'admin-core-meta-box' ), array(), '0.1' );
	
	// Registers admin-core, a file of core CSS rules for WP-Librarian's admin-end
	wp_register_style( 'wp_lib_admin_core_styles', wp_lib_style_url( 'admin-core' ), array(), '0.2' );

	// Sets up array of variables to be passed to JavaScript
	$vars = array(
			'siteUrl' 		=> site_url(),
			'adminUrl'		=> admin_url(),
			'pluginsUrl'	=> plugins_url( '', __FILE__ ),
			'dashUrl'		=> wp_lib_format_dash_url(),
			'siteName'		=> get_bloginfo( 'name' ),
			'getParams'		=> $_GET,
			'debugMode'		=> WP_LIB_DEBUG_MODE
	);
	
	// Sends variables to user
	wp_localize_script( 'wp_lib_core', 'wp_lib_vars', $vars );
	
	if ( $hook == 'post-new.php' || $hook == 'post.php' ) {
		switch ( $GLOBALS['post_type'] ) {
			case 'wp_lib_items':
				wp_register_script( 'hyphenateISBN', wp_lib_script_url( 'hyphenateISBN' ), array(), '0.1' );
				wp_register_style( 'wp_lib_admin_item_meta', wp_lib_style_url( 'admin-item-meta-box' ), array( 'wp_lib_meta_core_styles' ), '0.1' );
				wp_enqueue_script( 'wp_lib_edit_item', wp_lib_script_url( 'admin-edit-item' ), array( 'wp_lib_meta_core', 'hyphenateISBN' ), '0.2' );
			break;
			
			case 'wp_lib_members':
				wp_register_style( 'wp_lib_admin_member_meta', wp_lib_style_url( 'admin-member-meta-box' ), array( 'wp_lib_meta_core_styles' ), '0.1' );
				wp_enqueue_script( 'wp_lib_edit_member', wp_lib_script_url( 'admin-edit-member' ), array( 'wp_lib_meta_core' ), '0.2' );
			break;
		}
	} elseif ( $hook == 'edit.php' && in_array( $GLOBALS['post_type'], array( 'wp_lib_items', 'wp_lib_members', 'wp_lib_loans', 'wp_lib_fines' ), true ) ) {
		wp_enqueue_style( 'wp_lib_admin_post_table_core', wp_lib_style_url( 'admin-post-table-core' ), array(), '0.1' );
	}
	
	switch ( $hook ) {
		// Plugin settings page
		case 'wp_lib_items_page_wp-lib-settings':
			wp_enqueue_script( 'wp_lib_settings', wp_lib_script_url( 'admin-settings' ), array( 'wp_lib_core' ), '0.3' );
			wp_register_style( 'wp_lib_admin_settings', wp_lib_style_url( 'admin-settings' ), array( 'wp_lib_admin_core_styles' ), '0.1' );
		break;
		
		// Library Dashboard
		case 'wp_lib_items_page_dashboard':
			wp_enqueue_script( 'wp_lib_dashboard', wp_lib_script_url( 'admin-dashboard' ), array( 'wp_lib_core' ), '0.3' );
			wp_enqueue_script( 'dynatable', wp_lib_script_url( 'dynatable' ), array(), '0.3.1' );
			wp_enqueue_style( 'wp_lib_dashboard', wp_lib_style_url( 'admin-dashboard' ), array( 'wp_lib_admin_core_styles' ), '0.2' );
			wp_enqueue_style( 'wp_lib_mellon-datepicker', wp_lib_style_url( 'mellon-datepicker' ), array(), '0.1' ); // Styles Datepicker
			wp_enqueue_style( 'jquery-ui', wp_lib_style_url( 'jquery-ui' ), array(), '1.10.1' ); // Core Datepicker Styles
			wp_enqueue_style( 'dynatable', wp_lib_style_url( 'dynatable' ), array( 'jquery-ui' ), '0.3.1' );
		break;
	}
});

// Enqueues scripts and styles needed for WP-Librarian's front-end
add_action( 'wp_enqueue_scripts', function() {
	if ( get_post_type() === 'wp_lib_items' )
		wp_enqueue_style( 'wp_lib_frontend', wp_lib_style_url( 'front-end-core' ), array(), '0.2' );
});

// Modifies title of Featured Image box on item edit page
add_action('admin_head-post-new.php', 'wp_lib_modify_image_box' );
add_action('admin_head-post.php', 'wp_lib_modify_image_box' );
function wp_lib_modify_image_box() {
	if ( $GLOBALS['post_type'] == 'wp_lib_items' ) {
		global $wp_meta_boxes;
		$wp_meta_boxes['wp_lib_items']['side']['low']['postimagediv']['title'] = 'Cover Image';
	}
}

// Flushes permalink rules to avoid 404s, used after plugin activation and any Settings change
function wp_lib_flush_permalinks() {
	// Registers custom post type
	wp_lib_register_post_and_tax();
	
	// Flushes permalinks so new ones work, e.g. mysite.com/wp-librarian/
	flush_rewrite_rules();
}

// Performs necessary checks before an item, loan or fine is deleted
function wp_lib_check_post_pre_trash( $post_id ) {
	// If object doesn't belong to the Library, is an autosave or integrity checking is turned off, pre-deletion checking is skipped
	if ( !in_array( $GLOBALS['post_type'], ['wp_lib_items', 'wp_lib_members', 'wp_lib_loans', 'wp_lib_fines'] ) || wp_is_post_autosave( $post_id ) || !WP_LIB_MAINTAIN_INTEGRITY )
		return;
	
	// If object is being deleted via an AJAX request
	if (defined('DOING_AJAX') && DOING_AJAX && isset($GLOBALS['wp_lib_ajax'])) {
		$ajax = $GLOBALS['wp_lib_ajax'];
		
		// If authorisation array doesn't exist, item can't be in it and can't have been authorised for deletion
		if ( !property_exists( $ajax, 'deletion_authed_objects' ) )
			$ajax->stopAjax( 505 );
		
		// Iterates over all objects authorised for deletion
		foreach( $ajax->deletion_authed_objects as $key => $object ) {
			// If current object's ID in the loop matches the $post_id then the object has been authorised for deletion
			if ( $object[0] === $post_id ) {
				// Removes object from authorisation array
				unset( $ajax->deletion_authed_objects[$key] );
				
				// Allow WordPress to delete object
				return;
			}
		}
		
		// If this point is reached, object was never authorised for deletion
		$ajax->stopAjax( 505 );
	} else {
		// Redirects user to page to confirm object deletion properly (with connected objects being deleted as well)
		wp_redirect( wp_lib_format_dash_url(
			array(
				'dash_page' => 'object-deletion',
				'post_id'	=> $post_id
			)
		));
		die();
	}
}

// Sets up WP-Librarian on plugin activation
register_activation_hook( __FILE__, function() {
	wp_lib_load_helper( 'settings' );
	WP_LIB_SETTINGS::addPluginSettings();

	// Flushes permalink rules so new URLs don't 404
	wp_lib_flush_permalinks();
});

// Removes traces of plugin on deactivation
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

// Checks if item is on loan before it is moved to trash
add_action('before_delete_post', 'wp_lib_check_post_pre_trash');

	/* -- Profile Fields -- */
	/* Adds custom fields to user profile view/edit pages */

add_action( 'show_user_profile', 'wp_lib_add_profile_page_fields' );
add_action( 'edit_user_profile', 'wp_lib_add_profile_page_fields' );

function wp_lib_add_profile_page_fields( $user ) {
	// If current user can't edit users (is admin), disallow from viewing form
	// Note that regardless of being able to view the form, they wouldn't be able to change the user's meta
	if ( !current_user_can( 'edit_users' ) )
		return;
	
	// Sets up user role options array
	foreach( [1,5,10] as $role_value ) {
		$roles[$role_value] = wp_lib_format_user_permission_status( $role_value );
	}
	
	// Fetches user's current roles
	$status = get_user_meta( $user->ID, 'wp_lib_role', true );
	
	// If user has no role, role is 0
	if ( !$status )
		$status = 1;
	
	// Adds nonce to section
	wp_nonce_field( 'Editing User: ' . $user->ID, 'wp_lib_profile_nonce' );
	?>
	<h3>WP-Librarian</h3>
	<table class="form-table">
		<tr>
			<th><label for="wp-lib-role-input">Library Role</label></th>
			<td>
				<select id="wp-lib-role-input" name="wp_lib_role">
					<?php
						foreach( $roles as $numeric_role => $text_role ) {
							if ( $status == $numeric_role )
								$selected = ' selected="selected" ';
							else
								$selected = '';
							echo '<option value="' . $numeric_role . '"' . $selected . '>' . $text_role . '</option>';
						}
					?>
				</select><br/>
				<span class="description" for="wp-lib-role-input">This defines how a user can interact with WP-Librarian.</span>
			</td>
		</tr>
	</table>
	<?php
}

	/* -- Templates -- */
	/* Manages templates used to display post types for the Library */

// Checks for appropriate templates in the current theme and loads the plugin's default templates if that fails
add_filter( 'template_include', function( $template ) {
	if ( get_post_type() === 'wp_lib_items' ) {
		// If page is archive of multiple items
		if ( is_archive() ) {
			// Looks for template in current theme
			$theme_file = locate_template( array ( 'archive-wp_lib_items.php' ) );
			
			// If template is found, uses it
			if ($theme_file != ''){
				return $theme_file;
			}
			// Otherwise uses plugin's default template for archives
			else {
				return dirname( __FILE__ ) . '/templates/archive-wp_lib_items.php';
			}
		}
		// If page is a single item
		elseif ( is_single() ) {
			// Looks for template in current theme
			$theme_file = locate_template( array ( 'single-wp_lib_items.php' ) );
			
			// If template is found, uses it
			if ($theme_file != ''){
				return $theme_file;
			}
			// Otherwise uses plugin's default template for single items
			else {
				return dirname( __FILE__ ) . '/templates/single-wp_lib_items.php';
			}
		}
	}
	// If post is not a Library item
	return $template;
}, 10, 1 );

// Adds query parameters to exclude de-listed items from the Library archive
// Note that items are still public and can be accessed by their direct URL, that's what setting an item as private exists for!
add_action( 'pre_get_posts', function( $query ) {
	if ( $query->is_post_type_archive('wp_lib_items') && $query->is_main_query() ) {
		$query->set( 'meta_query',
			array(
				'relation'		=> 'OR',
				array(
					'key'		=> 'wp_lib_item_delist',
					'value'		=> '1',
					'compare'	=> '!=',
				),
				array(
					'key'		=> 'wp_lib_item_delist',
					'value'		=> 'bug #23268', // Allows WP-Librarian to run on pre-3.9 WP installs (bug was fixed for 3.9, text is arbitrary)
					'compare'	=> 'NOT EXISTS',
				)
			)
		);
	}
}, 10, 1 );
?>