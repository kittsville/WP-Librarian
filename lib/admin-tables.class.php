<?php
// No direct loading
defined( 'ABSPATH' ) OR die('No');

/*
 * Creates, removes and manages the columns of admin post tables for WP-Librarian's post types
 * @todo Add option to sort appropriate admin post table columns
 */
class WP_LIB_ADMIN_TABLES {
	// Instance of core plugin class WP_LIBRARIAN
	protected $wp_librarian;
	
	/*
	 * Adds WordPress hooks to add custom columns, remove unneeded default columns and add content to the custom columns
	 */
	function __construct( $wp_librarian ) {
		$this->wp_librarian = $wp_librarian;
		
		// Adds custom columns, removes unneeded columns and changes the order of columns
		add_filter( 'manage_wp_lib_items_posts_columns',		array( $this, 'manageItemsTableColumns' ),	10, 1 );
		add_filter( 'manage_wp_lib_members_posts_columns',		array( $this, 'manageMembersTableColumns' ),10, 1 );
		add_filter( 'manage_wp_lib_loans_posts_columns',		array( $this, 'manageLoansTableColumns' ),	10, 1 );
		add_filter( 'manage_wp_lib_fines_posts_columns',		array( $this, 'manageFinesTableColumns' ),	10, 1 );
		add_filter( 'manage_users_columns',						array( $this, 'manageUsersTableColumns' ),	10, 1 );
		
		// Adds content to custom post table columns
		add_action( 'manage_wp_lib_items_posts_custom_column',	array( $this, 'fillItemsTableColumns' ),	10, 2 );
		add_action( 'manage_wp_lib_members_posts_custom_column',array( $this, 'fillMembersTableColumns' ),	10, 2 );
		add_action( 'manage_wp_lib_loans_posts_custom_column',	array( $this, 'fillLoansTableColumns' ),	10, 2 );
		add_action( 'manage_wp_lib_fines_posts_custom_column',	array( $this, 'fillFinesTableColumns' ),	10, 2 );
		add_action( 'manage_users_custom_column',				array( $this, 'fillUsersTableColumns' ),	10, 3 );
		
		// Removes bulk actions actions from loans and fines post tables
		add_filter('bulk_actions-edit-wp_lib_loans',			function(){ return array(); });
		add_filter('bulk_actions-edit-wp_lib_fines',			function(){ return array(); });
	}
	
	/*
	 * Adds custom columns to admin post table for items
	 * @param	array	$columns	Existing WordPress post table columns
	 * @return	array				Modified WordPress post table columns
	 * @see							http://codex.wordpress.org/Plugin_API/Filter_Reference/manage_posts_columns
	 */
	public function manageItemsTableColumns( $columns ) {
		// Adds item status and item condition columns
		$new_columns = array(
			'item_status'		=> 'Loan Status',
			'item_condition'	=> 'Item Condition'
		);
		
		// Adds new columns between existing ones
		$columns = array_slice( $columns, 0, 4, true ) + $new_columns + array_slice( $columns, 4, NULL, true );
		
		return $columns;
	}
	
	/*
	 * Adds custom columns to admin post table for members
	 * @param	array	$columns	Existing WordPress post table columns
	 * @return	array				Modified WordPress post table columns
	 * @see							http://codex.wordpress.org/Plugin_API/Filter_Reference/manage_posts_columns
	 */
	public function manageMembersTableColumns( $columns ) {
		// Initialises new columns
		$new_columns = array(
			'member_name'		=> 'Name',
			'member_loans'		=> 'Items on Loan',
			'member_donated'	=> 'Items Donated',
			'member_fines'		=> 'Owed in Fines'
		);
		
		// Adds new columns between existing ones
		return array_slice( $columns, 0, 1, true ) + $new_columns;
	}
	
	/*
	 * Overwrites all existing columns for the loans admin post table with new columns
	 * @param	array	$columns	Existing WordPress post table columns
	 * @return	array				Modified WordPress post table columns
	 * @see							http://codex.wordpress.org/Plugin_API/Filter_Reference/manage_posts_columns
	 * @todo						Test function without accepting $columns as an argument and setting the filter params to 0
	 */
	public function manageLoansTableColumns( $columns ) {
		return array(
			'loan_loan'			=> 'Loan',
			'loan_item'			=> 'Item',
			'loan_member'		=> 'Member',
			'loan_status'		=> 'Status',
			'loan_start'		=> 'Loaned',
			'loan_end'			=> 'Expected',
			'loan_returned'		=> 'Returned',
		);
	}
	
	/*
	 * Overwrites all existing columns for the fines admin post table with new columns
	 * @param	array	$columns	Existing WordPress post table columns
	 * @return	array				Modified WordPress post table columns
	 * @see							http://codex.wordpress.org/Plugin_API/Filter_Reference/manage_posts_columns
	 * @todo						Test function without accepting $columns as an argument and setting the filter params to 0
	 */
	public function manageFinesTableColumns( $columns ) {
		return array(
			'fine_fine'			=> 'Fine',
			'fine_item'			=> 'Item',
			'fine_member'		=> 'Member',
			'fine_status'		=> 'Status',
			'fine_amount'		=> 'Amount'
		);
	}
	
	/*
	 * Adds users' library role column to admin user table columns
	 * @param	array	$columns	Existing WordPress user table columns
	 * @return	array				Modified WordPress user table columns
	 * @see							http://codex.wordpress.org/Plugin_API/Filter_Reference/manage_users_columns
	 */
	public function manageUsersTableColumns( $columns ) {
		return array_slice( $columns, 0, 5, true ) + array('library-role' => 'Library Role') + array_slice( $columns, 5, NULL, true );
	}
	
	/*
	 * Adds content to custom item admin post table columns
	 * @param	string	$column		Name of an item post table column
	 * @param	int		$item_id	Post ID of current row's item
	 * @see							http://codex.wordpress.org/Plugin_API/Action_Reference/manage_posts_custom_column
	 */
	public function fillItemsTableColumns( $column, $item_id ) {
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
	}
	
	/*
	 * Adds content to custom member admin post table columns
	 * @param	string	$column		Name of an member post table column
	 * @param	int		$member_id	Post ID of current row's member
	 * @see							http://codex.wordpress.org/Plugin_API/Action_Reference/manage_posts_custom_column
	 */
	public function fillMembersTableColumns( $column, $member_id ) {
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
	}
	
	/*
	 * Adds content to custom loan admin post table columns
	 * @param	string	$column		Name of an loan post table column
	 * @param	int		$loan_id	Post ID of current row's loan
	 * @see							http://codex.wordpress.org/Plugin_API/Action_Reference/manage_posts_custom_column
	 */
	public function fillLoansTableColumns( $column, $loan_id ) {
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
				echo wp_lib_prep_date_column( $loan_id, 'wp_lib_return_date' );
			break;
		}
	}
	
	/*
	 * Adds content to custom fines admin post table columns
	 * @param	string	$column		Name of an fines post table column
	 * @param	int		$fine_id	Post ID of current row's fines
	 * @see							http://codex.wordpress.org/Plugin_API/Action_Reference/manage_posts_custom_column
	 */
	public function fillFinesTableColumns( $column, $fine_id ) {
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
	}
	
	/*
	 * Fills custom library role column with name of users' current library role
	 * @param	string	$column		Name of an fines post table column
	 * @param	int		$fine_id	Post ID of current row's fines
	 */
	public function fillUsersTableColumns( $value='', $column, $user_id ) {
		switch( $column ) {
			case 'library-role':
				return wp_lib_fetch_user_permission_status( $user_id );
			break;
		}
	}
}


?>