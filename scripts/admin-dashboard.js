// An AJAX call to the Library API, to retrieve part of a page or lookup data
function wp_lib_api_call( params, postCallFunction ) {
	// Adds WordPress hook for WP_Lib_API to AJAX request
	params.action = 'wp_lib_api';
	
	// Sends AJAX request
	wp_lib_send_ajax( params, false, postCallFunction );
}

// Performs Dash action, modifying the Library e.g. Marking an item as returned
function wp_lib_do_action( dashAction, params ) {
	// Initialising AJAX object
	var data = {
		action		: 'wp_lib_action',
		dash_action	: dashAction
	};
	
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
	
	wp_lib_send_ajax( data, false, function( serverResponse ) {
		if ( serverResponse[0] === 4 ) {
			// If server responded indicating action was successful, return to Dashboard home
			wp_lib_load_page();
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
	wp_lib_send_ajax( ajaxData, false, function( serverResponse ) {
		// Performs actions based on how server responded
		switch( serverResponse[0] ) {
			// If server responded to indicate WP-Librarian failure
			case 3:
				wp_lib_local_error( 'Server encounted error while loading page' );
			break;
			
			// If server responded to indicate success
			case 4:
				// Renders page using AJAX returned data
				wp_lib_render_page( serverResponse[1] );
				
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
				
				// Fetches and renders any new notifications
				wp_lib_display_notifications();
			break;
			
			default:
				// On misc AJAX failure (preventing page load), return false
				return false;
			break;
		}
	});
}

// Renders form from an array
function wp_lib_render_page( pageArray ) {
	
	// jQuery setup
	var $ = jQuery;
	
	// Clears existing params in preparation of new page
	wp_lib_vars.getParams = {};

	// Changes page title
	$( '#page-title' ).html( pageArray.pageTitle );
	
	// Changes title of browser tab
	document.title = wp_lib_format_tab_title( pageArray.title );
	
	// Clears and selects Library workspace in preparation of new page
	var libWorkspace = $( '#wp-lib-workspace' ).empty();
	
	// If page has a header, renders
	if ( pageArray.content.hasOwnProperty('header') ) {
		// Creates wrapper for all header elements
		var theParent = $('<div/>',{
			id	: 'wp-lib-header'
		}).appendTo(libWorkspace);
		
		// Iterates through header elements, rendering them to the header wrapper
		$( pageArray.content.header ).each( function( i, e ) {
			wp_lib_render_page_element( e, theParent );
		});
	}
	
	// If page has any form content, render each element inside a form element
	if ( pageArray.content.hasOwnProperty('form') ) {
		// Creates form element for all form elements
		var libPage = $( '<form/>', {
			id		: 'library-form',
			onsubmit: 'return false;' // Prevents default form submission
		} );
		
		// Creates wrapper for all form elements
		$('<div/>',{
			id		: 'wp-lib-form',
			html	: libPage
		}).appendTo(libWorkspace);
		
		// Iterates through form elements, rendering them to the form
		$( pageArray.content.form ).each( function( i, e ) {
			wp_lib_render_page_element( e, libPage );
		});
	}
	
	// If page has a table, render table
	if ( pageArray.content.hasOwnProperty('table') ) {
		// Creates wrapper for all tables
		var tableWrap = $('<div/>',{
			'class'	: 'wp-lib-tables'
		}).appendTo(libWorkspace);
		
		// Iterates over all given tables, rendering them inside the wrapper
		$( pageArray.content.table ).each( function( i, e ) {
			wp_lib_render_page_element( e, tableWrap );
		});
	}
	
	// If any scripts are required by the page, loads them
	if ( pageArray.hasOwnProperty('scripts') && pageArray.scripts instanceof Array ) {
		$( pageArray.scripts ).each( function( i, scriptURL ) {
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
			var theElement = $('<button/>', elementObject ).addClass( 'button button-primary button-large' );
			
			// Sets button type to button to stop default behaviour (form submission)
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
						href	: pageItem.href,
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
		break;
		
		// Dash buttons are the large buttons on the Dashboard homepage
		case 'dash-button':
			// Creates element and adds default dash button class
			theElement = $('<button/>',elementObject).addClass('dashboard-button');
			
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
			
			// Adds button innards: icon and button name
			theElement.html([
				// Icon Wrapper
				$('<div/>', {
					'class'	: 'dash-button-top',
					html	:
						// Icon
						$('<img/>', {
							'class'	: 'dashboard-icon',
							src		: wp_lib_vars.pluginsUrl + '/images/dash-icons/' + pageItem.icon + '.png',
							alt		: pageItem.title + ' Icon'
						})
				}),
				// Button text wrapper
				$('<div/>', {
					'class'	: 'dash-button-bottom',
					html	:
						// Button text
						$('<span/>',{
							'class'	: 'dash-button-text',
							html	: pageItem.title
						})
				})
			]);
		break;
		
		// Paras groups of at least one paragraph, wrapped in a div
		case 'paras':
			// Initialises paragraph DOM element array
			var paraOutput = [];
			
			// Iterates over paragraphs, creating DOM elements and adding them to the array
			$( pageItem.content ).each( function ( i, e ) {
				paraOutput.push($('<p/>', {
					html	: e
				}));
			});
			
			// Creates parent div that contains the paragraph elements
			var theElement = $('<div/>',{
				'class'	: 'dash-paras',
				'html'	: paraOutput
			});
		break;
		
		// Date elements are inputs with jQuery datepickers
		case 'date':
			// Creates date input element, adds default datepicker styles then turns into a jQuery datepicker
			var theElement = $('<input/>', elementObject )
			.attr({ type	: pageItem.type })
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
				
			if(event.keyCode == 13) { // GGGG
				theElement.siblings('button.button').click();
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
			// Initialises metabox
			var metaBox = $('<table/>',{
				'class'	: 'lib-metabox',
			});
			
			// Iterates through meta fields, rendering them to the meta box
			$( pageItem.fields ).each( function( i, e ) {
				// Fetches field's name and contents
				var fieldName = e[0];
				var fieldData = e[1];
				
				// If meta field is array of tax terms, format into hyperlinks
				if ( fieldData instanceof Array ) {
					// If array has more than one tax term, pluralises tax term name
					if ( fieldData.length > 1 ) {
						fieldName += 's';
					}
				
					// Initialises Output
					var taxTermOutput = '';
					
					// Iterates through tax terms, creating hyperlink and adding to output
					$( fieldData ).each( function( i, taxData ) {
						// If term is higher than the 1st term, adds separator before term
						if ( i > 0 ) {
							taxTermOutput += ', ';
						}
						taxTermOutput += '<a href="' + taxData[1] + '">' + taxData[0] + '</a>';
					});
					
					// Sets output variable to actual output
					fieldData = taxTermOutput;
				}
				
				// Renders meta row inside div and adds to 
				metaBox.append( $('<tr/>', {
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
			
			// Sets up meta box wrapper's internal elements
			elementObject.html = [
				$('<strong/>',{
					html	: pageItem.title
				}),
				metaBox
			];
			
			// Creates meta box wrapper
			var theElement = $('<div/>',elementObject).addClass('lib-metabox');
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
					if ( columnData instanceof Array ) {
						columnData = '<a href="' + columnData[1] + '">' + columnData[0] + '</a>'; // Dynatable, Y U NO ACCEPT DOM OBJECTS?
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
					perPageDefault: 15,
					perPageOptions: [15, 30, 60, 180]
				},
				params: {
					records: pageItem.labels.records
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
				var listItemElements = [];
				
				// If item has a cover image, use. Otherwise use placeholder div that will be filled with book icon
				if ( record.cover != false ) {
					listItemElements.push($('<div/>', {
							'class'	: 'item-thumbnail',
							'html'	:
								$('<div/>',{
									'class'	: 'item-thumbnail-centrefix',
									'html'	:
										$('<img/>',{
											'src'	: record.cover[0]
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
				var localParent = $('<li/>', {
					'class'	: 'single-item',
					'html'	: listItemElements
				});
				
				// Adds class to colour item red if it is currently late
				if ( record.late == true ) {
					localParent.addClass( 'late-item' );
				}
				
				// Renders manage item button to invisible form containing item's ID
				wp_lib_render_page_element( {
						type	: 'button',
						link	: 'page',
						value	: 'manage-item',
						html	: 'Manage',
						classes	: 'item-manage'
					},
					$('<form/>',{ // GGGG
						html	:
							$('<input/>',{
								type	: 'hidden',
								name	: 'item_id',
								value	: record.item_id
							})
					}).appendTo( localParent )
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
				return localParent.prop('outerHTML');
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
				}
			});
			
			// Skips adding element to parent as this was performed earlier so that Dynatable would work properly
			return;
		break;
		
		default:
			var theElement = $('<strong/>', {
				'html'	: 'UNKNOWN ELEMENT TYPE<br/>',
				'style'	: 'color:red;'
			});
		break;
	}
	
	// Appends new DOM element to given parent element
	return theElement.appendTo( theParent );
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
	jQuery('#wp-lib-workspace').on('click', '[name="dash_action"]', function ( e ){
		console.log( e );
		// Fetches action to be performed
		var action = e.currentTarget.value;
		
		// Fetches form parameters that have been set
		var params = wp_lib_collect_form_params(e.target);
		
		// Performs action
		wp_lib_do_action( action, params );
	});
	
	// Adds listener for page loading buttons
	jQuery('#wp-lib-workspace').on('click', '[name="dash_page"]', function ( e ){
		// Fetches form parameters that have been set
		var params = wp_lib_collect_form_params(e.target);
		
		// Fetches page to be loaded
		params.dash_page = e.currentTarget.value;
		
		// Deletes nonce as it is only needed for dash actions
		delete params.wp_lib_ajax_nonce;

		// Loads page
		wp_lib_load_page( params );
	});
});