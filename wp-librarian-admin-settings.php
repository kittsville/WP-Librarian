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
		// Flushes permalink rules so new slugs work
		wp_lib_flush_permalinks();
		
		// Notifies user settings have been updated
		wp_lib_add_notification_on_load( 'Settings updated successfully' );
	} elseif ( $updated == 'false' ) {
		// Calls error to inform user that settings update failed
		wp_lib_add_notification_on_load( 'Settings failed to update' );
	}
}

// Sets settings tab to display based on GET param
switch ( $_GET['tab'] ) {
	case 'slugs':
		$settings_tab = 'wp_lib_slug_group';
	break;
	
	case 'dash':
		$settings_tab = 'wp_lib_dash_group';
	break;
	
	default:
		$settings_tab = 'wp_lib_library_group';
	break;
}
?>
<div id="wp-lib-admin-wrapper" class="wrap">
	<?php wp_lib_render_plugin_version(); ?>
	<div id="title-wrap">
		<h2>WP-Librarian Settings</h2>
	</div>
	<!-- Filled with any notifications waiting in a session -->
	<div id="wp-lib-notifications"></div>
	
	<h2 class="nav-tab-wrapper">
		<a href="?post_type=wp_lib_items&page=wp-lib-settings" class="nav-tab <?php echo !isset( $_GET['tab'] ) ? 'nav-tab-active' : ''; ?>">General</a>
		<a href="?post_type=wp_lib_items&page=wp-lib-settings&tab=slugs" class="nav-tab <?php echo ( $_GET['tab'] ) === 'slugs' ? 'nav-tab-active' : ''; ?>">Slugs</a>
		<a href="?post_type=wp_lib_items&page=wp-lib-settings&tab=dash" class="nav-tab <?php echo ( $_GET['tab'] ) === 'dash' ? 'nav-tab-active' : ''; ?>">Dashboard</a>
    </h2>

	<div id="wp-lib-main-content">
		<form method="POST" action="options.php">
			<?php
			// Handles nonces and other page security
			settings_fields( $settings_tab );
			
			// Renders selected tab's settings fields
			do_settings_sections( $settings_tab . '-options' );
			
			// Submit button to update options
			submit_button( 'Update', 'primary' );
			?>
		</form>
	</div>
</div>