<?php
$action = $_GET['item_action']; // The action to be taken on the page (returning/loaning an item)
$item_id = $_GET['item_id']; // The item's ID
$member = $_GET['item_member']; // The ID of the member loaning/returning the item
$time = $_GET['item_time']; // The time the item was returned (if an item was returned a few days ago)
$length = $_GET['item_loan_length']; // The length of the loan

// If an action has been specified data is sanitized
if ( !$action == '' ) {
	// Sanitizes item ID
	wp_lib_check_item_id( $item_id );
	
	// Sets time to false if not specified
	if ( !$time )
		$time = false;
}

// When an item to loan has been chosen but the member to loan to hasn't
if ( $action == 'checkout' )
	wp_lib_render_item( $item_id );

// Once the member and item have been chosen the item can be loaned
elseif ( $action == 'Loan' )
	wp_lib_create_loan( $item_id, $member, $length );

// When an item is to be returned (member need not be provided as the item/load contains that data)
elseif ( $action == 'Return' )
	wp_lib_return_item( $item_id, $time );
	
// When a late item needs to be resolved
elseif ( $action == 'Resolve' )
	wp_lib_render_resolution( $item_id, $date );
	
// If member is to be fined for late item
elseif ( $action == 'Fine Member' )
	wp_lib_create_fine( $item_id, $date );
	
elseif ( $action == 'Return Item (with no fine)' )
	wp_lib_return_item( $item_id, $time, true );
	
elseif ( $action == 'manage' )
	wp_lib_manage_item( $item_id );

// When user visits page via WordPress menu, and has thus not specified a task to do
elseif ( $action == '' )
	wp_lib_loans_dashboard();
	
// When an unknown action has been specified, give error
else
	wp_lib_error( 200, true );
	
function wp_lib_manage_item( $item_id ) {
	// Fetches loan ID and member if item is on loan
	if ( wp_lib_on_loan( $item_id ) ) {
		$loan_id = wp_lib_fetch_loan( $item_id );
		$member = wp_get_post_terms( $item_id, 'wp_lib_member' )[0];
		$late = wp_lib_item_late( $loan_id );
	}
	else
		$loan_id = false;
	
	$title = get_the_title( $item_id );
	wp_lib_render_management_header( $item_id );
	
	if ( $late )
		echo '<strong style="color:red;">Item is late, please resolve issue</strong>';
	?>
	<form action="edit.php" method="get">
		<input type="hidden" name="post_type" value="wp_lib_items" />
		<input type="hidden" name="page" value="loans-returns" />
		<input type="hidden" name="item_id" value="<?php echo $item_id; ?>" />
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
			<select name='item_member' id='item_member'>
				<option class='member-option' value=''>None</option>
				<?php
				foreach ($members as $member) {
					echo "<option class=\"member-option\" value=\"{$member->term_id}\">{$member->name}</option>";
				}
			   ?>
			</select>
			<select name='item_loan_length' id='item_loan_length'>
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
	
function wp_lib_render_loan( $item_id ) {
	// Checks if item is already on loan
	// If item is on loan then an error is thrown as you shouldn't be able to get here!
	if ( !wp_lib_loanable( $item_id ) )
		wp_lib_error( 401, true );
	
	$item_title = get_the_title( $item_id );
	wp_nonce_field( "updating item {$item->ID} meta", 'wp_lib_item_nonce' );
	
	echo "<h1>Loaning: {$item_title}</h1>";
     
    // Get all members and displays them as a drop down menu
	$members = get_terms( 'wp_lib_member', 'hide_empty=0' );
	?>
	<form action="edit.php" method="get">
	<input type="hidden" name="post_type" value="wp_lib_items" />
	<input type="hidden" name="page" value="loans-returns" />
	<input type="hidden" name="item_id" value="<?php $item_id ?>" />
	<input type="hidden" name="item_action" value="loan" />
	<select name='item_member' id='item_member'>
		<option class='member-option' value=''>None</option>
		<?php
		foreach ($members as $member) {
			echo "<option class=\"member-option\" value=\"{$member->term_id}\">{$member->name}</option>";
		}
	   ?>
	</select>
	<select name='item_loan_length' id='item_loan_length'>
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
	<input class="button button-primary button-large" type="submit" value="Loan" />	
	</form>
<?php
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
		<input type="hidden" name="page" value="loans-returns" />
		<input type="hidden" name="item_id" value="<?php echo $item_id; ?>" />
		<input class="button button-primary button-large" type="submit" name="item_action" value="Fine Member" />
		<input class="button button-primary button-large" type="submit" name="item_action" value="Return Item (with no fine)" />
	</form>
	<?php
}


?>