<?php
// No direct loading
defined( 'ABSPATH' ) OR die('No');

/**
 * Holds information about an error that occurred within WP-Librarian such as the error's description and where it occurred
 * Loaded Automatically: YES
 * @todo Add injective dependencies and reap the relevant benefits
 */
class WP_LIB_ERROR {
	/**
	 * Error code, must be a valid code from $error_codes
	 * @var int
	 */
	public $ID;
	
	/**
	 * Description of error that occurred, fetched from $error_codes
	 * @var string
	 */
	public $description;

	/**
	 * All valid error codes within WP-Librarian and their descriptions
	 * Each block of 100 codes is assigned to a category of error, as such:
	 * 0xx - Reserved, see wp_lib_add_notification()
	 * 1xx - Core functionality failure
	 * 2xx - General loan/return systems error
	 * 3xx - Invalid loan/return parameters
	 * 4xx - Error loaning/returning item or fining user
	 * 5xx - AJAX systems error
	 * 6xx - Debugging/Development Errors
	 * 8xx - JavaScript Errors, stored client-side
	 * 9xx - Error processing error
	 */
	public static $error_codes = array(
		110 => 'DateTime neither positive or negative',
		112 => 'Insufficient permissions',
		114	=> 'Option does not exist',
		115	=> 'Field value not found in option',
		116	=> 'AJAX classes can not be used outside of AJAX requests',
		201 => 'No \p status known for given value',
		204 => 'Multiple items have the same barcode',
		205	=> 'Deletion can not be completed while an item is on loan',
		206	=> 'Member does not owe the Library money',
		207	=> 'Unable to cancel fine as it would result in member owing less than nothing',
		208 => 'An item cannot be renewed unless it is on loan',
		209 => 'Item has been renewed the maximum number of times allowed',
		210 => 'Cannot renew item as it would clash with scheduled loan(s)',
		211 => 'Loan cannot be fulfilled unless it is currently scheduled',
		212 => 'Loan lateness calculator encountered an unexpected error',
		213 => 'Item cannot leave the library as it has been manually marked as unavailable',
		214 => 'Item is not currently on loan',
		301 => '\p ID given is not a number',
		302 => 'No loans found for that item ID',
		303	=> 'No \p with given ID exists',
		307 => 'Given dates result in an impossible or impractical loan',
		310 => 'Given date not valid',
		311 => 'Given loan length invalid (not a valid number)',
		312 => 'Given date(s) failed to validate',
		313 => 'Fine can not be cancelled if it is already cancelled',
		314	=> '\p is required and not given',
		316	=> 'Given member has been archived and cannot be loaned items',
		317 => 'Given ID does not belong to a valid Library object',
		318	=> 'Given barcode invalid',
		319	=> 'No item found with that barcode',
		320	=> 'Fine payment amount is invalid',
		322	=> 'Loan must be scheduled and the start date must have passed to give item to member',
		323 => 'Item renewal date must be after item\'s current due date',
		324 => 'Proposed return date is before item was loaned',
		400 => 'Loan creation failed for unknown reason, sorry :/',
		401 => 'Can\'t loan item between given dates, There may be a conflicting loan',
		403 => 'Loan not found (Loan ID found in item meta but no loan found that ID). The item has now been cleaned of all loan meta to attempt to resolve the issue. Refresh the page.',
		406 => 'Item is/was not late on given date, mate',
		407 => 'Fine creation failed for unknown reasons, sorry :/',
		409 => 'Loan status reports item is not currently on loan',
		410 => 'Item can not be returned on given date because it would be late. Please resolve late item or return item at an earlier date',
		411 => 'A loan was scheduled but an error occurred when giving the item to the user. The item has not been marked as having left the library!',
		500 => 'Action requested does not exist',
		502 => 'Requested Dashboard page not found',
		503	=> 'Nonce failed to verify, try reloading the page',
		504	=> 'Unknown API request',
		505	=> 'Object not authorised for deletion',
		506 => 'Infinite loop detected, request terminated',
		600	=> 'Unable to schedule debugging loan',
		601	=> 'Unable to fulfil successfully scheduled debugging loan',
		901 => 'Error encountered while processing error (error code not a number)',
		902 => 'Error encountered while processing error ID:\p (error does not exist)'
	);

	/**
	 * Creates the error object
	 * @param	int		$error_code	Error code, a number referring to an error already defined within the class
	 * @param	mixed	$param		OPTIONAL Additional details required by certain error codes
	 */
	function __construct( $error_code, $param = null ) {
		// Checks if error code is valid and error exists, if not returns error
		if ( !is_int( $error_code ) )
			$error_code = 901;
	
		// If given error code does not exist, calls 'undefined error code' error
		if ( !array_key_exists( $error_code, WP_LIB_ERROR::$error_codes ) )
			$error_code = 902;
		
		// Sets up object properties
		$this->ID			= $error_code;
		$this->description	= str_replace( '\p', $param, WP_LIB_ERROR::$error_codes[$error_code] );
		
		// If error was not called from an AJAX request, kill thread execution
		if ( !defined('DOING_AJAX') || !DOING_AJAX ) {
			die("<div class='wp-lib-error error'><p><strong style=\"color: red;\">WP-Librarian Error {$error_code}: {$this->description}</strong></p></div>");
		}
	}
}
?>
