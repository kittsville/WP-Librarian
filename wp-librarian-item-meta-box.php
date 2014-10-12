<?php
/* 
 * WP-LIBRARIAN ITEM META BOX
 * This renders the meta box that displays below the item description on the item editing page.
 * These vales are saved with the post in wp-librarian.php
 */

// Loads meta box css
wp_enqueue_style( 'wp_lib_admin_item_meta' );

// Nonce, to verify user authenticity
wp_nonce_field( "Updating item {$item->ID} meta", 'wp_lib_item_meta_nonce' );

// Fetches list of media types
$media_type_objects = get_terms( 'wp_lib_media_type', 'hide_empty=0' );

// Creates meta formatting array of media types
foreach ( $media_type_objects as $type ) {
	$media_types[] = array(
		'value'	=> $type->slug,
		'text'	=> $type->name
	);
}

// Array of all item meta, consisting of each section, then each section's fields and their properties
$meta_formatting = array(
	array(
		'title'	=> 'Basic Details',
		'class'	=> 'meta-basic-details-section',
		'fields'=> array(
			array(
				'title'		=> 'Media Type',
				'id'		=> 'meta-media-type-selector',
				'name'		=> 'wp_lib_media_type',
				'type'		=> 'select',
				'options'	=> $media_types
			),
			array(
				'title'		=> 'Available',
				'name'		=> 'wp_lib_item_loanable',
				'type'		=> 'checkbox',
				'alt-text'	=> 'Check if item can be loaned',
				'default'	=> 'checked'
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
		'value'	=> 'books',
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
	array(
		'title'	=> 'DVD Details',
		'value'	=> 'dvds',
		'fields'=> array(
			array(
				'title'		=> 'Placeholder',
				'name'		=> 'wp_lib_item_placeholder',
				'type'		=> 'text',
			),
		)
	),
);

// Fetches all post meta then strips away any meta not needed by the meta formatting
$meta = wp_lib_prep_admin_meta( $item->ID, $meta_formatting );

// Adds media type meta to meta array
$meta['wp_lib_media_type'] = wp_get_object_terms($item->ID, 'wp_lib_media_type', array("fields" => "slugs") )[0];
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