<?php
/* 
 * WP-LIBRARIAN META BOX
 * This renders the meta box that displays below the item description on the item editing page
 * On submission, these values are saved via 
 */

// Loads meta box css
wp_enqueue_style( 'wp_lib_admin_meta' );

// Nonce, to verify user authenticity
wp_nonce_field( "Updating item {$item->ID} meta", 'wp_lib_item_nonce' );

// Array of all item meta, consisting of each section, then each section's fields and their properties
$meta_formatting = array(
	array(
		'title'	=> 'Basic Details',
		'id'	=> 'basic-meta-details',
		'fields'=> array(
			array(
				'title'		=> 'Available',
				'name'		=> 'wp_lib_item_loanable',
				'type'		=> 'checkbox',
				'alt-text'	=> 'Check if item can be loaned',
				'default'	=> 'checked',
				'value'		=> 'true'
			),
			array(
				'title'		=> 'Hide from listing',
				'name'		=> 'wp_lib_item_delist',
				'type'		=> 'checkbox',
				'alt-text'	=> 'Check to hide from public list of items'
			),
			array(
				'title'		=> 'Condition',
				'name'		=> 'wp_lib_item_condition',
				'type'		=> 'select',
				'options'	=> array(
					array(
						'value' => '4',
						'text'	=> wp_lib_format_item_condition( 4 )
					),
					array(
						'value' => '3',
						'text'	=> wp_lib_format_item_condition( 3 )
					),
					array(
						'value' => '2',
						'text'	=> wp_lib_format_item_condition( 2 )
					),
					array(
						'value' => '1',
						'text'	=> wp_lib_format_item_condition( 1 )
					)
				)
			),
			array(
				'title'		=> 'Barcode',
				'name'		=> 'wp_lib_item_barcode',
				'type'		=> 'text'
			)
		)
	),
	array(
		'title'	=> 'Book Details',
		'id'	=> 'book-meta-details',
		'fields'=> array(
			array(
				'title'		=> 'ISBN',
				'name'		=> 'wp_lib_item_isbn',
				'type'		=> 'text',
			),
			array(
				'title'		=> 'Cover Type',
				'name'		=> 'wp_lib_item_cover_type',
				'type'		=> 'select',
				'options'	=> array(
					array(
						'value'	=> '2',
						'text'	=> 'HardCover'
					),
					array(
						'value'	=> '3',
						'text'	=> 'Softcover'
					)
				),
			),

		)
	),
);

// Fetches all item meta
$all_meta = get_post_meta( $item->ID );

// Iterates through meta formatting and fetches needed meta values for all item meta
foreach ( $meta_formatting as $meta_area ) {
	foreach ( $meta_area['fields'] as $field ) {
		$meta[$field['name']] = $all_meta[ $field['name'] ][0];
	}
}
?>
<div id="meta-dropzone">
	<div id="meta-formatting">
		<?php echo json_encode( $meta_formatting ); ?>
	</div>
	<div id="meta-raw">
		<?php echo json_encode( $meta ); ?>
	</div>
</div>
<div id="item-meta"></div>