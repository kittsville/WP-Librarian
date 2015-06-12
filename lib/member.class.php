<?php
// No direct loading
defined('ABSPATH') OR die('No');

/**
 * Represents a member of the library
 * Members may borrow items from the library or donate new ones
 * Loaded Automatically: NO
 */
class WP_Lib_Member extends WP_Lib_Object {
	/**
	 * Creates new instance of a member from its post ID
	 * @param   WP_Librarian                $wp_librarian   Single instance of core plugin class
	 * @param   int                         $member_id      Post ID of a member
	 * @return  WP_Lib_Member|WP_Lib_Error                  Instance of class or, if error occurred, error class
	 */
	public static function create(WP_Librarian $wp_librarian, $member_id) {
		return parent::initObject($wp_librarian, $member_id, __class__, 'wp_lib_members', 'Member');
	}
	
	/**
	 * Calculates amount currently owed by member in late fines
	 * @return  float|int   Amount currently owed by member without currency symbol
	 */
	public function getMoneyOwed() {
		// Queries WP for all loans to current member where a fine was incurred
		$query = new WP_Query(array(
			'post_type'         => 'wp_lib_loans',
			'post_status'       => 'published',
			'nopaging'          => true,
			'meta_query'        => array(
				'relation'      => 'AND',
				array(
					'key'           => 'wp_lib_member',
					'value'         => $this->ID,
					'compare'       => '='
				),
				array(
					'key'           => 'wp_lib_fine',
					'compare'       => 'EXISTS'
				)
			)
		));
		
		// Initialises amount owed by member in fines
		$owed = 0;
		
		if ($query->have_posts()){
			while ($query->have_posts()) {
				$query->the_post();
				
				// Fetches fine ID from loan post meta
				$fine_id = get_post_meta(get_the_ID(), 'wp_lib_fine', true);
				
				// If fine isn't active (fine was cancelled), skip adding to amount owed
				if (get_post_meta($fine_id, 'wp_lib_status', true) !== '1')
					continue;
				
				// Adds fine amount for current loan to total amount owed
				$owed += get_post_meta($fine_id, 'wp_lib_owed', true);
			}
		}
		
		// Fetches all times member has paid off their late fines
		foreach (get_post_meta($this->ID, 'wp_lib_payments') as $fine_payment) {
			// Subtracts fine payment from amount member owes
			$owed -= $fine_payment[1];
		}
		
		return $owed;
	}
	
	/**
	 * Adds fine payment to member records
	 * Fine payments that result in a negative amount owed are allowed
	 * @param   float|int           $payment    Amount to be paid
	 * @return  bool|WP_Lib_Error               True on success, error on failure
	 */
	public function payMoneyOwed($payment) {
		// If fine payment is negative or failed to validate (resulting in 0), call error
		if ($payment <= 0)
			return wp_lib_error(320);
		
		// Creates record of fine payment
		add_post_meta($this->ID, 'wp_lib_payments', array(
			current_time('timestamp'),  // Date of fine payment
			$payment,                   // Amount paid of member fines
			get_current_user_id()       // Librarian to authorise fine payment
		));
	}
}
