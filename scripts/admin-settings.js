jQuery( document ).ready(function($) {
	// Renders any buffered notifications
	wp_lib_display_notifications();
	
	/* 
	 * Updates example slug as text input is updated, providing live preview of what the slug would look like
	 * var text is the input box where the user types the slug
	 * var textPreview is an html span that updates to show whatever var text currently is
	 */
	$('.slug-input').each(function(index,element){
		var text = $(element);
		text.on( 'input', function(e) {
			var textID = $(element).attr("id");
			textPreview = $( '.' + textID + '-text' ); 
			textPreview.text(text.val().replace(/\ /g, '-').toLowerCase());
		});
	});
});