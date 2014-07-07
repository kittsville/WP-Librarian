<?php
$args = array( 'late' => '\d day\p' );
$late = wp_lib_prep_item_due( $item_id, $date, $args );
wp_lib_render_management_header( $item_id );
?>
<p><?php echo get_the_title( $item_id ); ?> is late by <?php echo $late; ?>. This issue must be resolved before the item can be returned. Choose an appropriate action from below</p>
<form action="edit.php" method="get">
	<input type="hidden" name="post_type" value="wp_lib_items" />
	<input type="hidden" name="page" value="loans-returns" />
	<input type="hidden" name="item_id" value="<?php echo $item_id; ?>" />
</form>