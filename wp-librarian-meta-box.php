<?php
// Loads meta box css
wp_enqueue_style( 'wp_lib_admin_meta' );

// Fetches item meta
$meta = get_post_meta( $item->ID );

// Nonce, to verify user authenticity
wp_nonce_field( "updating item {$item->ID} meta", 'wp_lib_item_nonce' );

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
	</div>
</table>