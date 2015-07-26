jQuery(document).ready(function($) {
	// Item meta - The current values of the item's meta fields, stored as key/value pairs
	var meta = JSON.parse(document.getElementById('meta-raw').innerHTML);
	
	// Meta format - Instructions on how to display the meta fields and labels for the fields
	var metaFormat = JSON.parse(document.getElementById('meta-formatting').innerHTML);
	
	// Selects meta box to be filled with meta sections and fields
	var metaBox = $('#item-meta');

	// Iterates over each meta section (basic details/book details/etc.) rendering its meta fields
	metaFormat.forEach(function(metaSection) {
		// Sets up basic object properties
		metaWrapperArgs = wp_lib_init_object(metaSection);
		
		// Creates wrapper for meta section
		var metaWrapper = $('<div/>', metaWrapperArgs).addClass('meta-section').appendTo(metaBox);
		
		// If section is specific to item's media type, set class for later use (hiding section)
		if (metaSection.value) {
			metaWrapper.addClass('meta-media-type-section');
		}

		// Renders section title and adds to meta section wrapper
		metaWrapper.append(
			$('<div/>', {
				'class' : 'meta-section-title',
				'html'  :
					$('<h3/>', {
						'text'  : metaSection.title
					})
			})
		);
		
		// Renders then selects fields wrapper
		var metaFieldsTable = $('<table/>', {
			'class' : 'meta-section-fields'
		})
		.appendTo(metaWrapper);
		
		// If metaSection has meta fields
		if (metaSection.fields instanceof Array) {
			// Iterates over each meta section's fields, rendering to the section wrapper
			metaSection.fields.forEach(function(metaField) {
				// Fetches previous meta value from meta array
				var currentMeta = meta[metaField.name];
				
				// Initialises element's properties
				var elementObject = wp_lib_init_object(metaField);
				
				// If element has hover over text, add to element
				if (metaField.hasOwnProperty('altText')) {
					elementObject.title = metaField.altText;
				}
				
				// Performs actions based on the meta field's specific element type
				switch(metaField.type) {
					// For select elements (drop down menus)
					case 'select':
						// Creates select element, adds default classes then adds default select option
						var theElement = $('<select/>',elementObject)
						.addClass('meta-select')
						.append(
							$('<option/>', {
								'html'  : 'Select'
							})
						);
						
						// If meta select has options (possible there will be none if no members have been added)
						if (metaField.options instanceof Array) {
							// Iterates through select field's options, adding them to select element
							metaField.options.forEach(function(option) {
								// Initialises option's properties
								var optionObject = {
									'value' : option.value,
									'html'  : option.html
								};
								
								// If option is the current value, pre-select as that option
								if (option.value == currentMeta) {
									optionObject.selected = 'selected';
								}
								
								// Creates option and adds to to select field's options
								theElement.append($('<option/>', optionObject));
							});
						}
					break;
					
					// For all other element types
					default:
						// Creates input element
						var theElement = $('<input/>',elementObject).attr('type',metaField.type);
						
						// Switch to build field's input element
						switch (metaField.type) {
							case 'checkbox':
								if (currentMeta === '1') {
									theElement.attr('checked','checked');
								}
								
								theElement.attr('value','true');
							break;
							
							default:
								theElement.attr('value',currentMeta);
							break;
						}
					break;
				}
				
				// Creates meta row with title and meta value (theElement)
				metaFieldsTable.append($('<tr/>', {
					'class' : 'meta-field-row',
					'html'  : [
						$('<td/>', {
							'text'  : metaField.title,
							'class' : 'meta-field-title'
						}),
						$('<td/>', {
							'class' : 'meta-input-wrapper',
							'html'  : theElement
						})
					]
				}));
			});
		}
	});
	
	// Checks for post type specific code to run after the meta-box has been rendered
	if (typeof wp_lib_post_render === "function") {
		wp_lib_post_render($);
	}
});
