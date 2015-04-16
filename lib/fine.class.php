<?php
// No direct loading
defined( 'ABSPATH' ) OR die('No');

/**
 * Represents a fine incurred for the late return of an item
 * Loaded Automatically: NO
 */
class WP_LIB_FINE extends WP_LIB_OBJECT {
	/**
	 * Creates new instance of a fine from its post ID
	 * @param	WP_LIBRARIAN	$wp_librarian	Single instance of core plugin class
	 * @param	int				$fine_id		Post ID of a fine
	 */
	public static function create( WP_LIBRARIAN $wp_librarian, $fine_id ) {
		return parent::initObject( $wp_librarian, $fine_id, __class__, 'wp_lib_fines', 'Fine' );
	}
	
	/**
	 * Cancels fine, removing fine amount from member's total debt
	 * @return	bool|WP_LIB_ERROR	Success/failure of fine cancellation
	 */
	public function cancel() {
		// Fetches (unformatted) fine status
		$fine_status = get_post_meta( $this->ID, 'wp_lib_status', true );
		
		// If fine is not active (has been cancelled), call error
		if ( $fine_status !== '1' )
			return wp_lib_error( 313 );

		// Changes fine status to Cancelled
		update_post_meta( $this->ID, 'wp_lib_status', 2 );
		
		return true;
	}
}
?>
