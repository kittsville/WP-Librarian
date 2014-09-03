jQuery( document ).ready(function($) {
	// Fetches existing item meta and meta fields
	var meta = JSON.parse( document.getElementById( 'meta-raw' ).innerHTML );
	var metaFormat = JSON.parse( document.getElementById( 'meta-formatting' ).innerHTML );
	
	console.log( meta );
	
	// Selects meta box to be filled with meta sections and fields
	var metaBox = $( '#item-meta' );
	
	// Iterates over each meta section (basic details/book details/etc.) rendering its meta fields
	metaFormat.forEach( function( metaSection ) {
		// Initialises div's properties
		metaWrapperArgs = {
			'class'	: 'meta-section',
			'id'	: metaSection.id
		};
		
		// Sets div's section if there is one to set
		if ( metaSection.value ) {
			metaWrapperArgs.value = metaSection.value;
		}
		
		if ( metaSection.class ) {
			metaWrapperArgs.class += ' ' + metaSection.class;
		}
		
		// Creates wrapper for meta section
		var metaWrapper = $('<div/>', metaWrapperArgs ).appendTo( metaBox );
	
		// Renders section title inside of a div
		$('<div/>', {
			'id'	: metaSection.id + '-title',
			'class'	: 'meta-section-title'
		})
		.html(
			$('<h3/>', {
				'text'	: metaSection.title
			})
		)
		.appendTo( metaWrapper );
		
		// Renders then selects fields wrapper
		var metaFieldsTable = $('<table/>', {
			'id'	: metaSection.id + '-fields',
			'class'	: 'meta-section-fields'
		})
		.appendTo( metaWrapper );
		
		// Iterates over each meta section's fields, rendering to the section wrapper
		metaSection.fields.forEach( function( metaField ) {
			// Fetches previous meta value from meta array
			var currentMeta = meta[metaField.name];
		
			// Creates row that will contain field and adds field title to row
			var metaRow = $('<tr/>', {
				'class'	: 'meta-field-row'
			})
			.append(
				$('<td/>', {
					'text'	: metaField.title,
					'class'	: 'meta-field-title'
				})
			)
			.appendTo( metaFieldsTable );
			
			// Creates field input wrapper
			var metaInputWrapper = $('<td/>', {
				'class'	: 'meta-input-wrapper'
			})
			.appendTo( metaRow );
			
			// If field is a dropdown menu (select), renders with options
			if ( metaField.type === 'select' ) {
				// Initialises select element's properties
				var selectArgs = {
					'class'	: 'meta-select',
					'name'	: metaField.name
				};
				
				// If field has an ID, enter it
				if ( metaField.id ) {
					selectArgs.id = metaField.id;
				}
				
				// Creates select element then selects it
				var metaSelect = $('<select/>', selectArgs ).appendTo( metaInputWrapper );
				
				// Adds default blank option
				$('<option/>', {
					'value'	: '',
					'text'	: 'Select'
				})
				.appendTo( metaSelect );
				
				// Iterates through select field's options, rendering them
				metaField.options.forEach( function( option ) {
					// Initialises option's properties
					var optionObject = {
						'value'	: option.value,
						'text'	: option.text
					};
					
					// If option is the current value, pre-select as that option
					if ( option.value == currentMeta ) {
						optionObject.selected = 'selected';
					}
					
					// Creates option and adds to to select field's options
					$('<option/>', optionObject).appendTo( metaSelect );
				});
			} else {
				// Initialises field input object
				var inputArgs = {
					'type'	: metaField.type,
					'name'	: metaField.name
				};
				
				// If field has an ID, enter it
				if ( metaField.id ) {
					inputArgs.id = metaField.id;
				}
				
				// Switch to build field's input element
				switch ( metaField.type ) {
					case 'checkbox':
						if ( currentMeta ) {
							inputArgs.checked = 'checked';
						}
						if ( metaField.value ) {
							inputArgs.value = metaField.value;
						}
					break;
					
					case 'text':
						inputArgs.value = currentMeta;
					break;
				
				}
				$('<input/>', inputArgs).appendTo( metaInputWrapper );
			}
		});
	});
	
	// Selects media type selector
	var metaTypeSelector = $( '#meta-media-type-selector' );
	
	// If selector currently has a value, displays relevant media type
	var currentMediaType = metaTypeSelector.val();
	
	// Iterates over each meta section, hiding it if it's not selected
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
});

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