jQuery( document ).ready(function($) {
	// Fetches existing item meta and meta fields
	var meta = JSON.parse( document.getElementById( 'meta-raw' ).innerHTML );
	var metaFormat = JSON.parse( document.getElementById( 'meta-formatting' ).innerHTML );
	
	// Selects meta box to be filled with meta sections and fields
	var metaBox = $( '#item-meta' );

	// Iterates over each meta section (basic details/book details/etc.) rendering its meta fields
	metaFormat.forEach( function( metaSection ) {
		// Sets up basic object properties
		metaWrapperArgs = wp_lib_init_object( metaSection );
		
		// Creates wrapper for meta section
		var metaWrapper = $('<div/>', metaWrapperArgs ).addClass( 'meta-section' ).appendTo( metaBox );
		
		// If section is specific to item's media type, set class for later use (hiding section)
		if ( metaSection.value ) {
			metaWrapper.addClass( 'meta-media-type-section' );
		}

		// Renders section title and adds to meta section wrapper
		metaWrapper.append(
			$('<div/>', {
				'class'	: 'meta-section-title',
				'html'	:
					$('<h3/>', {
						'text'	: metaSection.title
					})
			})
		);
		
		// Renders then selects fields wrapper
		var metaFieldsTable = $('<table/>', {
			'class'	: 'meta-section-fields'
		})
		.appendTo( metaWrapper );
		
		// If metaSection has meta fields
		if ( metaSection.fields instanceof Array ) {
			// Iterates over each meta section's fields, rendering to the section wrapper
			metaSection.fields.forEach( function( metaField ) {
				// Fetches previous meta value from meta array
				var currentMeta = meta[metaField.name];
				
				// Creates field input wrapper
				var metaInputWrapper = $('<td/>', {
					'class'	: 'meta-input-wrapper'
				});
			
				// Creates row that will contain field and adds field title to row
				metaFieldsTable.append( $('<tr/>', {
					'class'	: 'meta-field-row',
					'html'	: [
						$('<td/>', {
							'text'	: metaField.title,
							'class'	: 'meta-field-title'
						}),
						metaInputWrapper
					]
				}));
				
				// If field is a dropdown menu (select), renders with options
				if ( metaField.type === 'select' ) {
					// Initialises select element's properties
					var selectArgs = {
						'class'	: 'meta-select',
						'name'	: metaField.name
					};
					
					// If field has an ID, enter it
					if ( metaField.hasOwnProperty('id') ) {
						selectArgs.id = metaField.id;
					}
					
					// Creates select element
					var metaSelect = $('<select/>', selectArgs )
					// Adds default blank option
					.append(
						$('<option/>', {
							'html'	: 'Select'
						})
					)
					.appendTo( metaInputWrapper );
					
					// If meta select has options (possible there will be none if no members have been added)
					if ( metaField.options instanceof Array ) {
						// Iterates through select field's options, adding them to select element
						metaField.options.forEach( function( option ) {
							// Initialises option's properties
							var optionObject = {
								'value'	: option.value,
								'html'	: option.html
							};
							
							// If option is the current value, pre-select as that option
							if ( option.value == currentMeta ) {
								optionObject.selected = 'selected';
							}
							
							// Creates option and adds to to select field's options
							metaSelect.append( $('<option/>', optionObject) );
						});
					}
				} else {
					// Initialises field input object
					var inputArgs = wp_lib_init_object( metaField );
					inputArgs.type = metaField.type;
					
					// Switch to build field's input element
					switch ( metaField.type ) {
						case 'checkbox':
							if ( currentMeta ) {
								inputArgs.checked = 'checked';
							}
							
							inputArgs.value = 'true';
						break;
						
						default:
							inputArgs.value = currentMeta;
						break;
					}
					
					// Adds input element to meta field
					metaInputWrapper.append( $('<input/>', inputArgs) );
				}
			});
		}
	});
	
	// Checks for post type specific code to run after the meta-box has been rendered
	if (typeof wp_lib_post_render === "function" ) {
		wp_lib_post_render($);
	}
});