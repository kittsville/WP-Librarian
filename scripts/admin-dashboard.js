function wp_lib_do_action( action, params ) {
	// Initialising AJAX object
	var data = {};
	
	// AJAX action switch, decides what action should be taken
	switch ( action ) {
		case 'loan':
			data.action = 'wp_lib_loan_item';
			data.item_id = params['item_id'];
			data.member_id = params['member_id'];
			data.loan_length = params['loan_length'];
		break;
		
		case 'schedule':
			data.action = 'wp_lib_schedule_loan';
			data.item_id = params['item_id'];
			data.member_id = params['member_id'];
			data.start_date = params['start_date'];
			data.end_date = params['end_date'];
		break;
		
		case 'return-item':
			data.action = 'wp_lib_return_item';
			data.item_id = params['item_id'];
			data.end_date = params['end_date'];
		break;
		
		case 'return-item-no-fine':
			data.action = 'wp_lib_return_item';
			data.item_id = params['item_id'];
			data.end_date = params['end_date'];
			data.no_fine = true;
		break;
		
		case 'fine-member':
			data.action = 'wp_lib_fine_member';
			data.item_id = params['item_id'];
			data.end_date = params['end_date'];
		break;
		
		case 'cancel-fine':
			data.action = 'wp_lib_modify_fine';
			data.fine_id = params['fine_id'];
			data.fine_action = params['cancel'];
		break;
		
		case 'delete-object-et-al':
			data.et_al = true;
		// Lack of break statement is deliberate
		
		case 'delete-object':
			data.action = 'wp_lib_delete_object';
			data.post_id = params['post_id'];
			data.deletion_confirmed = true;
		break;
		
		case 'clean-item':
			data.action = 'wp_lib_clean_item';
			data.item_id = params['item_id'];
		break;
		
		default:
			data.action = 'wp_lib_unknown_action';
		break;
	}
	
	// Submits action with all given form parameters
	jQuery.post( ajaxurl, data, function( response ) {
		// Parses response
		var success = wp_lib_parse_json( response );
		
		// If action completed successfully, loads Dashboard. Otherwise fetches notifications
		if ( success ) {
			wp_lib_load_page();
		} else {
			// Fetches and renders any new notifications
			wp_lib_display_notifications();
		}
	})
	.fail( function() {
		wp_lib_ajax_fail();
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
	if ( !ajaxData.hasOwnProperty( 'dash_page' ) ) {
		ajaxData.dash_page = 'dashboard';
	}
	
	// Sets action, which specifies the name of the hook it will have in WordPress ( wp_ajax_{$hook} )
	ajaxData.action = 'wp_lib_page';
	
	// Sends AJAX page request with given params, fills workspace div with response
	jQuery.post( ajaxurl, ajaxData )
	.done( function( response ) {
		// Parses response
		var ajaxResult = wp_lib_parse_json( response );
		
		// If page load failed, stops function execution, see wp_lib_parse_json
		if ( ajaxResult === 0 ) {
			return false;
		}
		
		// If server response was false, call error
		if ( ajaxResult == false ) {
			wp_lib_local_error( 'Server encounted error while loading page' );
		} else {
			// Clears existing params in preparation of new page
			wp_lib_vars.getParams = {};
			
			// Renders page using AJAX returned data
			wp_lib_render_page( ajaxResult );
			
			// Checks if new page is replacing existing dynamically loaded page
			// If it isn't then there's no need to add a new history entry
			if ( stateLoad != 'history' ) {
				// Deletes redundant parameter
				delete ajaxData.action;
				
				// Deletes dash page if it's redundant
				if ( ajaxData.dash_page == 'dashboard' ) {
					delete ajaxData.dash_page;
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
					history.replaceState( ajaxData, wp_lib_format_tab_title( ajaxResult.title ), currentPage );
				} else if ( stateLoad == 'dynamic' ) {
					// Changes URL to reflect new page (also creates browser history entry)
					history.pushState( ajaxData, wp_lib_format_tab_title( ajaxResult.title ), currentPage );
				} else {
					return;
				}
			}
		}
	})
	.fail( function() {
		wp_lib_ajax_fail();
	})
	.always( function() {
		// Runs after load function
		wp_lib_after_load();
	});
}

// Run on each new dynamically loaded page
// Displays any new notifications and hooks any new forms/buttons
function wp_lib_after_load() {
	// Fetches and renders any new notifications
	wp_lib_display_notifications();
	
	// Sets all all date inputs as jQuery datepickers
	jQuery('.datepicker').datepicker({
		dateFormat: 'yy-mm-dd',
		inline: true,
		showOtherMonths: true,
	});
	
	// Adds listener for action performing buttons
	jQuery('#wp-lib-workspace').on('click', '[name="dash_action"]', function ( e ){
		// Fetches action to be performed
		var action = e.currentTarget.value;
		
		// Fetches form parameters that have been set
		var params = wp_lib_collect_form_params( '#library-form' );
		
		// Performs action
		wp_lib_do_action( action, params );
		
		// Prevents regular form submission
		return false;
	});
	
	// Adds listener for page loading buttons
	jQuery('#wp-lib-workspace').on('click', '[name="dash_page"]', function ( e ){
		// Fetches form parameters that have been set
		var params = wp_lib_collect_form_params( '#library-form' );
		
		// Fetches page to be loaded
		params.dash_page = e.currentTarget.value;

		// Loads page
		wp_lib_load_page( params );
		
		// Prevents regular form submission
		return false;
	});
}

// Renders form from an array
function wp_lib_render_page( pageArray ) {
	
	// jQuery setup
	var $ = jQuery;

	// Changes page title
	jQuery( '#page-title' ).html( pageArray.pageTitle );
	
	// Changes title of browser tab
	document.title = wp_lib_format_tab_title( pageArray.title );
	
	if ( pageArray.scripts ) {
		$( pageArray.scripts ).each( function( i, scriptURL ) {
			jQuery.getScript( scriptURL )
			.fail( function( jqxhr, settings, exception ) {
				wp_lib_local_error( "Failed to load JavaScript needed for this page" );
			});
		});
	}
	
	// Clears and selects Library workspace in preparation of new page
	var libWorkspace = jQuery( '#wp-lib-workspace' ).empty();
	
	// If page has any non-form content, render each element to the Library workspace
	if ( pageArray.content ) {
		// Iterates through elements, rendering them
		jQuery( pageArray.content ).each( function( i, e ) {
			render_page_element( e, libWorkspace );
		});
	}
	
	// If page has any form content, render each element inside a form element
	if ( pageArray.form ) {
		// Creates and selects form element
		var libPage = $( '<form/>', {
			'id'	: 'library-form'
		} ).appendTo( libWorkspace );
		
		// Iterates through elements, rendering them
		jQuery( pageArray.form ).each( function( i, e ) {
			render_page_element( e, libPage );
		});
	}
	
	// Renders a page element to the specified parent
	// Function is recursive and will render a div's child nodes using itself
	function render_page_element( pageItem, theParent ) {
		// Sets up basic properties of object such as class/ID/name
		elementObject = wp_lib_init_object( pageItem );
		
		// If element has a label, creates
		if ( pageItem.label ) {
			// Creates label, appends to parent, then sets parent as label. This means the pageItem will be inside the label
			theParent = $('<label/>', {
				'for'	: pageItem.label
			}).appendTo( theParent );
		}
		
		// Performs actions on page element based on element type
		switch ( pageItem.type ) {
			case 'hidden':
				elementObject.type = pageItem.type;
				
				$('<input/>', elementObject ).appendTo( theParent );
				
				wp_lib_vars.getParams[elementObject.name] = elementObject.value;
			break;
			
			case 'button':
				// Sets button classes to hook admin WordPress styles
				elementObject.class += wp_lib_add_classes( [ 'button', 'button-primary', 'button-large' ] );
				
				// Sets button type to button to stop default behavior (form submission)
				elementObject.type = 'button';
				
				// Sets up button properties based on the button type
				switch ( pageItem.link ) {
					// Button to load a new Dash page
					case 'page':
						elementObject.name = 'dash_page';
					break;
					
					// Button to modify the library (e.g. loan an item)
					case 'action':
						elementObject.name = 'dash_action';
					break;
					
					// Button that links to an external URL
					case 'url':
						elementObject.href = pageItem.href;
						elementObject.onclick = wp_lib_vars.onClick;
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
						elementObject.href = wp_lib_vars.adminUrl + 'post.php?action=edit&post=' + postID;
						elementObject.onclick = wp_lib_vars.onClick;
					break;
					
					// If button has no known type, render error
					default:
						render_page_element({}, theParent);
						return;
					break;
				}
				
				// Creates button using buttonObject and appends it to page
				$('<button/>', elementObject ).appendTo( theParent );
			break;
			
			case 'dash-button':
				// Merges dash button's classes with default dash button classes
				elementObject.class += wp_lib_add_classes( [ 'dashboard-button' ] );
				
				// Sets button's click behaviour based on its link type
				switch ( pageItem.link ) {
					case 'dash-page':
						elementObject.name = 'dash_page';
					break;
					
					case 'post-type':
						elementObject.href = wp_lib_vars.adminUrl + 'edit.php?post_type=' + pageItem.pType;
						elementObject.onclick = wp_lib_vars.onClick;
					break;
					
					case 'admin-url':
						elementObject.href = wp_lib_vars.adminUrl + pageItem.url;
						elementObject.onclick = wp_lib_vars.onClick;
					break;
					
					case 'url':
						elementObject.href = pageItem.url;
						elementObject.onclick = wp_lib_vars.onClick;
					break;
				}
				
				// Creates button and appends to document, setting parent to button
				var theParent = $('<button/>', elementObject ).appendTo( theParent );
				
				// Creates dash icon wrapper
				var localParent = $('<div/>', {
					'class'	: 'dash-button-top'
				}).appendTo( theParent );
				
				// Creates dash icon
				$('<img/>', {
					'class'	: 'dashboard-icon',
					'src'	: wp_lib_vars.pluginsUrl + '/images/dash-icons/' + pageItem.icon + '.png',
					'alt'	: pageItem.title + ' Icon',
				}).appendTo( localParent );
				
				// Creates bottom half of button: the label
				$('<div/>', {
					'class'	: 'dash-button-bottom',
					'html'	: pageItem.title
				}).appendTo( theParent );
			break;
			
			case 'paras':
				// Renders paragraphs from array to separate p elements
				$( pageItem.content ).each( function ( i, e ) {
					$('<p/>', {
						'html'	: e
					}).appendTo( theParent );
				});
			break;
			
			case 'date':
				elementObject.type = pageItem.type;
				elementObject.class += wp_lib_add_classes( [ 'datepicker', 'll-skin-melon' ] );
				$('<input/>', elementObject ).appendTo( theParent );
			break;
			
			case 'select':
				// Renders select element and adds it to the page
				var select = $('<select/>', elementObject ).appendTo( theParent );
				
				// Iterates through select element's options, adding them to the select element
				$( pageItem.options ).each( function( i, selectOption ) {
					$('<option/>', {
						'value'	: selectOption.value,
						'html'	: selectOption.html,
						'class'	: pageItem.optionClass
					}).appendTo( select );
				});
			break;
			
			case 'header':
				$('<h' + pageItem.size + '/>', elementObject ).appendTo( theParent );
			break;
			
			case 'text':
				// Sets object's type
				elementObject.type = 'text';
				
				// Sets property if property exists
				if ( pageItem.autofocus ) {
					elementObject.autofocus = 'autofocus';
				}
				
				// Creates text input element
				$('<input/>', elementObject ).appendTo( theParent );
			break;
			
			case 'div':
				// Creates div, selects and appends to parent
				var pageDiv = $('<div/>', elementObject ).appendTo( theParent );
				
				// Iterates through div's inner elements, appending them to itself
				$( pageItem.inner ).each( function( i, e ) {
					render_page_element( e, pageDiv );
				});
			break;
			
			case 'strong':
				$('<strong/>', elementObject ).appendTo( theParent );
			break;
			
			case 'metabox':
				// Adds default meta box classes
				elementObject.class += wp_lib_add_classes( [ 'lib-metabox' ] );
			
				// Creates wrapper for meta box, attached to DOM, and sets as parent
				var theParent = $('<dl/>', elementObject ).appendTo( theParent );
				
				// Sets title of meta box
				$('<strong/>', {
					'html'	: pageItem.title
				}).appendTo( theParent );
				
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
					
					var localParent = $('<div/>', {
						'class'	: 'meta-row'
					}).appendTo( theParent );
					
					// Renders meta field's name
					$('<dt/>', {
						'html'	: fieldName + ':'
					}).appendTo( localParent );
					
					// Renders meta field's value
					$('<dd/>', {
						'html'	: fieldData
					}).appendTo( localParent );
				});
			break;
			
			case 'dtable':
				// If dev is an idiot and forgets to give the table a class, assigns one randomly
				if (typeof( pageItem.id ) === 'undefined' ) {
					elementObject.id = 'dynamic-table-' + Math.floor((Math.random() * 100) + 1);
				}
				
				// Wraps table in div for styling purposes
				var theParent = $('<div/>', {} ).appendTo( theParent );
				
				// Creates/selects base element
				var theParent = $('<table/>', elementObject ).appendTo( theParent );
				
				// Adds head to table
				var tableHead = $('<thead/>', {} ).appendTo( theParent );
				
				// Iterates through given table columns, adding them to the table head
				$( pageItem.headers ).each( function( i, header ) {
					$('<th/>', {
						'html'	: header
					} ).appendTo( tableHead );
				});
				
				// Renders table body, to be dynamically populated
				$('<tbody/>', {} ).appendTo( theParent );
				
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
				
				// Fills table with formatted data
				$( '#' + elementObject.id ).dynatable({ 
					dataset: {
						records: tableRecords
					}
				});
			break;
			
			default:
				$('<strong/>', {
					'html'	: 'UNKNOWN ELEMENT TYPE<br/>',
					'style'	: 'color:red;'
				}).appendTo( theParent );
			break;
		}
	}

}

function wp_lib_format_tab_title( title ) {
	return title + ' ‹ ' + wp_lib_vars.siteName;
}

// Loads page content on page load. Only used if page is visited from a non-Dashboard page
jQuery( document ).ready(function($) {
	$.dynatableSetup({
		table: {
			headRowSelector: 'thead'
		},
		features: {
			pushState: false
		}
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
});