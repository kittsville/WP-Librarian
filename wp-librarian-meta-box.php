<?php
/* 
 * WP-LIBRARIAN META BOX
 * This renders the meta box that displays below the item description on the item editing page
 * On submission, these values are saved via 
 */

// Loads meta box css
wp_enqueue_style( 'wp_lib_admin_meta' );

// Fetches item meta
$meta = get_post_meta( $item->ID );

// Nonce, to verify user authenticity
wp_nonce_field( "Updating item {$item->ID} meta", 'wp_lib_item_nonce' );

// If item meta allows item to be loaned, checkbox is checked
$loanable = $meta['wp_lib_item_loanable'][0];
$loanable = ( $loanable == true ? 'checked' : '' );
?>
<table id="wp-lib-left-meta-table">
	<tr>
		<td class="wp-lib-meta-title">Available</td>
		<td class="wp-lib-meta-input"><input type="checkbox" size="50%" name="wp_lib_item_loanable" value="true" <?php echo $loanable; ?> />Check if item is allowed to be loaned</td>
	</tr>
	<tr>
		<td class="wp-lib-meta-title">Condition</td>
		<td class="wp-lib-meta-input"><input type="text" size="50%" name="wp_lib_item_condition" value="<?php echo $meta['wp_lib_item_condition'][0]; ?>" /></td>
	</tr>
	<div id="wp-lib-only-books">
		<tr>
			<div id="wp-lib-meta-type">
				<h4>Book Details</h4>
			</div>
		</tr>
		<tr>
			<td class="wp-lib-meta-title">ISBN</td>
			<td class="wp-lib-meta-input"><input type="text" size="50%" name="wp_lib_item_isbn" value="<?php echo $meta['wp_lib_item_isbn'][0]; ?>" /></td>
		</tr>
		<tr>
			<td class="wp-lib-meta-title">Barcode</td>
			<td class="wp-lib-meta-input"><input type="text" size="50%" name="wp_lib_item_barcode" value="<?php echo $meta['wp_lib_item_barcode'][0]; ?>" /></td>
		</tr>
	</div>
</table>