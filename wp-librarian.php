<?php
/**
 * Plugin Name: WP Librarian
 * Plugin URI: http://nerdverse.co.uk/wp-librarian
 * Description: Use WP-Librarian to manage a library of books/media and track who they're lent to and when they're due back.
 * Version: 0.0.1
 * Author: Kit Maywood
 * Author URI: http://nerdverse.co.uk/wp-librarian
 * License: GPL2
 */

	/* External Files used */
	
require_once (plugin_dir_path(__FILE__) . '/wp-librarian-functions.php');
require_once (plugin_dir_path(__FILE__) . '/wp-librarian-options.php');
require_once (plugin_dir_path(__FILE__) . '/wp-librarian-meta-box.php');


	/* Custom Post Types and Taxonomies */

// Creates custom post types to store library items and loans
function wp_lib_create_post_types() {
	// Creates post type for library items
	$args =	array(
		'labels' => array(
			'name' => 'Library',
			'singular_name' => 'Library Item',
			'add_new' => 'Add New Item',
			'add_new_item' => 'Add New Library Item',
			'edit' => 'Edit',
			'edit_item' => 'Edit Library Item',
			'new_item' => 'New Library Item',
			'view' => 'View',
			'view_item' => 'View Library Item',
			'search_items' => 'Search Library Items',
			'not_found' => 'No Library Items found',
			'not_found_in_trash' => 'No Library Items found in Trash',
			'parent' => 'Parent Library Item',
		),

		'public' => true,
		'menu_position' => 15,
		'supports' => array( 'title', 'editor', 'thumbnail'),
		'taxonomies' => array( '' ),
		'menu_icon' => 'dashicons-book-alt',
		'has_archive' => true,
		'rewrite' => array('slug' => get_option( 'wp_lib_slug', 'wp-librarian' ) ),
	);
	register_post_type( 'wp_lib_items', $args );
	
	// Creates post type to manage loaned items
	$args =	array(
		'labels' => array(
			'name' => 'Loans',
			'singular_name' => 'Loan',
			'add_new' => 'Add New Loan',
			'add_new_item' => 'Add New Loan',
			'edit' => 'Edit',
			'edit_item' => 'Edit Loan',
			'new_item' => 'New Loan',
			'view' => 'View',
			'view_item' => 'View Loan',
			'search_items' => 'Search Loans',
			'not_found' => 'No Loans found',
			'not_found_in_trash' => 'No Loans found in Trash',
			'parent' => 'Parent Loan',
		),

		'public' => true,
		'menu_position' => 15,
		'supports' => array( 'title' ),
		'taxonomies' => array( '' ),
		'menu_icon' => 'dashicons-book-alt',
		'has_archive' => true,
		'rewrite' => array('slug' => get_option( 'wp_lib_loans_slug', 'loans' ) ),
	);
	register_post_type( 'wp_lib_loans', $args );
	
	// Creates post type to manage fines
	$args =	array(
		'labels' => array(
			'name' => 'Fines',
			'singular_name' => 'Fine',
			'add_new' => 'Add New Fine',
			'add_new_item' => 'Add New Fine',
			'edit' => 'Edit',
			'edit_item' => 'Edit Fine',
			'new_item' => 'New Fine',
			'view' => 'View',
			'view_item' => 'View Fine',
			'search_items' => 'Search Fines',
			'not_found' => 'No Fines found',
			'not_found_in_trash' => 'No Fines found in Trash',
			'parent' => 'Parent Fine',
		),

		'public' => true,
		'menu_position' => 15,
		'supports' => array( 'title' ),
		'taxonomies' => array( '' ),
		'menu_icon' => 'dashicons-book-alt',
		'has_archive' => true,
		'rewrite' => array('slug' => get_option( 'wp_lib_fines_slug', 'fines' ) ),
	);
	register_post_type( 'wp_lib_fines', $args );
}

// Creates all Taxonomies used for the custom post type 'wp_lib_items'
function wp_lib_create_taxonomies() {
	
	// Registers Taxonomy: Media Type - The type of item in the library (book, CD, DVD, etc.)
	$labels = array(
		'name'			  => _x( 'Media Types', 'taxonomy general name' ),
		'singular_name'	 => _x( 'Media Type', 'taxonomy singular name' ),
		'search_items'	  => __( 'Search Media Types' ),
		'all_items'		 => __( 'All Media Types' ),
		'parent_item'	   => __( 'Parent Media Type' ),
		'parent_item_colon' => __( 'Parent Media Type:' ),
		'edit_item'		 => __( 'Edit Media Type' ),
		'update_item'	   => __( 'Update Media Type' ),
		'add_new_item'	  => __( 'Add New Media Type' ),
		'new_item_name'	 => __( 'New Media Type Name' ),
		'menu_name'		 => __( 'Media Type' ),
	);

	$args = array(
		'hierarchical'	  => true,
		'labels'			=> $labels,
		'show_ui'		   => true,
		'show_admin_column' => true,
		'query_var'		 => true,
		'rewrite' => array('slug' => apply_filters( 'wp_lib_prefix_url', 'wp_lib_media_type_slug', 'type' ) ),
	);

	register_taxonomy( 'wp_lib_media_type', 'wp_lib_items', $args );
	
	// Creates Default Media Type entries if they don't already exist, unless configured otherwise
	if ( get_option( 'wp_lib_default_media_types', true ) ) {
		$args = array(
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
		foreach ( $args as $item ) {
			if ( get_term_by( 'name', $item['name'], 'wp_lib_media_type' ) == false){
				wp_insert_term(
					$item['name'], // the term 
					'wp_lib_media_type', // the taxonomy
					array(
						'slug' => $item['slug']
					)
				);
			}
		}
	}

	// Registers Taxonomy: Authors - For the people who created the physical library item e.g. H.G. Wells or Terry Pratchett
	$labels = array(
		'name'					   => _x( 'Authors', 'taxonomy general name' ),
		'singular_name'			  => _x( 'Author', 'taxonomy singular name' ),
		'search_items'			   => __( 'Search Authors' ),
		'popular_items'			  => __( 'Popular Authors' ),
		'all_items'				  => __( 'All Authors' ),
		'parent_item'				=> null,
		'parent_item_colon'		  => null,
		'edit_item'				  => __( 'Edit Author' ),
		'update_item'				=> __( 'Update Author' ),
		'add_new_item'			   => __( 'Add New Author' ),
		'new_item_name'			  => __( 'New Author Name' ),
		'separate_items_with_commas' => __( 'Separate authors with commas' ),
		'add_or_remove_items'		=> __( 'Add or remove authors' ),
		'choose_from_most_used'	  => __( 'Choose from the most used authors' ),
		'not_found'				  => __( 'No authors found.' ),
		'menu_name'				  => __( 'Authors' ),
	);
	
	$args = array(
		'hierarchical'		  => false,
		'labels'				=> $labels,
		'show_ui'			   => true,
		'show_admin_column'	 => true,
		'update_count_callback' => '_update_post_term_count',
		'query_var'			 => true,
		'rewrite' => array('slug' => apply_filters( 'wp_lib_prefix_url', 'wp_lib_authors_slug', 'authors' ) ),
	);

	register_taxonomy( 'wp_lib_author', 'wp_lib_items', $args );

	// Registers Taxonomy: Donors - The people who donated the library item to the library
	$labels = array(
		'name'					   => _x( 'Donors', 'taxonomy general name' ),
		'singular_name'			  => _x( 'Donor', 'taxonomy singular name' ),
		'search_items'			   => __( 'Search Donors' ),
		'popular_items'			  => __( 'Popular Donors' ),
		'all_items'				  => __( 'All Donors' ),
		'parent_item'				=> null,
		'parent_item_colon'		  => null,
		'edit_item'				  => __( 'Edit Donor' ),
		'update_item'				=> __( 'Update Donor' ),
		'add_new_item'			   => __( 'Add New Donor' ),
		'new_item_name'			  => __( 'New Donor Name' ),
		'separate_items_with_commas' => __( 'Separate donors with commas' ),
		'add_or_remove_items'		=> __( 'Add or remove donors' ),
		'choose_from_most_used'	  => __( 'Choose from the most used donors' ),
		'not_found'				  => __( 'No donors found.' ),
		'menu_name'				  => __( 'Donors' ),
	);
	
	$args = array(
		'hierarchical'		  => false,
		'labels'				=> $labels,
		'show_ui'			   => true,
		'show_admin_column'	 => true,
		'update_count_callback' => '_update_post_term_count',
		'query_var'			 => true,
		'rewrite' => array('slug' => apply_filters( 'wp_lib_prefix_url', 'wp_lib_donors_slug', 'donors' ) ),
	);

	register_taxonomy( 'wp_lib_donor', 'wp_lib_items', $args );

	// Registers Taxonomy: Members - People who borrow items from the library
	$labels = array(
		'name'					   => _x( 'Members', 'taxonomy general name' ),
		'singular_name'			  => _x( 'Member', 'taxonomy singular name' ),
		'search_items'			   => __( 'Search Donors' ),
		'popular_items'			  => __( 'Popular Donors' ),
		'all_items'				  => __( 'All Donors' ),
		'parent_item'				=> null,
		'parent_item_colon'		  => null,
		'edit_item'				  => __( 'Edit Member' ),
		'update_item'				=> __( 'Update Member' ),
		'add_new_item'			   => __( 'Add New Member' ),
		'new_item_name'			  => __( 'New Member Name' ),
		'separate_items_with_commas' => __( 'Separate members with commas' ),
		'add_or_remove_items'		=> __( 'Add or remove members' ),
		'choose_from_most_used'	  => __( 'Choose from the most used members' ),
		'not_found'				  => __( 'No members found.' ),
		'menu_name'				  => __( 'Members' ),
	);
	
	$args = array(
		'hierarchical'		  => false,
		'labels'				=> $labels,
		'show_ui'			   => true,
		'show_admin_column'	 => true,
		'update_count_callback' => '_update_post_term_count',
		'query_var'			 => true,
		'rewrite' => array('slug' => apply_filters( 'wp_lib_prefix_url', 'wp_lib_members_slug', 'members' ) ),
	);

	register_taxonomy( 'wp_lib_member', 'wp_lib_items', $args );
	
}

	/* Functions */

// Adds custom columns to wp-admin item table and removes unneeded ones
function wp_lib_modify_item_table( $columns ) {
	// Removes 'Members' column
	unset(
		$columns['taxonomy-wp_lib_member']
	);
	
	// Adds item status and item condition columns
	$new_columns = array(
		'item_status'		=> 'Loan Status',
		'item_condition'	=> 'Item Condition',
	);
	$columns = array_slice( $columns, 0, 5, true ) + $new_columns + array_slice( $columns, 5, NULL, true );
	return $columns;
}

// Adds data to custom columns in item table
function wp_lib_fill_item_table( $column, $post_id ) {
	// Displays the current status of the item (On Loan/Available/Late)
	if ( $column == 'item_status' )
		echo wp_lib_prep_item_available( $post_id, true, true );
	// Displays the condition of the item
	elseif ( $column == 'item_condition' )
		echo get_post_meta( $post_id, 'wp_lib_item_condition', true );
}

// Add custom columns to wp-admin loans table and removes unneeded ones
function wp_lib_modify_loans_table( $columns ) {
	// Removes 'Date' column
	unset(
		$columns['date']
	);

	// Adds useful loan meta to loan table
	$new_columns = array(
		'loan_status'		=> 'Status',
		'loan_created'		=> 'Created',
		'loan_expected'		=> 'Expected',
		'loan_returned'		=> 'Returned',
	);
	
	$columns = array_slice( $columns, 0, 2, true ) + $new_columns + array_slice( $columns, 2, NULL, true );
	
	return $columns;
}

// Adds data to custom columns in loans table
function wp_lib_fill_loans_table( $column, $loan_id ) {
	// Displays loan status (Open/Closed)
	if ( $column == 'loan_status' )
		echo get_post_meta( $loan_id, 'wp_lib_status', true );
	elseif ( $column == 'loan_created' )
		echo wp_lib_process_date_column( $loan_id, 'wp_lib_start_date' );
	elseif ( $column == 'loan_expected' )
		echo wp_lib_process_date_column( $loan_id, 'wp_lib_due_date' );
	elseif ( $column == 'loan_returned' )
		echo wp_lib_process_date_column( $loan_id, 'wp_lib_end_date' );
}

// Hooks meta boxes
function wp_lib_setup_meta_box() {
	// Creates meta box below Library Item description
	add_action( 'add_meta_boxes', 'wp_lib_create_meta_box' );
	
	// Saves the meta fields on the save_post hook
	add_action( 'save_post', 'wp_lib_process_meta_box', 10, 2 );
}

// Creates meta box below custom post type
function wp_lib_create_meta_box() {
	add_meta_box(
		'library_meta_box',
		'Library Item Details',
		'wp_lib_draw_meta_box',
		'wp_lib_items',
		'normal',
		'high'
	);
}

// Adds custom fields to meta box
function wp_lib_process_meta_box( $item_id, $item ) {

	/* Verify the nonce before proceeding. */
	if ( !isset( $_POST['wp_lib_item_nonce'] ) || !wp_verify_nonce( $_POST['wp_lib_item_nonce'], "updating item {$item_id} meta" ) )
		return $item_id;

	/* Get the post type object. */
	$post_type = get_post_type_object( $item->post_type );

	/* Check if the current user has permission to edit the post. */
	if ( !current_user_can( $post_type->cap->edit_post, $item_id ) )
		return $item_id;
		
	/* Stores all meta box fields and sanitization methods */
	$meta_array = array(
		array(
			'post'		=> 'wp_lib_item_isbn',
			'sanitize'	=> 'sanitize_html_class',
			'key'		=> 'wp_lib_item_isbn',
		),
		array(
			'post'		=> 'wp_lib_item_loanable',
			'sanitize'	=> 'wp_lib_sanitize_checkbox',
			'key'		=> 'wp_lib_item_loanable',
		),
		array(
			'post'		=> 'wp_lib_item_condition',
			'sanitize'	=> 'sanitize_html_class',
			'key'		=> 'wp_lib_item_condition',
		),
	);
	
	foreach ( $meta_array as $meta ) {
		/* Checks if sanitizing function exists */
		if ( !is_callable( $meta['sanitize'] ) )
			return $item_id;
	
		/* Get the posted data and sanitize it for use as an HTML class. */
		$new_meta_value = ( isset( $_POST[$meta['post']] ) ? $meta['sanitize']( $_POST[$meta['post']] ) : '' );

		/* Get the meta key. */
		$meta_key = $meta['key'];

		/* Get the meta value of the custom field key. */
		$meta_value = get_post_meta( $item_id, $meta_key, true );

		/* If a new meta value was added and there was no previous value, add it. */
		if ( $new_meta_value && '' == $meta_value )
			add_post_meta( $item_id, $meta_key, $new_meta_value, true );

		/* If the new meta value does not match the old value, update it. */
		elseif ( $new_meta_value && $new_meta_value != $meta_value )
			update_post_meta( $item_id, $meta_key, $new_meta_value );

		/* If there is no new meta value but an old value exists, delete it. */
		elseif ( '' == $new_meta_value && $meta_value )
			delete_post_meta( $item_id, $meta_key, $meta_value );
	}
}

function wp_lib_remove_meta_boxes() {
	remove_meta_box( 'wp_lib_seriesdiv', 'wp_lib_items', 'side' );
	remove_meta_box( 'tagsdiv-wp_lib_member', 'wp_lib_items', 'side' );
	remove_meta_box( 'tagsdiv-wp_lib_donor', 'wp_lib_items', 'side' );
}

// Registers settings page
function wp_lib_create_submenu_pages() {
	add_submenu_page('edit.php?post_type=wp_lib_items', 'WP Librarian Settings', 'Settings', 'activate_plugins', 'settings', 'wp_lib_render_settings');
	add_submenu_page('edit.php?post_type=wp_lib_items', 'Library Dashboard', 'Dashboard', 'edit_post', 'loans-returns', 'wp_lib_render_dashboard');
}

// Renders settings page
function wp_lib_render_settings() {
	require_once( plugin_dir_path(__FILE__) . '/wp-librarian-admin-settings.php' );
}

//  Render Loans/Returns Page
function wp_lib_render_dashboard() {
	require_once( plugin_dir_path(__FILE__) . '/wp-librarian-admin-loans.php' );
}

// Adds plugin url to front of string (e.g. authors -> library/authors)
add_filter( 'wp_lib_prefix_url', 'wp_lib_prefix_url', 10, 2 );

// Adds metadata addition section to taxonomy field
add_action('wp_lib_member_edit_form', 'wp_lib_member_edit_form');
add_action('wp_lib_member_add_form', 'wp_lib_member_edit_form');

// Adds row of metadata to taxonomy field
add_action('wp_lib_member_add_form_fields','wp_lib_member_add_form_fields');
add_action( 'wp_lib_member_edit_form_fields', 'wp_lib_member_edit_form_fields', 10, 2 );

// Updates taxonomy metadata after update
add_action( 'edited_wp_lib_member', 'wp_lib_member_save_taxonomy_meta', 10, 2 );  
add_action( 'create_wp_lib_member', 'wp_lib_member_save_taxonomy_meta', 10, 2 );

// Clears taxonomy options on taxonomy term delete
add_action( 'delete_term_taxonomy', 'wp_lib_clear_tax_options', 1, 2 );

// Removes meta box 'series' from item edit page
add_action( 'admin_menu', 'wp_lib_remove_meta_boxes' );

// Modifies table of library items, removing unnecessary columns and adding useful ones
add_filter( 'manage_wp_lib_items_posts_columns', 'wp_lib_modify_item_table');
add_action( 'manage_wp_lib_items_posts_custom_column' , 'wp_lib_fill_item_table', 10, 2 );

// Modifies table of loans, removing unnecessary columns and adding useful ones
add_filter( 'manage_wp_lib_loans_posts_columns', 'wp_lib_modify_loans_table');
add_action( 'manage_wp_lib_loans_posts_custom_column' , 'wp_lib_fill_loans_table', 10, 2 );

// Adds item status to wp-admin item table
add_filter( 'manage_wp_lib_items_posts_columns', 'wp_lib_modify_item_table');
add_action( 'manage_wp_lib_items_posts_custom_column' , 'wp_lib_fill_item_table', 10, 2 );

// Clears Description field on unwanted taxonomies
add_action( 'wp_lib_member_add_form_fields', 'wp_lib_no_tax_description' );
add_action( 'wp_lib_donor_add_form', 'wp_lib_no_tax_description' );
add_action( 'wp_lib_member_edit_form_fields', 'wp_lib_no_tax_edit_description' );
add_action( 'wp_lib_donor_edit_form', 'wp_lib_no_tax_edit_description' );


// Currently does nothing
function wp_lib_member_edit_form() {
?>

<?php 
}

function wp_lib_member_add_form_fields() {
?>
	<tr class="form-field">
		<th valign="top" scope="row">
			<label for="wp_lib_member[phone-num]">Telephone Number</label>
		</th>
		<td>
			<input type="tel" id="wp_lib_member[phone-num]" name="wp_lib_member[phone-num]"/>
		</td>
	</tr>
	<tr class="form-field">
		<th valign="top" scope="row">
			<label for="wp_lib_member[mobile-num]">Mobile Number</label>
		</th>
		<td>
			<input type="tel" id="wp_lib_member[mobile-num]" name="wp_lib_member[mobile-num]"/>
		</td>
	</tr>
<?php 
}

function wp_lib_member_edit_form_fields( $term ) {
	$the_id = $term->term_id;
	$term_meta = get_option( "wp_lib_tax_{$the_id}" );
?>
	<tr class="form-field">
		<th valign="top" scope="row">
			<label for="wp_lib_member[phone-num]">Telephone Number</label>
		</th>
		<td>
			<input type="tel" id="wp_lib_member[phone-num]" name="wp_lib_member[phone-num]" value="<?php echo esc_attr( $term_meta['phone-num'] ) ? esc_attr( $term_meta['phone-num'] ) : ''; ?>"/>
		</td>
	</tr>
	<tr class="form-field">
		<th valign="top" scope="row">
			<label for="wp_lib_member[mobile-num]">Mobile Number</label>
		</th>
		<td>
			<input type="tel" id="wp_lib_member[mobile-num]" name="wp_lib_member[mobile-num]" value="<?php echo esc_attr( $term_meta['mobile-num'] ) ? esc_attr( $term_meta['mobile-num'] ) : ''; ?>"/>
		</td>
	</tr>
<?php 
}

function wp_lib_member_save_taxonomy_meta( $term_id ) {
	if ( isset( $_POST['wp_lib_member'] ) ) {
		$the_id = $term_id;
		$term_meta = get_option( "wp_lib_tax_{$the_id}" );
		$raw_keys = array_keys( $_POST['wp_lib_member'] );
		foreach ( $raw_keys as $key ) {
			if ( isset ( $_POST['wp_lib_member'][$key] ) ) {
				$term_meta[$key] = $_POST['wp_lib_member'][$key];
			}
		}
		// Save the option array.
		update_option( "wp_lib_tax_{$term_id}", $term_meta );
	}
}

/* Custom Post Types and Taxonomies */
add_action( 'init', 'wp_lib_create_post_types' );
add_action( 'init', 'wp_lib_create_taxonomies' );

/* Library Settings Page */
add_action( 'admin_menu', 'wp_lib_create_submenu_pages');

/* Metabox when creating or editing items */
add_action( 'load-post.php', 'wp_lib_setup_meta_box' );
add_action( 'load-post-new.php', 'wp_lib_setup_meta_box' );

/* Registered Functions */
add_filter( 'wp_lib_fetch_meta', 'wp_lib_fetch_meta', 10, 1 );
add_filter( 'wp_lib_format_meta', 'wp_lib_prep_meta', 10, 4 );

/* Registered CSS Files */
wp_register_style( 'wp_lib_template', plugins_url( '/css/templates.css', __FILE__ ), false, '1' );
wp_register_style( 'wp_lib_admin_settings', plugins_url( '/css/admin-settings.css', __FILE__ ), false, '1' );
wp_register_style( 'wp_lib_admin_meta', plugins_url( '/css/admin-meta-box.css', __FILE__ ), false, '1' );


/* Templates */

// Archive Template
add_filter( 'template_include', 'wp_lib_template', 10 );

?>