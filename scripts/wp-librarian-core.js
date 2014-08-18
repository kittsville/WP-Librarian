function wp_lib_render_notification( notification ) {
	jQuery('#notifications-holder').append( "<div class='wp-lib-notification updated'><p>" + notification + "</p></div>" );
};