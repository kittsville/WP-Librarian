function wp_lib_send_form( action, params ) {
	// Initialising AJAX object
	var data = {};
	
	// Initialising default page to load on success
	var successPage = '';
	
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
	});
}

// Fetches page, using given parameters
function wp_lib_load_page( page, data ) {
	
	// Main AJAX switch, decides where AJAX request should go
	switch ( page ) {
		case 'manage-item':
			data['action'] = 'wp_lib_manage_item';
		break;
		
		case 'manage-member':
			data['action'] = 'wp_lib_manage_member';
		break;
		
		case 'manage-fine':
			data['action'] = 'wp_lib_manage_fine';
		break;
		
		case 'manage-loan':
			data['action'] = 'wp_lib_manage_loan';
		break;
		
		case 'scheduling-page':
			data['action'] = 'wp_lib_scheduling_page';
		break;
		
		case 'return-past':
			data['action'] = 'wp_lib_return_past';
		break;
		
		case 'resolve-loan':
			data['action'] = 'wp_lib_resolution_page';
		break;
		
		case 'failed-deletion':
			data['action'] = 'wp_lib_deletion_failed';
		break;
		
		default:
			data['action'] = 'wp_lib_dashboard';
		break;
	}
	
	// Sends AJAX page request with given params, fills workspace div with response
	jQuery.post( ajaxurl, data, function(response) {
		jQuery( '#library-workspace' ).html( response );
	});
	
	// Runs after load function
	wp_lib_after_load();
}

// Run on each new dynamically loaded page
// Displays any new notifications and hooks any new forms/buttons
function wp_lib_after_load() {
	// Fetches and renders any new notifications
	wp_lib_display_notifications();
	
	// Adds listener for action performing buttons
	jQuery('#library-workspace').on('click', '.dash-action', function ( e ){
		// Fetches action to be performed
		var action = e.currentTarget.value;
		
		// Fetches form parameters that have been set
		var params = wp_lib_collect_form_params( '#library-form' );
		
		// Performs action
		wp_lib_send_form( action, params )
		
		// Prevents regular form submission
		return false;
	});
	
	// Adds listener for page loading buttons
	jQuery('#library-workspace').on('click', '.dash-page', function ( e ){
		// Fetches page to be loaded
		var page = e.currentTarget.value;
		
		// Fetches form parameters that have been set
		var params = wp_lib_collect_form_params( '#library-form' );

		// Loads page
		wp_lib_load_page( page, params );
		
		// Prevents regular form submission
		return false;
	});

}

jQuery( document ).ready(function($) {
	wp_lib_load_page( GetVars['dash_page'], GetVars );
});