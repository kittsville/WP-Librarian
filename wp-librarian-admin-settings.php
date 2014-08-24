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

if ( isset( $_GET['settings-updated'] ) ) {
	?>
	<script>var UpdateSuccess = <?= $_GET['settings-updated']; ?>;</script>
	<?php
	if ( $_GET['settings-updated'] == 'true' )
		wp_lib_flush_permalinks();
}
?>
<div id="wp-lib-admin-wrapper" class="wrap">
	<div id="title-wrap">
		<h2>WP-Librarian Settings</h2>
	</div>
	<!-- Filled with any notifications waiting in a session -->
	<div id="notifications-holder"></div>

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