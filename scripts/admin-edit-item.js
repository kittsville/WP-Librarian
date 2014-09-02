jQuery( document ).ready(function($) {
	/*$( '[name=tax_input[wp_lib_media_type]]' ).each( function( e ) {
	
		console.log( e );
		
		$('#library-workspace').change( function( e ) {
			console.log( "Element changed" );
		})
	});*/
	
	var meta = JSON.parse( document.getElementById( 'meta-raw' ).innerHTML );
	
	console.log( meta );
	
	var metaFormat = JSON.parse( document.getElementById( 'meta-formatting' ).innerHTML );
	
	console.log( metaFormat );
	
	// Selects meta box to be filled with meta sections and fields
	var metaBox = $( '#item-meta' );
	
	// Iterates over each meta section (basic details/book details/etc.) rendering its title and fields
	metaFormat.forEach( function( metaSection ) {
		// Creates wrapper for meta section
		var metaWrapper = $('<div/>', {
			'id'	: metaSection.id,
			'class'	: 'meta-section'
		}).appendTo( metaBox );
	
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
				var metaSelect = $('<select/>', {
					'class'	: 'meta-select',
					'name'	: metaField.name
				})
				.appendTo( metaInputWrapper );
				
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
				inputArgs = {
					'type'	: metaField.type,
					'name'	: metaField.name
				};
				
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



});

// Given raw text, formats a title in HTML and appends it to the meta field
function wp_lib_title_builder( title ) {
	// GGGGGG


}