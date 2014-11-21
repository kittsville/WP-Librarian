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
	metaTypeSelector.change( function() {
		currentMediaType = wp_lib_update_meta_box_display( metaTypeSelector.val(), currentMediaType );
	});
	
	// Formats ISBN field, if input exists
	var isbnField = $( '[name="wp_lib_item_isbn"]' );
	
	if ( isbnField.val() != '' ) {
		isbnField.val( hyphenateISBN( isbnField.val() ) );
	}
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
		if ( theSection.val() === oldMediaType ) {
			theSection.hide( 400 );
		}
		// If the section is the new media type's section, shows it
		else if ( theSection.val() === newMediaType ) {
			theSection.show( 400 );
		}
	});
	
	return newMediaType;
}

// Empties all meta fields in hidden media types on item updating
jQuery(function($){
	// On clicking Save Draft/Publish/etc.
	$('div#submitpost').on('click', '[type="submit"]', function ( e ){
		// Fetches current media type selected
		var currentMediaType = $( '#meta-media-type-selector' ).val();
		
		// Iterates over all media type sections, clearing their fields if the type is unselected
		$( '.meta-media-type-section' ).each( function( i, obj ) {
			// Selects current Meta Section
			var theSection = jQuery( obj );
			
			// If section's media type matches the selected one, continue loop, otherwise clear fields
			if ( theSection.val() === currentMediaType ) {
				return true;
			} else {
				// Iterates over meta fields within meta section, clearing them if they're an input or select
				$( theSection.find( 'td.meta-input-wrapper *' ) ).each( function( i, metaField ) {
					if ( metaField.tagName != 'OPTION' ) {
						$( metaField ).val('');
					}
				});
			}
		});
	});
});