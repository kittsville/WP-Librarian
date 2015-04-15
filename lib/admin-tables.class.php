<?php
// No direct loading
defined( 'ABSPATH' ) OR die('No');

/**
 * Creates, removes and manages the columns of admin post tables for WP-Librarian's post types
 * @todo Add option to sort appropriate admin post table columns
 */
class WP_LIB_ADMIN_TABLES {
	/**
	 * Single instance of core plugin class
	 * @var WP_LIBRARIAN
	 */
	private $wp_librarian;
	
	/**
	 * Buffer of current table row's post object
	 * @var array Array containing current row's post item and a WP_LIB_ITEM|WP_LIB_MEMBER|WP_LIB_LOAN|WP_LIB_FINE instance
	 */
	protected $row_buffer = array();
	
	
	/**
	 * Adds WordPress hooks to add custom columns, remove unneeded default columns and add content to the custom columns
	 */
	function __construct( WP_LIBRARIAN $wp_librarian ) {
		$this->wp_librarian = $wp_librarian;
		
		// Adds custom columns, removes unneeded columns and changes the order of columns
		add_filter( 'manage_wp_lib_items_posts_columns',			array( $this,			'manageItemsTableColumns' ),		10, 1 );
		add_filter( 'manage_wp_lib_members_posts_columns',			array( $this,			'manageMembersTableColumns' ),		10, 1 );
		add_filter( 'manage_wp_lib_loans_posts_columns',			array( $this,			'manageLoansTableColumns' ),		10, 1 );
		add_filter( 'manage_wp_lib_fines_posts_columns',			array( $this,			'manageFinesTableColumns' ),		10, 1 );
		add_filter( 'manage_users_columns',							array( $this,			'manageUsersTableColumns' ),		10, 1 );
		
		add_action( 'load-edit.php',								array( $wp_librarian,	'loadObjectClasses' ) );
		
		// Adds content to custom post table columns
		add_action( 'manage_wp_lib_items_posts_custom_column',		array( $this,			'fillItemsTableColumns' ),			10, 2 );
		add_action( 'manage_wp_lib_members_posts_custom_column',	array( $this,			'fillMembersTableColumns' ),		10, 2 );
		add_action( 'manage_wp_lib_loans_posts_custom_column',		array( $this,			'fillLoansTableColumns' ),			10, 2 );
		add_action( 'manage_wp_lib_fines_posts_custom_column',		array( $this,			'fillFinesTableColumns' ),			10, 2 );
		add_action( 'manage_users_custom_column',					array( $this,			'fillUsersTableColumns' ),			10, 3 );
		
		// Makes relevant custom columns sortable
		add_filter( 'manage_edit-wp_lib_items_sortable_columns',	array( $this,			'setSortableItemsTableColumns' ),	10, 1 );
		add_filter( 'manage_edit-wp_lib_members_sortable_columns',	array( $this,			'setSortableMembersTableColumns' ),	10, 1 );
		add_filter( 'manage_edit-wp_lib_loans_sortable_columns',	array( $this,			'setSortableLoansTableColumns' ),	10, 1 );
		add_filter( 'manage_edit-wp_lib_fines_sortable_columns',	array( $this,			'setSortableFinesTableColumns' ),	10, 1 );
		
		// Adds custom sorting logic to custom taxonomy columns
		add_filter( 'posts_clauses',								array($this,			'sortCustomTaxColumns'),			10, 2);
		
		// Adds custom sorting logic to columns that rely on meta-based foreign keys
		add_filter( 'posts_clauses',								array($this,			'sortCustomForeignMetaColumns'),	10, 2);
		
		// Adds custom sorting logic to custom post meta columns
		add_action( 'pre_get_posts',								array($this,			'sortCustomMetaColumns'),			10, 1);
		
		// Removes bulk actions actions from loans and fines post tables
		add_filter( 'bulk_actions-edit-wp_lib_loans',				function(){ return array(); });
		add_filter( 'bulk_actions-edit-wp_lib_fines',				function(){ return array(); });
	}
	
	/**
	 * Fetches UNIX timestamp from post meta and, if timestamp exists, formats
	 * @param	int		$item_id		Post ID to get post meta from
	 * @param	string	$meta_key		Post meta key where timestamp is located
	 */
	private function dateColumn( $post_id, $meta_key ) {
		// Fetches date from post meta using given key
		$date = get_post_meta( $post_id, $meta_key, true );
		
		// If date is valid returns formatted date
		if ( is_numeric( $date ) )
			echo wp_lib_format_unix_timestamp( $date );
		// Otherwise return dash to indicate missing/unknown information
		else
			echo '-';
	}
	
	/**
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
	
	/**
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
	
	/**
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
	
	/**
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
	
	/**
	 * Adds users' library role column to admin user table columns
	 * @param	array	$columns	Existing WordPress user table columns
	 * @return	array				Modified WordPress user table columns
	 * @see							http://codex.wordpress.org/Plugin_API/Filter_Reference/manage_users_columns
	 */
	public function manageUsersTableColumns( $columns ) {
		return array_slice( $columns, 0, 5, true ) + array('library-role' => 'Library Role') + array_slice( $columns, 5, NULL, true );
	}
	
	/**
	 * Adds content to custom item admin post table columns
	 * @param	string	$column		Name of an item post table column
	 * @param	int		$item_id	Post ID of current row's item
	 * @see							http://codex.wordpress.org/Plugin_API/Action_Reference/manage_posts_custom_column
	 */
	public function fillItemsTableColumns( $column, $item_id ) {
		if ( $this->row_buffer[0] !== $item_id ) {
			$this->row_buffer = array(
				$item_id,
				WP_LIB_ITEM::create( $this->wp_librarian, $item_id )
			);
		}
		
		switch ( $column ) {
			// Displays the current status of the item (On Loan/Available/Late)
			case 'item_status':
				echo $this->row_buffer[1]->formattedStatus( false, true );
			break;
			
			// Fetches and formats the condition the item is in, using a placeholder if the condition is unspecified
			case 'item_condition':
				echo wp_lib_format_item_condition( get_post_meta( $item_id, 'wp_lib_item_condition', true ) );
			break;
		}
	}
	
	/**
	 * Adds content to custom member admin post table columns
	 * @param	string	$column		Name of an member post table column
	 * @param	int		$member_id	Post ID of current row's member
	 * @see							http://codex.wordpress.org/Plugin_API/Action_Reference/manage_posts_custom_column
	 */
	public function fillMembersTableColumns( $column, $member_id ) {
		if ( $this->row_buffer[0] !== $member_id ) {
			$this->row_buffer = array(
				$member_id,
				WP_LIB_MEMBER::create( $this->wp_librarian, $member_id )
			);
		}
		
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
				echo wp_lib_format_money( (float) $this->row_buffer[1]->getMoneyOwed() );
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
	
	/**
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
				echo wp_lib_manage_member_hyperlink( get_post_meta( $loan_id, 'wp_lib_member', true ) );
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
				$this->dateColumn( $loan_id, 'wp_lib_start_date' );
			break;
			
			// Displays date item should be returned by
			case 'loan_end':
				$this->dateColumn( $loan_id, 'wp_lib_end_date' );
			break;
			
			// Displays date item was actually returned
			case 'loan_returned':
				$this->dateColumn( $loan_id, 'wp_lib_return_date' );
			break;
		}
	}
	
	/**
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
			
			// Displays item that caused fine creation with link to manage item
			case 'fine_item':
				echo wp_lib_manage_item_hyperlink( get_post_meta( $fine_id, 'wp_lib_item', true ) );
			break;
			
			// Displays member that has been fined as link to manage member
			case 'fine_member':
				echo wp_lib_manage_member_hyperlink( get_post_meta( $fine_id, 'wp_lib_member', true ) );
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
				$fine = get_post_meta( $fine_id, 'wp_lib_owed', true );
				
				// Formats fine with local currency and displays it
				echo wp_lib_format_money( $fine );
			break;
		}
	}
	
	/**
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
	
	/**
	 * Allows items table to be sorted by appropriate custom columns
	 * @param	Array	$columns	Array of sortable item table columns
	 * @return	Array				Modified array of sortable table columns
	 */
	public function setSortableItemsTableColumns( Array $columns ) {
		// Makes array of columns sortable
		foreach(['taxonomy-wp_lib_media_type','taxonomy-wp_lib_author','item_status','item_condition'] as $sortable ) {
			$columns[$sortable] = $sortable;
		}
		
		return $columns;
	}
	
	/**
	 * Allows members table to be sorted by appropriate custom columns
	 * @param	Array	$columns	Array of sortable member table columns
	 * @return	Array				Modified array of sortable table columns
	 */
	public function setSortableMembersTableColumns( Array $columns ) {
		// Makes array of columns sortable
		foreach(['member_name','member_loans','member_fines','member_donated'] as $sortable ) {
			$columns[$sortable] = $sortable;
		}
		
		return $columns;
	}
	
	/**
	 * Allows loans table to be sorted by appropriate custom columns
	 * @param	Array	$columns	Array of sortable loan table columns
	 * @return	Array				Modified array of sortable table columns
	 */
	public function setSortableLoansTableColumns( Array $columns ) {
		// Makes array of columns sortable
		foreach(['loan_loan','loan_item','loan_member','loan_status','loan_start','loan_end','loan_returned'] as $sortable ) {
			$columns[$sortable] = $sortable;
		}
		
		return $columns;
	}
	
	/**
	 * Allows fines table to be sorted by appropriate custom columns
	 * @param	Array	$columns	Array of sortable fine table columns
	 * @return	Array				Modified array of sortable table columns
	 */
	public function setSortableFinesTableColumns( Array $columns ) {
		// Makes array of columns sortable
		foreach(['fine_fine','fine_item','fine_member','fine_status','fine_amount'] as $sortable ) {
			$columns[$sortable] = $sortable;
		}
		
		return $columns;
	}
	
	/**
	 * Defines custom logic for sorting the Items table's custom taxonomy columns
	 * Adapted from Mike Schinkel's comment on Scribu's post: 
	 * @link	http://scribu.net/wordpress/sortable-taxonomy-columns.html#direct-joins
	 * @param	Array		$clauses	SQL clauses for fetching posts
	 * @param	WP_Query	$wp_query	A request to WordPress for posts
	 * @return	Array					Modified SQL clauses
	 */
	public function sortCustomTaxColumns( Array $clauses, WP_Query $wp_query ) {
		if ( !isset($wp_query->query['orderby']) )
			return $clauses;
		
		switch( $wp_query->query['orderby'] ) {
			case 'taxonomy-wp_lib_author':
				$taxonomy = 'wp_lib_author';
			break;
			
			case 'taxonomy-wp_lib_media_type':
				$taxonomy = 'wp_lib_media_type';
			break;
			
			default:
				return $clauses;
			break;
		}
		
		global $wpdb;
		
		$clauses['join'] .= <<<SQL
LEFT OUTER JOIN {$wpdb->term_relationships} ON {$wpdb->posts}.ID={$wpdb->term_relationships}.object_id
LEFT OUTER JOIN {$wpdb->term_taxonomy} USING (term_taxonomy_id)
LEFT OUTER JOIN {$wpdb->terms} USING (term_id)
SQL;
		
		$clauses['where'] .= " AND (taxonomy = '".$taxonomy."' OR taxonomy IS NULL)";
		$clauses['groupby'] = "object_id";
		$clauses['orderby']  = "GROUP_CONCAT({$wpdb->terms}.name ORDER BY name ASC) ";
		$clauses['orderby'] .= ( 'ASC' === strtoupper( $wp_query->get('order') ) ) ? 'ASC' : 'DESC';

		return $clauses;
	}
	
	/**
	 * Sorts a custom column by its value, where that value comes a different post type's title
	 * @todo							Give this function a better name
	 * @param	Array		$clauses	SQL clauses for fetching posts
	 * @param	WP_Query	$wp_query	A request to WordPress for posts
	 * @return	Array					Modified SQL clauses
	 */
	public function sortCustomForeignMetaColumns( Array $clauses, WP_Query $wp_query ) {
		if (!isset($wp_query->query['orderby']))
			return $clauses;
		
		switch( $wp_query->query['orderby'] ) {
			// The item title column of the loans table
			case 'loan_item':
				$meta_key	= 'wp_lib_item';
			break;
			
			// The member name column of the loans table
			case 'loan_member':
				$meta_key	= 'wp_lib_member';
			break;
			
			// The item title column of the fines table
			case 'fine_item':
				$meta_key	= 'wp_lib_item';
			break;
			
			// The member name column of the fines table
			case 'fine_member':
				$meta_key	= 'wp_lib_member';
			break;
			
			default:
				return $clauses;
			break;
		}
		
		global $wpdb;
		
		// Posts table -> Foreign Keys in Meta Table -> Posts table posts that match f. key
		$clauses['join'] .= <<<SQL
LEFT OUTER JOIN {$wpdb->postmeta} ON {$wpdb->posts}.ID={$wpdb->postmeta}.post_id AND {$wpdb->postmeta}.meta_key='{$meta_key}'
LEFT OUTER JOIN {$wpdb->posts} AS wp_lib_cpt ON meta_value=wp_lib_cpt.ID
SQL;
		
		$clauses['orderby'] = "wp_lib_cpt.post_title ";
		$clauses['orderby'] .= ( 'ASC' === strtoupper( $wp_query->get('order') ) ) ? 'ASC' : 'DESC';
		
		return $clauses;
	}
	
	/**
	 * Tells WordPress how to sort the Items table's custom post meta columns
	 * @param	Array		$vars		Query variables passed to default main SQL query
	 */
	public function sortCustomMetaColumns( WP_Query $query ) {
		if( !is_admin() )  
			return;
		
		// Adds sorting logic based on column being sorted
		switch( $query->get( 'orderby') ) {
			case 'item_status':
				// Requires special logic
			break;
			
			case 'item_condition':
				$query->set('meta_key',	'wp_lib_item_condition');
				$query->set('orderby',	'meta_value_num');
			break;
			
			case 'member_name':
				$query->set('orderby',	'title');	// Member's names are stored as the title of their post
			break;
			
			case 'member_loans':
				// Requires special logic
			break;
			
			case 'member_fines':
				// Requires special logic
			break;
			
			case 'member_donated':
				// Requires special logic
			break;
			
			case 'loan_loan':
				$query->set('orderby',	'ID');
			break;
			
			case 'loan_status':
				$query->set('meta_key',	'wp_lib_status');
				$query->set('orderby',	'meta_value_num');
			break;
			
			case 'loan_start':
				$query->set('meta_key',	'wp_lib_start_date');
				$query->set('orderby',	'meta_value_num');
				$query->set('meta_type','NUMERIC');
			break;
			
			case 'loan_end':
				$query->set('meta_key',	'wp_lib_end_date');
				$query->set('orderby',	'meta_value_num');
				$query->set('meta_type','NUMERIC');
			break;
			
			case 'loan_returned':
				$query->set('meta_key',	'wp_lib_return_date');
				$query->set('orderby',	'meta_value_num');
				$query->set('meta_type','NUMERIC');
			break;
			
			case 'fine_fine':
				$query->set('orderby',	'ID');
			break;
			
			case 'fine_status':
				$query->set('meta_key',	'wp_lib_status');
				$query->set('orderby',	'meta_value_num');
			break;
			
			case 'fine_amount':
				$query->set('meta_key',	'wp_lib_owed');
				$query->set('orderby',	'meta_value_num');
			break;
		}
	}
}


?>