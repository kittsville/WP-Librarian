<?php
// No direct loading
defined( 'ABSPATH' ) OR die('No');

/**
 * Used to generate a Dynatable from a WordPress query, using columns built into the class
 * A single instance and thus query can be used to generate multiple tables
 * @link http://dynatable.com/
 * @todo Add injective dependencies and reap the relevant benefits
 */
class WP_LIB_DYNATABLE {
	/**
	 * Instance of WP_Query generated by running query in constructor
	 * Data from this query is used to generate the Dynatable
	 * @var WP_Query
	 * @link http://codex.wordpress.org/Class_Reference/WP_Query
	 */
	public $WP_Query;
	
	/**
	 * ID of post from which table is being generated
	 * @var int
	 */
	public $post_id;
	
	/**
	 * ID of post in row being generated
	 * @var int
	 */
	protected $row_post_id;
	
	/**
	 * Meta of current row's post
	 * @var array
	 */
	protected $row_meta;
	
	/**
	 * Generates WP_Query instance
	 * @param int			$post_id	Post ID of item
	 * @param Array|String	$args		WP_Query Arguments
	 */
	function __construct( $args ) {
		// Checks class has been called from a valid context
		if ( !defined('DOING_AJAX') || !DOING_AJAX )
			wp_lib_error( 116 );
		
		// Runs query, generating object containing matching posts
		$this->WP_Query = new WP_Query( $args );
		
		// Sets up post_id property for use when generating tables
		$this->post_id = $post_id;
	}
	
	/**
	 * Generates a Dynatable with the currently set columns
	 * @param	Array	$table_columns	Columns to be created for each row
	 * @param	Array	$properties		OPTIONAL Additional properties to add to Dynatable
	 * @param	String	$no_posts		OPTIONAL Text to display if no posts were found
	 * @return	Array					Dynatable in Library Dashboard format
	 */
	public function generateTable( $table_columns, $properties = array(), $no_posts = 'No posts found' ) {
		if ( $this->WP_Query->have_posts() ) {
			// Fetches table header names
			foreach ( $table_columns as $column )
				$headers[] = $column[0];
			
			// Iterates over posts, generating table rows
			while ( $this->WP_Query->have_posts() ) {
				$this->WP_Query->the_post();
				
				$this->row_post_id = get_the_ID();
				$this->row_meta = get_post_meta( $this->row_post_id );
				
				$row = array();
				
				// Creates data for each row's column
				foreach ( $table_columns as $column ) {
					$row[$column[1]] = $this->$column[2]();
				}
				
				$table_rows[] = $row;
			}
			
			// Merges additional table properties into default properties
			return array_merge(
				$properties,
				array(
					'type'		=> 'dtable',
					'headers'	=> $headers,
					'data'		=> $table_rows
				)
			);
		} else {
			return array(
				'type'		=> 'paras',
				'content'	=> array( $no_posts )
			);
		}
	}
}

/**
 * Generates Dynamic table for the Dashboard to display a list of loans associated with a member or item
 */
class WP_LIB_DYNATABLE_LOANS extends WP_LIB_DYNATABLE {
	function __construct( $key, $id ) {
		parent::__construct(array(
			'post_type' 	=> 'wp_lib_loans',
			'post_status'	=> 'publish',
			'meta_query'	=> array(
				array(
					'key'		=> $key,
					'value'		=> $id,
					'compare'	=> 'IN'
				)
			)
		));
	}
	
	/**
	 * Generates Dashboard hyperlink to manage the Item
	 */
	protected function genColumnManageItem() {
		return wp_lib_manage_item_dash_hyperlink( $this->row_meta['wp_lib_item'][0] );
	}
	
	/**
	 * Generates Dashboard hyperlink to manage the member
	 */
	protected function genColumnManageMember() {
		return wp_lib_manage_member_dash_hyperlink( $this->row_meta['wp_lib_member'][0] );
	}
	
	/**
	 * Generates Dashboard hyperlink to manage the loan
	 */
	protected function genColumnManageLoan() {
		return wp_lib_manage_loan_dash_hyperlink( $this->row_post_id );
	}
	
	/**
	 * Generates readable loan status, with link to manage the loan
	 */
	protected function genColumnLoanStatus() {
		$status = wp_lib_format_loan_status( $this->row_meta['wp_lib_status'][0] );
		
		// If there is a fine attached to the loan, makes status a hyperlink to manage the fine
		if ( $this->row_meta['wp_lib_status'][0] == 4 ) {
			return wp_lib_prep_dash_hyperlink( $status, wp_lib_prep_manage_fine_params( $this->row_meta['wp_lib_fine'][0] ) );
		} else {
			return $status;
		}
	}
	
	/**
	 * Generates formatted date of when item was loaned or, if not yet loaned, the date the loan is scheduled to start
	 */
	protected function genColumnLoanStart() {
		return wp_lib_format_unix_timestamp( ( isset( $this->row_meta['wp_lib_give_date'] ) ? $this->row_meta['wp_lib_give_date'][0] : $this->row_meta['wp_lib_start_date'][0] ) );
	}
	
	/**
	 * Generates formatted date of when item was due to be returned
	 */
	protected function genColumnLoanEnd() {
		return wp_lib_format_unix_timestamp( $this->row_meta['wp_lib_end_date'][0] );
	}
	
	/**
	 * Generates formatted date of when item was actually returned
	 */
	protected function genColumnReturned() {
		return wp_lib_format_unix_timestamp( $this->row_meta['wp_lib_return_date'][0] );
	}
}
?>
