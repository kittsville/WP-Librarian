<?php

if ( !current_user_can( 'manage_options' ) ) {
	wp_lib_error( 112, true );
}

wp_enqueue_style( 'wp_lib_admin_settings' );

// Fetches slugs
$main_slug = get_option( 'wp_lib_slug', 'Error' );
$authors_slug = get_option( 'wp_lib_authors_slug', 'Error' );
$type_slug = get_option( 'wp_lib_media_type_slug', 'Error' );
$donors_slug = get_option( 'wp_lib_donors_slug', 'Error' );


?>
<div id="wp-lib-admin-wrapper" class="wrap">
	<div id="wp-lib-title">
		<h1>WP-Librarian Settings</h1>
	</div>

	<div id="wp-lib-main-content">
		<form method="POST" action="options.php">
			<?php
			// Handles nonces and other page security
			settings_fields( 'wp_lib_slugs' );
			
			// Renders all slug fields 
			do_settings_sections( 'wp-lib-settings' );
			
			// Submit button to update options
			submit_button( 'Update', 'primary' );
			?>
		</form>
	</div>
</div>