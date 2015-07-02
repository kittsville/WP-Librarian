<?php
/**
 * WP-LIBRARIAN SETTINGS
 * Utilises WordPress Settings API to render settings fields for this plugin
 */

// No direct loading
defined('ABSPATH') OR die('No');

// If user isn't allowed to modify site's settings, settings fields aren't rendered and error is called
if (!current_user_can('wp_lib_change_settings')) {
	wp_lib_error(112, true);
}

$selected_tab = isset($_GET['tab']) ? $_GET['tab'] : '';

do_action('wp_lib_settings_page', $selected_tab);

$tabs = apply_filters('wp_lib_settings_tabs', array(
	''      => array('wp_lib_library_group',    'General'),
	'slugs' => array('wp_lib_slug_group',       'Slugs'),
), $selected_tab);

$settings_tab = isset($tabs[$selected_tab]) ? $tabs[$selected_tab][0] : $tabs[''][0];
?>
<div id="wp-lib-admin-wrapper" class="wrap">
	<?php wp_lib_render_plugin_version(); ?>
	<div id="title-wrap">
		<h2>WP-Librarian Settings</h2>
	</div>
	<!-- Filled with any notifications waiting in a session -->
	<div id="wp-lib-notifications"></div>
	
	<h2 class="nav-tab-wrapper">
		<?php
			foreach ($tabs as $key => $tab) {
				$href   = '?post_type=wp_lib_items&page=wp-lib-settings';
				$class  = 'nav-tab';
				
				if ($key !== '')
					$href .= '&tab=' . $key;
				
				// If current tab is selected, display as such
				if ($settings_tab === $tab[0])
					$class .= ' nav-tab-active';
				
				echo "<a href='{$href}' class='{$class}'>{$tab[1]}</a>";
			}
		?>
	</h2>

	<div id="wp-lib-main-content">
		<form method="POST" action="options.php">
			<?php
			// Handles nonces and other page security
			settings_fields($settings_tab);
			
			// Renders selected tab's settings fields
			do_settings_sections($settings_tab . '-options');
			
			// Submit button to update options
			submit_button('Update', 'primary');
			?>
		</form>
	</div>
</div>
<?php
