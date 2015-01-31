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
		return parent::create( $wp_librarian, $fine_id, __class__, 'wp_lib_fines', 'Fine' );
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
		
		// Fetches member from fine meta
		$member = WP_LIB_MEMBER::create( get_post_meta( $this->ID, 'wp_lib_member', true ), 'derp' );
		
		if ( wp_lib_is_error( $member ) )
			return $member;
		
		// Fetches current amount owed by member
		$owed = $member->getOwed();
		
		// Fetches fine total
		$fine_total = get_post_meta( $this->ID, 'wp_lib_fine', true );
		
		// If cancelling fine would leave member with negative money owed, call error
		if ( $owed - $fine_total < 0 ) {
			return wp_lib_error( 207 );
		}
		
		// Removes fine from member's debt
		$owed -= $fine_total;
		
		// Updates member debt
		update_post_meta( $member->ID, 'wp_lib_owed', $owed );

		// Changes fine status to Cancelled
		update_post_meta( $this->ID, 'wp_lib_status', 2 );
		
		return true;
	}
}
?>