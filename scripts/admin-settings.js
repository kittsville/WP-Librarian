jQuery(function($){
	if ( wp_lib_vars.getParams.hasOwnProperty( 'settings-updated' ) ) {
		switch ( wp_lib_vars.getParams['settings-updated'] ) {
			case 'true':
				wp_lib_local_notification( 'Settings updated successfully' );
			break;
			
			case 'false':
				wp_lib_local_notification( 'Settings failed to update' );
			break;
		}
	}
	
	// Updates slug previews based on changed input field
	function wp_lib_update_slug_previews( slugInput ) {
		// Fetches and sanitizes current slug input
		var inputValue = string_to_slug(slugInput.val());
		
		// If slug input is main slug, update all previews that use the main slug
		// Otherwise updates only preview for that input
		if ( slugInput.attr('name') === 'wp_lib_slugs[0]' ) {
			$('span[name="main-slug-text"]').each(function(i,element){
				$(element).text(inputValue);
			});
		} else {
			slugInput.next().find('span.slug-preview').text(inputValue);
		}
	}
	
	// Iterates over slug input fields, setting up previews and hooking preview updating to input change
	$('input.slug-input').each(function(index,element){
		var slugInput = $(element);
		
		wp_lib_update_slug_previews( slugInput );
		
		slugInput.on( 'input', function(e) {
			wp_lib_update_slug_previews( slugInput );
		});
	});
});

// Converts string into proper slug for preview
// Thanks to dense13 for this function (http://dense13.com/blog/2009/05/03/converting-string-to-slug-javascript/)
function string_to_slug(str) {
	str = str.replace(/^\s+|\s+$/g, ''); // trim
	str = str.toLowerCase();

	// remove accents, swap ñ for n, etc
	var from = "aaaaeeeeiiiioooouuuunc·/_,:;";
	var to   = "aaaaeeeeiiiioooouuuunc------";
	for (var i=0, l=from.length ; i<l ; i++) {
		str = str.replace(new RegExp(from.charAt(i), 'g'), to.charAt(i));
	}

	str = str.replace(/[^a-z0-9 -]/g, '') // remove invalid chars
	.replace(/\s+/g, '-') // collapse whitespace and replace by -
	.replace(/-+/g, '-'); // collapse dashes

	return str;
}