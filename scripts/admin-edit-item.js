function wp_lib_post_render($) {
	// Selects media type selector (drop-down menu)
	var metaTypeSelector = $( '#meta-media-type-selector' );
	
	// If selector currently has a value, displays relevant media type
	var currentMediaType = metaTypeSelector.val();
	
	// Iterates over each meta section, hiding them if they're not the currently selected media type
	$( '.meta-media-type-section' ).each( function( e, obj ) {
		var theSection = $( obj );
		if ( theSection.val() != currentMediaType ) {
			theSection.hide();
		}
	});
	

	// Creates listener on item Media Type being changed
	$( metaTypeSelector ).change( function() {
		currentMediaType = wp_lib_update_meta_box_display( metaTypeSelector.val(), currentMediaType );
	});
}

function wp_lib_update_meta_box_display( newMediaType, oldMediaType ) {
	// If new media type is same as the current, no change is needed
	if ( oldMediaType === newMediaType ) {
		return newMediaType;
	}
	
	// Hides currently displaying meta section for previously selected media type
	jQuery( '.meta-media-type-section' ).each( function( i, obj ) {
		// Selects current Meta Section
		var theSection = jQuery( obj );
		
		// If the section is the old media type's section, hides it
		if ( theSection.val() == oldMediaType ) {
			theSection.hide( 400 );
		}
		// If the section is the new media type's section, shows it
		else if ( theSection.val() == newMediaType ) {
			theSection.show( 400 );
		}
	});
	
	return newMediaType;
}