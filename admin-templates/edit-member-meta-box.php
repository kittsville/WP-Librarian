<?php
/* 
 * WP-LIBRARIAN MEMBER META BOX
 * This renders the meta box that contains member's details;
 * these vales are saved with the post in wp-librarian.php
 */

// No direct loading
defined( 'ABSPATH' ) OR die('No');

// Loads meta box css
wp_enqueue_style( 'wp_lib_admin_member_meta' );

// Nonce, to verify user authenticity
wp_nonce_field( "Updating member {$member->ID} meta", 'wp_lib_member_meta_nonce' );

// Array of all item meta, consisting of each section, then each section's fields and their properties
$meta_formatting = array(
	array(
		'title'	=> 'Contact Information',
		'fields'=> array(
			array(
				'title'		=> 'Email Address',
				'name'		=> 'wp_lib_member_email',
				'type'		=> 'email'
			),
			array(
				'title'		=> 'Phone Number',
				'name'		=> 'wp_lib_member_phone',
				'type'		=> 'tel'
			),
			array(
				'title'		=> 'Mobile Number',
				'name'		=> 'wp_lib_member_mobile',
				'type'		=> 'tel'
			)
		)
	),
	array(
		'title'	=> 'Settings',
		'fields'=> array(
			array(
				'title'		=> 'Archive member',
				'name'		=> 'wp_lib_member_archive',
				'type'		=> 'checkbox',
				'altText'	=> 'Removes member from active participation in the library'
			)
		)
	)
);

// Fetches meta and strips arrays wrapping each meta field
$meta = wp_lib_prep_admin_meta( $member->ID, $meta_formatting );
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