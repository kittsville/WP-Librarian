function wp_lib_send_form( action, params ) {
	// Initialising AJAX object
	var data = {};
	
	// Initialising default page to load on success
	var successPage = '';
	
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
		
		case 'pay-fine':
			data.action = 'wp_lib_modify_fine';
			data.fine_id = params['fine_id'];
			data.fine_action = params['pay'];
		break;
		
		case 'revert-fine':
			data.action = 'wp_lib_modify_fine';
			data.fine_id = params['fine_id'];
			data.fine_action = params['revert'];
		break;
		
		case 'cancel-fine':
			data.action = 'wp_lib_modify_fine';
			data.fine_id = params['fine_id'];
			data.fine_action = params['cancel'];
		break;
		
		case 'clean-item':
			data.action = 'wp_lib_clean_item';
			data.item_id = params['item_id'];
		break;
		
		default:
			data.action = 'wp_lib_unknown_action';
			data.given_action = action;
		break;
	}
	
	// Submits action with all given form parameters
	jQuery.post( ajaxurl, data, function( response ) {
		// Parses response
		var success = JSON.parse( response );
		
		// If action completed successfully, redirects to dashboard
		if ( success ) {
			wp_lib_load_page( successPage, {} );
		} else {
			// Otherwise load notifications to display explanatory errors
			wp_lib_display_notifications();
		}
	})
	.fail( function() {
		wp_lib_ajax_fail();
	});
}

// Fetches page, using given parameters
function wp_lib_load_page( page, ajaxData ) {

	// AJAX page switch, decides which page should be loaded
	switch ( page ) {
		case 'manage-item':
			ajaxData['action'] = 'wp_lib_manage_item';
		break;
		
		case 'manage-member':
			ajaxData['action'] = 'wp_lib_manage_member';
		break;
		
		case 'manage-fine':
			ajaxData['action'] = 'wp_lib_manage_fine';
		break;
		
		case 'manage-loan':
			ajaxData['action'] = 'wp_lib_manage_loan';
		break;
		
		case 'scan-item':
			ajaxData['action'] = 'wp_lib_scan_item';
		break;
		
		case 'scheduling-page':
			ajaxData['action'] = 'wp_lib_scheduling_page';
		break;
		
		case 'return-past':
			ajaxData['action'] = 'wp_lib_return_past';
		break;
		
		case 'resolve-loan':
			ajaxData['action'] = 'wp_lib_resolution_page';
		break;
		
		case 'failed-deletion':
			ajaxData['action'] = 'wp_lib_deletion_failed';
		break;
		
		default:
			ajaxData['action'] = 'wp_lib_dashboard';
		break;
	}
	
	// Sends AJAX page request with given params, fills workspace div with response
	jQuery.post( ajaxurl, ajaxData, function( response ) {
		jQuery( '#library-workspace' ).html( response );
		
		// Deletes now redunant parameter
		delete ajaxData['action'];

		// Serializes page parameters
		var urlString = jQuery.param( ajaxData );
		
		// Creates current page title
		var currentPage = wp_lib_vars.dashurl + '&' + urlString;
		
		// Changes URL to reflect new page (also creates browser history entry)
		history.pushState( ajaxData, 'Dashboard Title' + '&bull' + wp_lib_vars.sitename, currentPage );
		
		// Sets trigger for the back button being used
		window.onpopstate = function( event ) {
			wp_lib_load_page( event.state.dash_page, event.state );
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
	jQuery('#library-workspace').on('click', '.dash-action', function ( e ){
		// Fetches action to be performed
		var action = e.currentTarget.value;
		
		// Fetches form parameters that have been set
		var params = wp_lib_collect_form_params( '#library-form' );
		
		// Performs action
		wp_lib_send_form( action, params );
		
		// Prevents regular form submission
		return false;
	});
	
	// Adds listener for page loading buttons
	jQuery('#library-workspace').on('click', '.dash-page', function ( e ){
		// Fetches form parameters that have been set
		var params = wp_lib_collect_form_params( '#library-form' );
		
		// Fetches page to be loaded
		params.dash_page = e.currentTarget.value;

		// Loads page
		wp_lib_load_page( params.dash_page, params );
		
		// Prevents regular form submission
		return false;
	});

}

// Loads page content on first external visit
jQuery( document ).ready(function($) {
	var GetVars = wp_lib_vars.getparams;
	
	// Removes default GET params as they are no longer needed
	delete GetVars["post_type"];
	delete GetVars["page"];
	
	// Loads relevant page
	wp_lib_load_page( GetVars['dash_page'], GetVars, true );
});