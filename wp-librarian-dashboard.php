<h2>Library Dashboard</h2>
<p>Use the options below to manage your Library</p>
<div class="dashboard-buttons-wrap">
<?php
$buttons = array(
	array(
		'title'	=> 'Scan Item',
		'icon'	=> 'default',
		'link'	=> array(
			'type'	=> 'dash-page',
			'page'	=> 'scan-item'
		)
	),
	array(
		'title'	=> 'Manage Items',
		'icon'	=> 'default',
		'link'	=> array(
			'type'	=> 'post-type',
			'name'	=> 'wp_lib_items'
		)
	),
	array(
		'title'	=> 'Manage Members',
		'icon'	=> 'default',
		'link'	=> array(
			'type'	=> 'dash-page',
			'page'	=> 'browse-members'
		)
	),
	array(
		'title'	=> 'Manage Fines',
		'icon'	=> 'default',
		'link'	=> array(
			'type'	=> 'post-type',
			'name'	=> 'wp_lib_fines'
		)
	),
	array(
		'title'	=> 'Settings',
		'icon'	=> 'default',
		'link'	=> array(
			'type'	=> 'admin-url',
			'url'	=> 'edit.php?post_type=wp_lib_items&page=wp-lib-settings'
		)
	),
	array(
		'title'	=> 'Help',
		'icon'	=> 'default',
		'link'	=> array(
			'type'	=> 'url',
			'url'	=> 'http://sci1.co.uk/wp-librarian/'
		)
	)
);
// Sets base url for all icons
$icon_url_base =  plugins_url('/images/dash-icons/', __FILE__);

foreach ( $buttons as $button ) {
	// Initialises button classes
	$classes = 'dashboard-button';
	
	// Sets button properties based on the type of link it will be
	switch ( $button['link']['type'] ) {
		case 'dash-page':
			$classes .= ' dash-page';
			
			$properties = "name='dash_page' value='{$button['link']['page']}'";
		break;
		
		case 'post-type':
			$url = admin_url( 'edit.php?post_type=' . $button['link']['name'] );
		
			$properties = "href='{$url}'";
		break;
		
		case 'url':
			$properties = "href='{$button['link']['url']}'";
		break;
		
		case 'admin-url':
			$url = admin_url( $button['link']['url'] );
			
			$properties = "href='{$url}'";
		break;
	}
	// Constructs url to icon using base url
	$icon_url = $icon_url_base . $button['icon'] . '.png';
	
	echo "<button class='{$classes}' {$properties} ><div class='dash-button-top'><img class='dashboard-icon' src='{$icon_url}' alt='{$button['title']} Icon' /></div><div class='dash-button-bottom'>{$button['title']}</div></button>";

}


?>
</div>