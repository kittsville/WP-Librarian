<?php
$action = $_GET['item_action']; // The action to be performed (e.g. loan an item)
$page = $_GET['item_page']; // The requested page ( e.g. fine management page )
$item_id = $_GET['item_id']; // The item's ID
$member_id = $_GET['member_id']; // The ID of the member loaning/returning the item
$fine_id = $_GET['fine_id']; // The ID of the fine being managed
$date = $_GET['item_time']; // The time the item was returned (if an item was returned a few days ago)
$start_date = $_GET['loan_start_date']; // When an item is to start being loaned
$end_date = $_GET['loan_end_date']; // When a loan of an item should have ended by
$length = $_GET['loan_length']; // The length of the loan

?>
<div class="wrap wp-lib-domain">
<?php

	/* -- GET Variable Validation -- */
	/* Checks if the IDs belong to valid objects of their type */

// If Item ID is given, validate
if ( $item_id )
	wp_lib_check_item_id( $item_id );
	
// If Member ID is given, validate
if ( $member_id )
	wp_lib_check_member_id( $member_id );

// If Fine ID is given, validate
if ( $fine_id )
	wp_lib_check_fine_id( $fine_id );
	
// If time is not given, it is set to false
if ( !is_numeric( $date ) )
	wp_lib_prep_date( $date );
	
	/* -- GET Variable Sanitization -- */
	/* Converts any parameters to necessary formats */

if ( $start_date )
	$start_time = strtotime( $start_date );

if ( $end_date )
	$end_time = strtotime( $end_date );

	/* -- Item actions -- */
	/* Used to decide what, if anything, the user is aiming to do */
	
switch ( $action ) {
	// Once the member and item have been chosen the item can be loaned
	case 'loan':
		wp_lib_loan_item( $item_id, $member_id, $loan_length );
	break;
	
	// To create loans in the future
	case 'schedule':
		wp_lib_render_schedule_loan( $item_id );
		exit();
	break;
	
	// Passes parameters for scheduling loan to relevant function
	case 'schedule-loan':
		wp_lib_schedule_loan( $item_id, $member_id, $start_time, $end_time );
	break;
	
	// When an item is to be returned (member need not be provided as the item/load contains that data)
	case 'return':
		wp_lib_return_item( $item_id, $date );
	break;
	
	// When a late item needs to be resolved
	case 'resolve':
		wp_lib_render_resolution( $item_id, $date );
		exit();
	break;
	
	// If member is to be fined for late item
	case 'fine':
		wp_lib_create_fine( $item_id, $date );
	break;
	
	// If an item is late but will be returned with no fine
	case 'no-fine':
		wp_lib_return_item( $item_id, $time, true );
	break;
	
	// When an item is to be managed (Loaned/Returned/Marked as lost)
	case 'manage-item':
		wp_lib_manage_item( $item_id );
		exit();
	break;
	
	// When a member is to be managed
	case 'manage-member':
		wp_lib_manage_member( $member_id );
		exit();
	break;
	
	// When a fine is to be managed
	case 'manage-fine':
		wp_lib_manage_fine( $fine_id );
		exit();
	break;
	
	// When a user thinks they can manage fines
	case 'manage-loan':
		wp_lib_error( 202 );
	break;
	
	// When user wants to mark a fine as paid
	case 'resolve-fine':
		wp_lib_charge_fine( $fine_id );
	break;
	
	// When a user wants to revert a fine's status from Paid to Unpaid
	case 'revert-fine':
		wp_lib_revert_fine( $fine_id );
	break;
	
	// When a user wants to cancel a fine
	case 'cancel-fine':
		wp_lib_cancel_fine( $fine_id );
	break;
	
	// Debugging option that allows an item to be cleaned of a current loan
	case 'clean-item':
		wp_lib_clean_item( $item_id );
		$title = get_the_title( $item_id );
		echo "Item {$title} has been cleaned of any current associated loan";
		exit();
	break;
	
	// If an action has been specified but it is not one of the proper actions listed above, error is thrown
	case !'':
		wp_lib_error( 200 );
}
	
	/* -- Rendering the Dashboard -- */
	/*	Now that all requested item actions have been completed, it is time to render the Library Dashboard */

// All notifications/errors in the buffers are rendered and cleared and the URL is cleaned
wp_lib_pre_dashboard();

// Renders the Dashboard
wp_lib_dashboard();

	/* -- Misc Dashboard Functions -- */
	/* Functions that render pages of the Library Dashboard */

function wp_lib_manage_item( $item_id ) {
	// Fetches loan ID and member if item is on loan
	if ( wp_lib_on_loan( $item_id ) ) {
		$loan_id = wp_lib_fetch_loan( $item_id );
		$member = wp_get_post_terms( $item_id, 'wp_lib_member' )[0];
		$late = wp_lib_item_late( $loan_id );
	}
	else
		$loan_id = false;
		$loanable = wp_lib_loanable( $item_id );
	
	// Fetches title
	$title = get_the_title( $item_id );
	
	// If item is late, display error bar
	if ( $late )
		wp_lib_render_error( "{$title} is late, please resolve this issue" );
	
	// Displays the management header
	wp_lib_render_item_management_header( $item_id );
	?>
	<form action="edit.php" method="get">
		<input type="hidden" name="post_type" value="wp_lib_items" />
		<input type="hidden" name="page" value="dashboard" />
		<input type="hidden" name="item_id" value="<?= $item_id; ?>" />
		<?php
		// If item is current on loan
		if ( $loan_id ) {
			// If item is also late
			if ( $late ){
				?>
				<button class="button button-primary button-large" name="item_action" value="resolve">Resolve</button>
				<?php
			// If item is not late
			} else {
				?>
				<button class="button button-primary button-large" name="item_action" value="return">Return</button>
				<?php
			}
			// Regardless of if item is late, user is allowed to return item at a previous date
			?>
			<button class="button button-primary button-large" name="item_action" value="return-past">Return at a Past Date</button>
			<?php
		}
		// If item is not in loan and is allowed to be loaned
		elseif ( $loanable ) {
			$members = get_terms( 'wp_lib_member', 'hide_empty=0' );
			?>
			<h4>Loan item:</h4>
			<select name='member_id' id='member_id'>
				<option class='member-option' value=''>None</option>
				<?php
				foreach ($members as $member) {
					echo "<option class=\"member-option\" value=\"{$member->term_id}\">{$member->name}</option>";
				}
				?>
			</select>
			<select name='loan_length' id='loan_length'>
				<option class='loan-length-option' value=''>Default</option>
				<?php
				// Temporary code to render days to loan for option
				$inc = -3;
				while ( $inc < 12 ) {
					++$inc;
					echo "<option class=\"loan-length-option\" value=\"{$inc}\">{$inc} Days</option>";
				}
				?>
			</select>
			<button name="item_action" value="loan" class="button button-primary button-large">Loan Item</button>
			<?php
		}
		?>
		<button name="item_action" value="schedule" class="button button-primary button-large">Schedule Future Loan</button>
	</form>
	<?php
}

function wp_lib_render_schedule_loan( $item_id ) {
	// Displays the management header
	wp_lib_render_item_management_header( $item_id );

	// Fetches list of all Members
	$members = get_terms( 'wp_lib_member', 'hide_empty=0' );
	
	// Formats placeholder loan start date (current date)
	$start_date = Date( 'Y-m-d' );
	
	// Adds default loan length to current date
	$time = time() + ( get_option( 'wp_lib_loan_length', 12 ) * 24 * 60 * 60);
	
	// Formats placeholder loan end date (current date + default loan length)
	$end_date = Date( 'Y-m-d', $time );
	?>
	<h4>Loan item:</h4>
	<div class="member-select manage-item">
		<label for="member-select">
			<strong>Member:</strong>
		</label>
		<select name='member_id' class='member-select'>
			<option class='member-option' value=''>None</option>
			<?php
			foreach ($members as $member) {
				echo "<option class=\"member-option\" value=\"{$member->term_id}\">{$member->name}</option>";
			}
		   ?>
		</select>
	</div>
	
	<div class="loan-start manage-item">
		<label for="loan-start">
			<strong>Start Date:</strong>
		</label>
		<input type="date" name="loan_start_date" id="loan-start-date" class="loan-date" value="<?= $start_date ?>" />
	</div>
	
	<div class="loan-end manage-item">
		<label for="loan-end">
			<strong>End Date:</strong>
		</label>
		<input type="date" name="loan_end_date" id="loan-end-date" class="loan-date" value="<?= $end_date ?>" />
	</div>
	
	<script>
		jQuery(document).ready(function(){
			jQuery('#loan-start-date').datepicker({
				dateFormat: 'yy-mm-dd'
			});
		});
	</script>
	<button name="item_action" value="schedule-loan" class="button button-primary button-large">Schedule Loan</button>
	<?php
}

// Shows member's details and loan history
function wp_lib_manage_member( $member_id ) {
	// Renders management header
	wp_lib_render_member_management_header( $member_id );

	echo "<p>Nothing much to see here yet!</p>";
}

// Informs librarian of details of item lateness and provides options to resolve the issue
function wp_lib_render_resolution( $item_id, $date ){
	// Fetches loan ID using item ID
	$loan_id = wp_lib_fetch_loan( $item_id );
	
	// Ensures item is actually late
	if ( !wp_lib_item_late( $loan_id ) )
		wp_lib_error( 406, true );
	
	// Creates formatted string containing item lateness
	$args = array( 'late' => '\d day\p' );
	$days_late = wp_lib_prep_item_due( $item_id, $date, $args );
	
	// Renders 'Managing: $item' header
	wp_lib_render_item_management_header( $item_id );
	
	// Prepares useful variables
	$title = get_the_title( $item_id );
	$daily_fine = get_option( 'wp_lib_fine_daily' );
	$late = -wp_lib_cherry_pie( $loan_id, false );
	$fine = wp_lib_format_money( $daily_fine * $late );
	$daily_fine = wp_lib_format_money( $daily_fine );
	?>
	<p><?= $title ?> is late by <?= $days_late ?>. If charged, a fine of <?= $fine ?> would be incurred (<?= $daily_fine ?> per day x <?= $days_late?>)</p>
	<form action="edit.php" method="get">
		<input type="hidden" name="post_type" value="wp_lib_items" />
		<input type="hidden" name="page" value="dashboard" />
		<input type="hidden" name="item_id" value="<?= $item_id; ?>" />
		<button class="button button-primary button-large" name="item_action" value="fine">Fine</button>
		<button class="button button-primary button-large" name="item_action" value="no-fine">Return Without Fine</button>
	</form>
	<?php
}

// Shows librarian details of fine and allows fine cancellation/returning
function wp_lib_manage_fine( $fine_id ){
	wp_lib_render_fine_management_header( $fine_id );
	
	// Fetches fine status
	$fine_status = get_post_meta( $fine_id, 'wp_lib_status', true );
	?>
	<form action="edit.php" method="get">
		<input type="hidden" name="post_type" value="wp_lib_items" />
		<input type="hidden" name="page" value="dashboard" />
		<input type="hidden" name="fine_id" value="<?= $fine_id; ?>" />
		<?php
		// If fine is unpaid, provide options to Pay or Cancel fine
		if ( $fine_status == 1 ) {
			?>
			<p>Marking a fine as paid assumes the money has been collected from the relevant member.</p>
			<button class="button button-primary button-large" name="item_action" value="resolve-fine">Pay Fine</button>
			<?php
		}
		// If fine is paid, provide options to revert fine to being unpaid
		elseif ( $fine_status == 2 ) {
			?>
			<button class="button button-primary button-large" name="item_action" value="revert-fine">Revert to Unpaid</button>
			<?php
		}
		
		// If fine has not been cancelled, display option to cancel fine
		if ( $fine_status != 3 ) {
			?>
			<button class="button button-primary button-large" name="item_action" value="cancel-fine">Cancel Fine</button>
			<?php
		}
		?>
	</form>
	<?php
}
?>
</div>