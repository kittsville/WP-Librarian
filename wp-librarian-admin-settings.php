<?php
/*
 * WP-LIBRARIAN SETTINGS
 * Utilises WordPress Settings API to render settings fields for this plugin
 */

// If user isn't allowed to modify site's settings, settings fields aren't rendered and error is called
if ( !current_user_can( 'manage_options' ) ) {
	wp_lib_error( 112, true );
}

// Loads settings CSS
wp_enqueue_style( 'wp_lib_admin_settings' );

// If settings have been updated (or failed to do so)
if ( isset( $_GET['settings-updated'] ) ) {
	$updated = $_GET['settings-updated'];
	
	// If settings were successfully updated, notifies user
	if ( $updated == 'true' ) {
		?>
		<script type="text/javascript">
			wp_lib_add_notification( [ 0, 'Settings updated successfully' ] );
		</script>
		<?php
	} elseif ( $updated == 'false' ) {
		?>
		<script type="text/javascript">
			wp_lib_add_notification( [ 1, 'Settings failed to update' ] );
		</script>
		<?php
	}
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