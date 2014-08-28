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
			textPreview.text( string_to_slug( text.val() ) );
			
		});
	});
});

// Converts string into proper slug for preview
// Thanks to dense13 for this function (http://dense13.com/blog/2009/05/03/converting-string-to-slug-javascript/)
function string_to_slug(str) {
	str = str.replace(/^\s+|\s+$/g, ''); // trim
	str = str.toLowerCase();

	// remove accents, swap ס for n, etc
	var from = "אבהגטיכךלםןמעףצפשתüûסח·/_,:;";
	var to   = "aaaaeeeeiiiioooouuuunc------";
	for (var i=0, l=from.length ; i<l ; i++) {
		str = str.replace(new RegExp(from.charAt(i), 'g'), to.charAt(i));
	}

	str = str.replace(/[^a-z0-9 -]/g, '') // remove invalid chars
	.replace(/\s+/g, '-') // collapse whitespace and replace by -
	.replace(/-+/g, '-'); // collapse dashes

	return str;
}