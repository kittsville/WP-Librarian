<?php
function wp_lib_draw_meta_box( $item ) {
	wp_enqueue_style( 'wp_lib_admin_meta' );
	$meta = get_post_meta( $item->ID );
	var_dump( $meta );
	wp_nonce_field( "updating item {$item->ID} meta", 'wp_lib_item_nonce' );
	$loanable = $meta['wp_lib_item_loanable'][0];
	$loanable = ( $loanable == true ? 'checked' : '' );
?>
	<table id="wp-lib-left-meta-table">
		<tr>
			<td class="wp-lib-meta-title">Available</td>
			<td class="wp-lib-meta-input"><input type="checkbox" size="50%" name="wp_lib_item_loanable" value="true" <?php echo $loanable; ?> />Check if item is allowed to be loaned</td>
		</tr>
		<tr>
			
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
	<?php
}
?>