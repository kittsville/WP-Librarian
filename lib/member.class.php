<?php
// No direct loading
defined( 'ABSPATH' ) OR die('No');

/**
 * Represents a member of the library
 * Members may borrow items from the library or donate new ones
 * Loaded Automatically: NO
 */
class WP_LIB_MEMBER extends WP_LIB_OBJECT {
	/**
	 * Creates new instance of a member from its post ID
	 * @param	WP_LIBRARIAN				$wp_librarian	Single instance of core plugin class
	 * @param	int							$member_id		Post ID of a member
	 * @return	WP_LIB_MEMBER|WP_LIB_ERROR					Instance of class or, if error occurred, error class
	 */
	public static function create( WP_LIBRARIAN $wp_librarian, $member_id ) {
		return parent::create( $wp_librarian, $member_id, __class__, 'wp_lib_members', 'Member' );
	}
}
?>