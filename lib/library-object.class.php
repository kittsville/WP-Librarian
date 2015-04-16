<?php
// No direct loading
defined( 'ABSPATH' ) OR die('No');

/**
 * Contains basic logic relating a library object (item/loan/fine)
 * Each object's child class extends the base class with object specific logic
 * As this is an abstract class, don't look directly at the class. Only extend this class
 * Loaded Automatically: NO
 */
abstract class WP_LIB_OBJECT {
	/**
	 * Single instance of core plugin class
	 * @var WP_LIBRARIAN
	 */
	protected $wp_librarian;
	
	/**
	 * Post ID of object
	 * @var int
	 */
	public $ID;
	
	/**
	 * Adds instance of main plugin class to class properties
	 * @param WP_LIBRARIAN $wp_librarian Instance of core plugin class
	 */
	private function __construct( WP_LIBRARIAN $wp_librarian, $post_id ) {
		$this->wp_librarian = $wp_librarian;
		$this->ID			= $post_id;
	}
	
	protected static function initObject( $wp_librarian, $post_id, $class, $post_type, $object_name ) {
		// If given post ID isn't valid, calls error
		if ( get_post_type( $post_id ) !== $post_type )
			return wp_lib_error( 303, $object_name );
		else
			// Sets up basic class properties: post ID and instance of core plugin class
			return new $class( $wp_librarian, $post_id );
	}
}