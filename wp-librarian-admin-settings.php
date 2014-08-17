<?php

if ( !current_user_can( 'manage_options' ) ) {
	wp_lib_error( 112, true );
}

wp_enqueue_style( 'wp_lib_admin_settings' );

// Fetches current value for slugs
$main_slug = get_option( 'wp_lib_slug', 'Error' );
$authors_slug = get_option( 'wp_lib_authors_slug', 'Error' );
$type_slug = get_option( 'wp_lib_media_type_slug', 'Error' );
$donors_slug = get_option( 'wp_lib_donors_slug', 'Error' );

// One day get_settings_error will be used to fetch whatever error occurred, one day
$update_success = $_GET['settings-updated'];

if ( $update_success == 'true' ) {
	$notification = wp_lib_format_notification( 'Settings updated' );
	wp_lib_flush_permalinks();
}
elseif ( $update_success == 'false' )
	$notification = wp_lib_format_error( 'Settings failed to update' );


?>
<div id="wp-lib-admin-wrapper" class="wrap">
	<div id="wp-lib-title">
		<h1>WP-Librarian Settings</h1>
	</div>
	<?php
		echo $notification;
	?>

	<div id="wp-lib-main-content">
		<form method="POST" action="options.php">
			<?php
			// Handles nonces and other page security
			settings_fields( 'wp_lib_slug_group' );
			
			// Renders all slug fields 
			do_settings_sections( 'wp_lib_items_page_wp-lib-settings' );
			
			// Submit button to update options
			submit_button( 'Update', 'primary' );
			?>
		</form>
	</div>
</div>