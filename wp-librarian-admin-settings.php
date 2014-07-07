<?php

wp_enqueue_style( 'wp_lib_admin_settings' );

?>
<div id="wp-lib-admin-wrapper">
	<div id="wp-lib-title">
		<h1>WP-Librarian Settings</h1>
	</div>

	<div id="wp-lib-main-content">
		<form id="wp-lib-settings" method="post" action="options.php">
			<table class="wp-lib-settings">
				<tbody>
					<tr>
						<th>
							<h4>Main Slug<h4>
						</th>
						<td>
							<input type="text" name="wp-lib-main-slug" value="<?php echo get_option( 'wp_lib_slug', 'Error' ); ?>" />
						</td>
					</tr>
					<tr>
						<th>
							<h4>Authors Slug<h4>
						</th>
						<td>
							<input type="text" name="wp-lib-main-slug" value="<?php echo get_option( 'wp_lib_authors_slug', 'Error' ); ?>" />
						</td>
					</tr>
					<tr>
						<th>
							<h4>Media Type Slug<h4>
						</th>
						<td>
							<input type="text" name="wp-lib-main-slug" value="<?php echo get_option( 'wp_lib_media_type_slug', 'Error' ); ?>" />
						</td>
					</tr>
					<tr>
						<th>
							<h4>Donors Slug<h4>
						</th>
						<td>
							<input type="text" name="wp-lib-main-slug" value="<?php echo get_option( 'wp_lib_donors_slug', 'Error' ); ?>" />
						</td>
					</tr>
					<tr>
						<th>
							<h4>Members Slug<h4>
						</th>
						<td>
							<input type="text" name="wp-lib-main-slug" value="<?php echo get_option( 'wp_lib_members_slug', 'Error' ); ?>" />
						</td>
					</tr>
				</tbody>
			</table>
		</form>
	</div>
</div>