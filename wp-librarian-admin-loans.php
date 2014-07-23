<?php
$action = $_GET['item_action']; // The action to be taken on the page (returning/loaning an item)
$item_id = $_GET['item_id']; // The item's ID
$member_id = $_GET['member_id']; // The ID of the member loaning/returning the item
$fine_id = $_GET['fine_id']; // The ID of the fine being managed
$time = $_GET['item_time']; // The time the item was returned (if an item was returned a few days ago)
$length = $_GET['loan_length']; // The length of the loan

?>
<div class="wrap">
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
	
// If not given, time is set to false
if ( !$time )
	$time = false;

	/* -- Item actions -- */
	/* Used to decide what, if anything, the user is aiming to do */
	
switch ( $action ) {
	// Once the member and item have been chosen the item can be loaned
	case 'Loan':
		wp_lib_create_loan( $item_id, $member_id, $length );
	break;
	
	// When an item is to be returned (member need not be provided as the item/load contains that data)
	case 'Return':
		wp_lib_return_item( $item_id, $time );
	break;
	
	// When a late item needs to be resolved
	case 'Resolve':
		wp_lib_render_resolution( $item_id, $date );
		exit();
	break;
	
	// If member is to be fined for late item
	case 'Fine Member':
		wp_lib_create_fine( $item_id, $date );
	break;
	
	// If an item is late but will be returned with no fine
	case 'Return Item (with no fine)':
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
	case 'Pay Fine':
		wp_lib_charge_fine( $fine_id );
	break;
	
	// When a user wants to revert a fine's status from Paid to Unpaid
	case 'Revert to Unpaid':
		wp_lib_revert_fine( $fine_id );
	break;
	
	// When a user wants to cancel a fine
	case 'Cancel Fine':
		wp_lib_cancel_fine( $fine_id );
	break;
	
	// If an action has been specified but it is not one of the proper actions listed above, error is thrown
	default:
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
	
	// Fetches title
	$title = get_the_title( $item_id );
	
	// If item is late, display error bar
	if ( $late )
		wp_lib_render_error( "{$title} is late, please resolve this issue" );
	
	// Displays the management header
	wp_lib_render_management_header( $item_id );
	?>
	<form action="edit.php" method="get">
		<input type="hidden" name="post_type" value="wp_lib_items" />
		<input type="hidden" name="page" value="dashboard" />
		<input type="hidden" name="item_id" value="<?= $item_id; ?>" />
		<?php
		if ( $late ) {
			?>
			<input type="submit" name="item_action" value="Resolve" class="button button-primary button-large" />
			<input type="submit" name="item_action" value="Return at a Past Date" class="button button-primary button-large" />
			<?php
		}
		elseif ( $loan_id ) {
			?>
			<input type="submit" name="item_action" value="Return" class="button button-primary button-large" />
			<input type="submit" name="item_action" value="Return at a Past Date" class="button button-primary button-large" />
			<?php
		}
		else {
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
			<input type="submit" name="item_action" value="Loan" class="button button-primary button-large" />	
			<?php
		}
		?>
	</form>
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
	wp_lib_render_management_header( $item_id );
	
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
		<input class="button button-primary button-large" type="submit" name="item_action" value="Fine Member" />
		<input class="button button-primary button-large" type="submit" name="item_action" value="Return Item (with no fine)" />
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
			<input class="button button-primary button-large" type="submit" name="item_action" value="Pay Fine" />
			<?php
		}
		// If fine is paid, provide options to revert fine to being unpaid
		elseif ( $fine_status == 2 ) {
			?>
			<input class="button button-primary button-large" type="submit" name="item_action" value="Revert to Unpaid" />
			<?php
		}
		
		// If fine has not been cancelled, display option to cancel fine
		if ( $fine_status != 3 ) {
			?>
			<input class="button button-primary button-large" type="submit" name="item_action" value="Cancel Fine" />
			<?php
		}
		?>
	</form>
	<?php
}
?>
</div>