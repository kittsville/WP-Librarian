// An AJAX call to the Library API, to retrieve part of a page or lookup data
function wp_lib_api_call( params, postCallFunction ) {
	// Adds WordPress hook for WP_Lib_API to AJAX request
	params.action = 'wp_lib_api';
	
	// Sends AJAX request
	wp_lib_send_ajax( params, postCallFunction );
}

// Performs Dash action, modifying the Library e.g. Marking an item as returned
function wp_lib_do_action( dashAction, params ) {
	// Initialising AJAX object
	var data = {
		action		: 'wp_lib_action',
		dash_action	: dashAction
	};
	
	// Adds referrer to AJAX request, this is needed for certain actions
	if ( wp_lib_vars.hasOwnProperty( 'dash_page' ) ) {
		data.ref = wp_lib_vars.dash_page;
	}
	
	// AJAX action switch, decides what action should be taken
	switch ( dashAction ) {
		case 'run-test-loan':
			data.item_id = params['item_id'];
		break;
		
		case 'loan':
			data.item_id = params['item_id'];
			data.member_id = params['member_id'];
			data.loan_length = params['loan_length'];
		break;
		
		case 'schedule':
			data.item_id = params['item_id'];
			data.member_id = params['member_id'];
			data.start_date = params['start_date'];
			data.end_date = params['end_date'];
		break;
		
		case 'return-item-no-fine':
			data.fine_member = false;
			data.dash_action = 'return-item';
			// Deliberate lack of break
		case 'return-item':
			data.item_id = params['item_id'];
			data.end_date = params['end_date'];
		break;
		
		case 'give-item':
			data.loan_id = params['loan_id'];
			if ( params.hasOwnProperty( 'give_date' ) ) {
				data.give_date = params['give_date'];
			}
		break;
		
		case 'renew-item':
			data.loan_id		= params['loan_id'];
			data.renewal_date	= params['renewal_date'];
		break;
		
		case 'fine-member':
			data.fine_member = true;
			data.dash_action = 'return-item';
			data.item_id = params['item_id'];
		break;
		
		case 'cancel-fine':
			data.fine_id = params['fine_id'];
		break;
		
		case 'pay-fine':
			data.member_id = params['member_id'];
			data.fine_payment = params['fine_payment'];
		break;
		
		case 'delete-object':
			data.post_id = params['post_id'];
			data.deletion_confirmed = true;
		break;
		
		case 'clean-item':
			data.item_id = params['item_id'];
		break;
		
		case 'scan-barcode':
			data.code = params['item_barcode'];
		break;
	}
	
	// Adds nonce to params to be sent
	data.wp_lib_ajax_nonce = params[WP_LIB_NONCE];
	
	// Sends dash action to server. Returns to Dashboard home on success and displays errors on failure
	wp_lib_send_ajax( data, function( serverResponse ) {
		switch ( serverResponse[0] ) {
			// Server response indicating success
			case 4:
				// Loads Dashboard home
				wp_lib_load_page();
			break;
			
			// Server response indicating failure (by WP-Librarian, not AJAX failure or WordPress error)
			case 3:
				// Checks for any reported errors (notifications). If none were reported uses default error message
				if ( serverResponse[1][1].length === 0 ) {
					wp_lib_local_error( 'The action could not be completed but the server did not explain why' );
				}
			break;
		}
	});
}

// Fetches page, using given parameters
function wp_lib_load_page( ajaxData, stateLoad ) {
	// If page was loaded without a state defined, assumes page is replacing existing page
	if ( typeof stateLoad === 'undefined' ) {
		var stateLoad = 'dynamic';
	}
	
	// If data (page to load/params) was unspecified, initialise
	if ( typeof ajaxData === 'undefined' ) {
		var ajaxData = {};
	}
	
	// If page to load wasn't specified, load Dashboard
	if ( !ajaxData.hasOwnProperty( 'dash_page' ) || ajaxData.dash_page == '' ) {
		ajaxData.dash_page = 'dashboard';
	}
	
	// Sets action, which specifies the name of the hook it will have in WordPress ( wp_ajax_{$hook} )
	ajaxData.action = 'wp_lib_page';
	
	// Adds referrer to AJAX request, to allow page elements to be selectively loaded
	if ( wp_lib_vars.hasOwnProperty( 'dash_page' ) ) {
		ajaxData.ref = wp_lib_vars.dash_page;
	}
	
	// Requests page from server, with function handling any failures
	wp_lib_send_ajax( ajaxData, function( serverResponse ) {
		// Performs actions based on how server responded
		switch( serverResponse[0] ) {
			// If server responded to indicate WP-Librarian failure
			case 3:
				// Checks for any reported errors (notifications). If none were reported uses default error message
				if ( serverResponse[1][1].length === 0 ) {
					wp_lib_local_error( 'Server encounted error while loading page but didn\'t specify why' );
				}
			break;
			
			// If server responded to indicate success
			case 4:
				// Renders page using AJAX returned data
				wp_lib_render_page( serverResponse[1][2] );
				
				// Pushes current dash page to wp_lib_vars
				wp_lib_vars.dash_page = ajaxData.dash_page;
				
				// Checks if new page is replacing existing dynamically loaded page
				// If it isn't then there's no need to add a new history entry
				if ( stateLoad != 'history' ) {
					// Deletes redundant parameters
					delete ajaxData.action;
					delete ajaxData.ref;
					
					// Deletes dash page if it's redundant
					if ( ajaxData.dash_page == 'dashboard' ) {
						delete ajaxData.dash_page;
					}
					
					// Removes Nonce from data to be pushed to URL
					if ( ajaxData.wp_lib_ajax_nonce ) {
						delete ajaxData.wp_lib_ajax_nonce;
					}
					
					// Creates base url for history entry
					var currentPage = wp_lib_vars.dashUrl;
					
					// Serializes page parameters
					var serialAjaxData = jQuery.param( ajaxData );
					
					// If there was any output, add parameters to page URL
					if ( serialAjaxData != '' ) {
						currentPage += '&'+ serialAjaxData;
					}
					
					if ( stateLoad == 'first' ) {
						// Saves essential data to history entry for use if page navigation is used
						history.replaceState( ajaxData, wp_lib_format_tab_title( serverResponse[1].title ), currentPage );
					} else if ( stateLoad == 'dynamic' ) {
						// Changes URL to reflect new page (also creates browser history entry)
						history.pushState( ajaxData, wp_lib_format_tab_title( serverResponse[1].title ), currentPage );
					} else {
						return;
					}
				}
			break;
			
			default:
				// On misc AJAX failure (preventing page load), return false
				return false;
			break;
		}
	});
}

/*
 * Renders a Dashboard page
 * @param array pageArray Array containing page title, tab title then an array of all page elements
 */
function wp_lib_render_page( pageArray ) {
	
	// jQuery setup
	var $ = jQuery;
	
	// Clears existing params in preparation of new page
	wp_lib_vars.getParams = {};

	// Changes page title
	$( '#page-title' ).html( pageArray[0] );
	
	// Changes title of browser tab
	document.title = wp_lib_format_tab_title( pageArray[1] );
	
	// Clears and selects Library workspace in preparation of new page
	var libWorkspace = $( '#wp-lib-workspace' ).empty();
	
	// Initialises pseudo-URL base and array of buttons that need Pseudo-URLs
	var urlBase = wp_lib_vars.dashUrl;
	var formDashPageButtons = [];
	
	// Iterates over page elements, turning them into HTML elements then appending them to the Dashboard
	pageArray[2].forEach(function(e,i){
		wp_lib_render_page_element( e, libWorkspace );
	});
	
	// If any scripts are required by the page, loads them
	if ( pageArray[3] instanceof Array ) {
		$( pageArray[3] ).each( function( i, scriptURL ) {
			$.getScript( wp_lib_vars.pluginsUrl + '/scripts/' + scriptURL + '.js' )
			.fail( function( jqxhr, settings, exception ) {
				wp_lib_local_error( "Failed to load JavaScript needed for this page" );
			});
		});
	}
}

// Renders a page element to the specified parent
// Function is recursive and will use itself to render nested elements
function wp_lib_render_page_element( pageItem, theParent ) {
	var $ = jQuery;
	
	// Sets up basic properties of object such as class/ID/name
	elementObject = wp_lib_init_object( pageItem );
	
	// If any classes need to be added to the specific element, adds them
	if ( pageItem.hasOwnProperty( 'classes' ) ) {
		// If new classes are stored as an array, turns into string first
		if ( pageItem.classes instanceof Array ) {
			elementObject['class'] = pageItem.classes.join(' ');
		} else if ( typeof pageItem.classes === 'string' ) {
			elementObject['class'] = pageItem.classes;
		}
	}
	
	// If element has a label, creates
	if ( pageItem.hasOwnProperty('label') ) {
		// Creates label, appends to parent, then sets parent as label. This means the pageItem will be inside the label
		theParent = $('<label/>', {
			'for'	: pageItem.label
		}).appendTo( theParent );
	}
	
	// Performs actions on page element based on element type
	// Note that each element type has requirements specific to the Dashboard, otherwise they would rendered without calling this function
	switch ( pageItem.type ) {
		// Hidden inputs used to store information such as the item ID
		case 'hidden':
			var theElement = $('<input/>', elementObject ).attr('type', pageItem.type );
			
			wp_lib_vars.getParams[elementObject.name] = elementObject.value;
		break;
		
		// Nonces used to verify the source of do_action requests
		case 'nonce':
			var theElement = $('<input/>', elementObject ).attr({
				'type'	: 'hidden',
				'id'	: WP_LIB_NONCE,
				'name'	: WP_LIB_NONCE
			});
		break;
		
		// Buttons used to load Dash pages or perform Dash actions
		case 'button':
			// Creates DOM element and adds classes to use native WordPress styles
			var theElement = $('<a/>', elementObject ).addClass( 'dash-button dash-button-medium' );
			
			// Sets anchor type to button for appropriate styling/behaviour
			theElement.attr('type','button');
			
			// Sets up button properties based on the button type
			switch ( pageItem.link ) {
				// Button to load a new Dash page
				case 'page':
					theElement.attr('name','dash_page');
				break;
				
				// Button to modify the library (e.g. loan an item)
				case 'action':
					theElement.attr('name','dash_action');
				break;
				
				// Button that links to an external URL
				case 'url':
					theElement.attr({
						onclick	: wp_lib_vars.onClick
					});
				break;
				
				case 'edit':
					// Initialises item/member ID
					var postID = 0;
					
					// Sets post ID as item or member ID based off existing params
					if ( wp_lib_vars.getParams.hasOwnProperty( 'item_id' ) ) {
						postID = wp_lib_vars.getParams.item_id;
					} else if ( wp_lib_vars.getParams.hasOwnProperty( 'member_id' ) ) {
						postID = wp_lib_vars.getParams.member_id;
					}
					
					// Creates link to edit item/member
					theElement.attr({
						href	: wp_lib_vars.adminUrl + 'post.php?action=edit&post=' + postID,
						onclick	: wp_lib_vars.onClick
					});
				break;
				
				case 'none':
					theElement.attr('name','dash_blank');
				break;
				
				// If button has no known type, render error
				default:
					wp_lib_render_page_element({}, theParent);
					return;
				break;
			}
			
			// If button has an icon overwrite button contents with icon
			if ( pageItem.hasOwnProperty( 'icon' ) ) {
				theElement.html($('<div/>',{
					'class'	: 'dash-icon dash-icon-medium dashicons dashicons-' + pageItem.icon,
				}))
				.addClass('dash-icon-medium-parent'); // Adds class to icon parent (button) as CSS3 selectors don't support selecting an element based on its children
			}
		break;
		
		// Dash urls give the appearance of regular URLs, but are loaded dynamically when clicked
		case 'dash-url':
			// Sets up local parent that will hold URL's parameters
			var theElement = $('<form/>',{
				'class' : 'dash-url-form'
			});
			
			// Iterates over URL parameters, rendering them as hidden inputs
			$.each( pageItem.params, function( paramName, paramValue ) {
				theElement.append(
					$('<input/>',{
						type	: 'hidden',
						name	: paramName,
						value	: paramValue
					})
				);
			});
			
			// Creates hyperlink with mock URL
			theElement.append(
				$('<a/>',elementObject)
				.attr('href', wp_lib_vars.dashUrl + '&' + $.param(pageItem.params) )
				.addClass('dash-url')
			);
			
		break;
		
		// Dash buttons are the large buttons on the Dashboard homepage
		case 'dash-button':
			// Creates element and adds default dash button class
			theElement = $('<a/>',elementObject).addClass('dash-button dash-button-large');
			
			// Sets anchor type to button for appropriate styling/behaviour
			theElement.attr('type','button');
			
			// Sets button's click behaviour based on its link type
			switch ( pageItem.link ) {
				case 'dash-page':
					theElement.attr('name','dash_page');
				break;
				
				case 'post-type':
					theElement.attr({
						href	: wp_lib_vars.adminUrl + 'edit.php?post_type=' + pageItem.pType,
						onclick	: wp_lib_vars.onClick
					});
				break;
				
				case 'admin-url':
					theElement.attr({
						href	: wp_lib_vars.adminUrl + pageItem.url,
						onclick	: wp_lib_vars.onClick
					});
				break;
				
				case 'url':
					theElement.attr({
						href	: pageItem.url,
						onclick	: wp_lib_vars.onClick
					});
				break;
			}
			
			// If dash icon is not valid, sets to default (a cross)
			if ( !pageItem.hasOwnProperty( 'icon' ) ) {
				pageItem.icon = 'no';
			}
			
			// Adds button innards: icon and button name
			theElement.html(
				// Wrapper
				$('<div/>', {
				'class'	: 'dash-button-wrapper',
				html	: [
					// Icon
					$('<div/>', {
						'class'	: 'dash-icon dash-icon-large dashicons dashicons-' + pageItem.icon,
					}),
					// Button text
					$('<h4/>',{
						'class'	: 'dash-button-text',
						html	: pageItem.bName
					})
				]})
			);
		break;
		
		// Paras groups of at least one paragraph, wrapped in a div
		case 'paras':
			// Can be passed array of strings or single string
			if ( pageItem.hasOwnProperty('content') ){
				if ( pageItem.content instanceof Array ) {
					elementObject.html = [];
				
					$( pageItem.content ).each( function ( i, e ) {
						elementObject.html.push($('<p/>', {
							html	: e
						}));
					});
				} else if ( typeof pageItem.content === 'string' ) {
					elementObject.html = $('<p/>', {
						html	: pageItem.content
					});
				}
			}
			
			
			
			// Creates parent div that contains the paragraph elements
			var theElement = $('<div/>',elementObject)
			.addClass('dash-paras');
		break;
		
		// Date elements are inputs with jQuery datepickers
		case 'date':
			// Creates date input element, adds default datepicker styles then turns into a jQuery datepicker
			var theElement = $('<input/>', elementObject )
			.attr({ type : pageItem.type })
			.addClass('dash-datepicker')
			.datepicker();
			
			// Adds necessary class to utilise melon styles
			$( '#ui-datepicker-div' ).addClass('ll-skin-melon');
		break;
		
		// Input elements, boxes for inputting text, numbers, colours and more
		case 'input':
			var theElement = $('<input/>', elementObject );
			
			// If input has any special attributes, add them to the input element
			if ( pageItem.hasOwnProperty('attr') ) {
				$(pageItem.attr).each(function(i,anAttr){
					theElement.attr(anAttr,pageItem.attr[anAttr]);
				});
			}
			
			// If enter key is pressed, carry out dash action
			theElement.keydown(function(event){
				
			if(event.keyCode == 13) {
				theElement.siblings('a[type="button"]').click();
			}
			});
		break;
		
		// Dropdown menu with different options
		case 'select':
			// Renders select element and adds it to the page
			var theElement = $('<select/>', elementObject );
			
			// Initialises select option output
			var elementSelectOptions = [];
			
			// Iterates through select element's options, adding them to the select element
			$( pageItem.options ).each( function( i, selectOption ) {
				elementSelectOptions.push( $('<option/>', {
					'value'	: selectOption.value,
					'html'	: selectOption.html,
					'class'	: pageItem.optionClass
				}) );
			});
			
			// Adds select options to select element
			theElement.html( elementSelectOptions );
		break;
		
		// Regular h2/h3/etc. element
		case 'header':
			var theElement = $('<h' + pageItem.size + '/>', elementObject );
		break;
		
		// A div with elements inside
		case 'div':
			// Creates div
			var theElement = $('<div/>', elementObject );
			
			// Iterates over inner elements, rendering them to the theElement (the div)
			$( pageItem.inner ).each( function( i, e ) {
				wp_lib_render_page_element( e, theElement );
			});
		break;
		
		// Bold text
		case 'strong':
			var theElement = $('<strong/>', elementObject );
		break;
		
		// An item/member/etc. meta box containing information on the object concerned
		case 'metabox':
			// Creates meta box wrapper
			var theElement = $('<div/>',elementObject)
			.addClass('lib-metabox')
			.append($('<strong/>',{
				html	: pageItem.title
			}));
			
			// Initialises meta field output
			var metaFields = [];
			
			// Iterates through meta fields, rendering them to the meta box
			$( pageItem.fields ).each( function( i, e ) {
				// Fetches field's name and contents
				var fieldName = e[0];
				var fieldData = e[1];
				
				// If meta field is a collection of one or more URLs (e.g. tax terms like item authors)
				if ( fieldData instanceof Array ) {
					// If array has more than one url, pluralises field name
					if ( fieldData.length > 1 ) {
						fieldName += 's';
					}
					
					// Initialises Output
					var multiUrlOutput = [];
					
					// Iterates through urls, creating hyperlink and adding to output
					$( fieldData ).each( function( i, taxData ) {
						// If url is not the first, prepends with comma
						if ( i > 0 ) {
							multiUrlOutput.push( ', ' );
						}
						
						// Adds url to output buffer
						multiUrlOutput.push( $('<a/>',{
							href	: taxData[1],
							html	: taxData[0]
						}));
					});
					
					// Sets output variable to actual output
					fieldData = multiUrlOutput;
				
				// If fieldData is a single URL
				} else if ( fieldData instanceof Object ) {
					// Renders URL element and sets as fieldData
					fieldData = wp_lib_render_page_element( fieldData );
				}
				
				// Renders meta row inside div and adds to 
				metaFields.push( $('<tr/>', {
					'class'	: 'meta-row',
					html	: [
						// Meta field's name e.g. Item ID
						$('<th/>', {
							'html'	: fieldName + ':'
						}),
						
						// Meta field's value e.g. 42
						$('<td/>', {
							'html'	: fieldData
						})
					]
				}));
			});
			
			// Adds meta fields to meta wrapper
			theElement.append($('<table/>',{
				'class'	: 'lib-metabox',
				'html'	: metaFields
			}));
		break;
		
		case 'form':
			var theElement = $('<form/>', elementObject );
			
			// Adds default attributes
			theElement.addClass('lib-form');
			theElement.attr('onsubmit', 'return false;');
			
			// If form's has child elements, render and give all buttons Pseudo-URLs
			if ( pageItem.hasOwnProperty('content') && pageItem.content instanceof Array ) {
				var urlBase = wp_lib_vars.dashUrl;
				
				// Builds Pseudo-URL from base url using hidden form elements
				pageItem.content.forEach(function(e,i){
					if ( e.hasOwnProperty('type') && e.type === 'hidden' ) {
						urlBase += '&' + e.name + '=' + e.value;
					}
				});
				
				// Renders all form buttons then gives buttons Pseudo-URL
				pageItem.content.forEach(function(e,i){
					var formElement = wp_lib_render_page_element( e, theElement );
					
					if ( e.hasOwnProperty('type') && e.type === 'button' && e.link === 'page' ) {
						formElement.attr('href',urlBase+'&dash_page='+e.value);
					}
				});
			}
		break;
		
		// Dynamic table managed by Dynatable
		case 'dtable':
			// Creates table head
			var tableHead = $('<thead/>', {} );
			
			// Iterates through given table columns, adding them to the table head
			$( pageItem.headers ).each( function( i, header ) {
				$('<th/>', {
					html	: header
				} ).appendTo( tableHead );
			});
			
			// If dev is an idiot and forgets to give the table a class, assigns one randomly
			if ( !pageItem.hasOwnProperty('id') ) {
				elementObject.id = 'dynamic-table-' + Math.floor((Math.random() * 100) + 1);
			}
			
			// Creates table, adds default classes and sets inner html to table head and body
			var theTable = $('<table/>',elementObject)
			.addClass(['dynatable-table', 'wp-list-table', 'widefat', 'fixed', 'posts'].join(' '))
			.html([
				tableHead,
				$('<tbody/>', {} )
			]);
			
			/* Prepares data for Dynatable */
			
			// Initialises output
			var tableRecords = [];
			
			// Iterates through table data, adding it to output in correct format
			$.each( pageItem.data, function( i, row ) {
				// Initialises row output
				var tableRow = {};
				
				// Iterates through row's data, setting up values for Dynatable
				$.each( row, function( columnName, columnData ) {
					// If row is a hyperlink, formats
					if ( columnData instanceof Object ) {
						columnData = wp_lib_render_page_element( columnData ).prop('outerHTML');
					}
					
					// Creates entry in table row named with column's slug (e.g. load-id) and sets value to 
					tableRow[columnName] = columnData;
				});
				
				// Adds row to table records
				tableRecords.push( tableRow );
			});
			
			// If table does not have a custom value for '1 of 5 records', use default
			if ( !pageItem.hasOwnProperty('labels') || !pageItem.labels.hasOwnProperty('records') ) {
				pageItem.labels = {
					records	: 'records'
				};
			}
			
			// Wraps element in div, element remains
			var theElement = $('<div/>',{
				'class'	: 'dynatable-wrap',
				html	: theTable
			}).appendTo(theParent);
			
			// Fills table with formatted data
			theTable.dynatable({
				dataset: {
					records: tableRecords,
					perPageDefault: 10,
					perPageOptions: [10, 20, 50, 100]
				},
				params: {
					records: pageItem.labels.records
				},
				inputs: {
					processingText: '<div class="dtable-process-wrap"><div class="dtable-process-icon dashicons dashicons-book-alt"></div></div>'
				}
			});
			
			// Iterates over table headers, adding class to use WordPress table styling
			$( '.dynatable-head' ).each( function(i, tableHeader) {
				$(tableHeader).addClass('manage-column');
			});
			
			// Skips adding element to parent as this was performed earlier so that Dynatable would work properly
			return;
		break;
		
		// List of items with options to manage/view, managed by Dynatable
		case 'item-list':
			var theTable = $('<ul/>', elementObject ).addClass('item-list');
			
			// Function to render a single item (row)
			function render_item_row( rowIndex, record, columns, cellWriter ) {
				// Initialises item's list entry elements
				var listItemElements = [
					$('<input/>',{
						type	: 'hidden',
						name	: 'item_id',
						value	: record.item_id
					})
				];
				
				// If item has a cover image, use. Otherwise use placeholder div that will be filled with book icon
				if ( record.cover !== false ) {
					listItemElements.push($('<div/>', {
							'class'	: 'item-thumbnail',
							'html'	:
								$('<div/>',{
									'class'	: 'item-thumbnail-centrefix',
									'html'	:
										$('<img/>',{
											'src'	: record.cover[0],
											'class'	: 'item-thumbnail'
										})
								})
						})
					);
				} else {
					listItemElements.push( $('<div/>', {
						'class'	: 'item-no-thumbnail'
					}));
				}
				
				// Reduces item's title length if it is too long
				// This method will be improved in future
				if ( record.title.length > 50 ) {
					record.title = record.title.substring(0,48) + '...';
				}
				
				// Initialises item meta elements with item title
				var itemMeta = [
					$('<h4/>',{
						'class'	: 'item-title',
						'html'	: record.title
					})
				];
				
				// If item has authors list list authors
				if ( record.authors != false ) {
					var allAuthors = record.authors.join(', ');
					
					// If authors list is too long, clip
					if ( allAuthors.length > 45 ) {
						allAuthors = allAuthors.substring(0,43) + '...';
					}
					itemMeta.push(
						$('<p/>',{
							'class'	: 'item-authors',
							'html'	: 'Authors: ' + allAuthors
						})
					);
				}
				
				// Adds item status (Available/on loan/etc.) to item meta
				itemMeta.push(
					$('<p/>',{
						'class'	: 'item-status',
						'html'	: 'Status: ' + record.status
					})
				);
				
				// Adds item title and details to entry elements
				listItemElements.push(
					$('<div/>',{
						'class'	: 'item-meta',
						'html'	: itemMeta
					})
				);
				
				// Creates list element
				var singleItem = $('<li/>', {
					'class'	: 'single-item'
				});
				
				var localParent = $('<form/>', {
					'html'	: listItemElements
				}).appendTo(singleItem);
				
				// Adds class to colour item red if it is currently late
				if ( record.late == true ) {
					singleItem.addClass( 'late-item' );
				}
				
				// Renders manage item button to invisible form containing item's ID
				wp_lib_render_page_element( {
						type	: 'button',
						link	: 'page',
						value	: 'manage-item',
						html	: 'Manage',
						classes	: 'item-manage',
						href	: wp_lib_vars.dashUrl+'&dash_page=manage-item&item_id='+record.item_id
					},
					localParent
				);
				
				// Creates 'View' button to view items public listing
				wp_lib_render_page_element( {
					type		: 'button',
					link		: 'url',
					href		: record.view,
					html		: 'View',
					classes		: 'item-view'
				}, localParent );
				
				// Converts to html string, as Dynatable doesn't accept DOM objects
				return singleItem.prop('outerHTML');
			}
			
			// Wraps table in div
			var theElement = $('<div/>',{
				'class'	: 'item-list-wrap',
				html	: theTable
			}).appendTo(theParent);
			
			// Sets up table as Dynatable
			theTable.dynatable({
				table: {
					bodyRowSelector: 'li'
				},
				writers: {
					_rowWriter: render_item_row
				},
				dataset: {
					records: pageItem.data,
					perPageDefault: 20,
					perPageOptions: [20, 40, 60, 100]
				},
				params: {
					records: 'items'
				},
				inputs: {
					processingText: '<div class="dtable-process-wrap"><div class="dtable-process-icon dashicons dashicons-book-alt"></div></div>'
				}
			});
			
			// Skips adding element to parent as this was performed earlier so that Dynatable would work properly
			return;
		break;
		
		default:
			// If debugging mode is enabled, render obvious placeholder for invalid element type
			// Otherwise fail subtly
			if ( wp_lib_vars.debugMode ) {
				console.log( 'Dash element has unknown element type:' );
				console.log( pageItem );
				
				var theElement = $('<strong/>', {
					'html'	: 'UNKNOWN ELEMENT TYPE<br/>',
					'style'	: 'color:red;'
				});
			} else {
				var theElement = $('<div/>',{});
			}
		break;
	}
	
	// If parent was given, appends element to parent
	if ( typeof theParent !== 'undefined' ) {
		theElement.appendTo( theParent );
	}
	
	return theElement;
}

function wp_lib_format_tab_title( title ) {
	return title + ' ‹ ' + wp_lib_vars.siteName;
}

// Loads page content on page load. Only used if page is visited from a non-Dashboard page
jQuery(function($){
	$.dynatableSetup({
		table: {
			headRowSelector: 'thead'
		},
		features: {
			pushState: false
		}
	});
	
	$.datepicker.setDefaults({
		dateFormat: 'yy-mm-dd',
		inline: true,
		showOtherMonths: true
	});
	
	// Fetches GET parameters from global variables
	var GetVars = wp_lib_vars.getParams;
	
	// Removes default GET params as they are no longer needed
	delete GetVars["post_type"];
	delete GetVars["page"];
	
	// Loads relevant page
	wp_lib_load_page( GetVars, 'first' );
	
	// Sets trigger for the back button being used
	window.onpopstate = function( event ) {
		wp_lib_load_page( event.state, 'history' );
	}
	
	// Adds listener for action performing buttons
	jQuery('#wp-lib-workspace').on('click', 'a[name="dash_action"]', function ( e ){
		// Fetches action to be performed
		var action = e.currentTarget.value;
		
		// Fetches form parameters that have been set
		var params = wp_lib_collect_form_params(e.target);
		
		// Performs action
		wp_lib_do_action( action, params );
		
		return false;
	});
	
	// Loads dash page when a dash page button or dash URL is clicked
	jQuery('#wp-lib-workspace').on('click', 'a[name="dash_page"], a.dash-url', function ( e ){
		// Fetches form parameters that have been set
		var params = wp_lib_collect_form_params(e.target);
		
		// If element is a dash button
		if ( e.currentTarget.getAttribute('name') === 'dash_page' ) {
			// Fetch dash page from element value
			params.dash_page = e.currentTarget.getAttribute('value');
			
			// Deletes nonce as it is only needed for dash actions
			delete params.wp_lib_ajax_nonce;
		}
		
		// Loads page in current or new tab, depending on whether control was pressed
		if ( e.ctrlKey ) {
			// Allows default behaviour, resulting in pseudo-URL being opened in new tab
			return true;
		} else {
			// Dynamically loads page
			wp_lib_load_page( params );
			
			// Prevents default behaviour
			return false;
		}
	});
	
	// Adds listener for Item thumbnail being clicked
	jQuery('#wp-lib-workspace').on('click', 'img.item-thumbnail', function ( e ){
		// Fetches form parameters that have been set
		var params = wp_lib_collect_form_params(e.target);
		
		// Sets page to load
		params.dash_page = 'manage-item';
		
		// Loads page
		wp_lib_load_page( params );
		
		return false;
	});
});