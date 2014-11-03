// Counter used as part of simple UID for notifications
var notificationCount = 0;
wp_lib_vars.onClick = 'wp_lib_click_button( this )';

jQuery(function($){
	// Selects the notification holder
	window.notificationHolder = jQuery( '#wp-lib-notifications' );
});

// Collects of a submitted form's parameters, removes the blank ones, then returns the result as an object
function wp_lib_collect_form_params() {

	// Fetches all form elements and creates an array of objects
	var objects = jQuery( '#library-form' ).serializeArray();
	console.log( objects );
	// Initialises results object
	var result = {};
	
	// Iterates through each object, if the value is set, it is added to the results
	objects.forEach(function(object) {
		if ( object.hasOwnProperty('name') && object.hasOwnProperty('value') && object.value != '' ) {
			result[object.name] = object.value;
		}
	});
	return result;
}

// General purpose AJAX failed message, probably a network issue, unless the server is hosted off some terrible cloud server
function wp_lib_ajax_fail() {
	console.log( "WP-Librarian: AJAX Request failed" );
	wp_lib_local_error( "Unable to complete request. The website might be down or you may be having connection issues." );
}

// Adds notification to client-side buffer
function wp_lib_add_notification( array ) {
	if (typeof window.wp_lib_notification_buffer === 'undefined') {
		window.wp_lib_notification_buffer = [];
	}
	window.wp_lib_notification_buffer.push( array );
}

// Fetches and displays any notifications waiting in the local or server buffer
function wp_lib_display_notifications() {
	// Initialises notifications array by fetching any client-side notifications
	var notifications = wp_lib_fetch_local_notifications();
	
	// Checks server for notifications
	jQuery.post( ajaxurl, { 'action' : 'wp_lib_fetch_notifications' } )
	.done( function( response ) {
		// Parses response
		var serverNotifications = JSON.parse( response );
		
		// If there are any server-side notifications, merge them into client-side notifications
		if ( jQuery.isArray( serverNotifications ) ) {
			notifications = notifications.concat( serverNotifications );
		}
	})
	.fail( function() {
		wp_lib_ajax_fail();
	})
	.always( function() {
		// Renders all collected local and server notifications
		wp_lib_render_notifications( notifications );
	});
}

// Fetches and returns all client-side buffered notifications
function wp_lib_fetch_local_notifications() {
	// If any notifications are buffered, fetch them then clear the buffer
	if (typeof window.wp_lib_notification_buffer != 'undefined') {
		var notifications = window.wp_lib_notification_buffer;
		delete window.wp_lib_notification_buffer;
		return notifications;
	}
	// If none were found, return empty array
	return [];
}

// Fetches and displays local notifications only
function wp_lib_display_local_notifications() {
	wp_lib_render_notifications( wp_lib_fetch_local_notifications() );
}

// Given an array of notifications, renders them to the notification holder
function wp_lib_render_notifications( notificationsArray ) {
	
	// Iterates through notifications, rendering them to the notification holder
	notificationsArray.forEach( function( notificationText ) {
		wp_lib_render_notification( notificationText );
	});
}

// Renders a single notification to a specified notification holder
function wp_lib_render_notification( notificationText ) {
	// Formats notification inside div and gives notification ID (to keep track of it)
	var result = wp_lib_format_notification( notificationText );

	// Adds notification to the notification holder
	notificationHolder.append( result[1] ).hide().fadeIn( 500 );
	
	// Selects the notification using its ID
	var notification = jQuery( '.' + result[0] );
	
	// Sets notification to fade away after 5 seconds then get deleted
	setTimeout(function(){
		wp_lib_hide_notification( notification );
	}, 7000 );
}

// Renders client-side notification straight to the browser, no buffering
function wp_lib_local_notification( text ) {
	wp_lib_render_notification( [ 0, text ] );
}

// Renders client-side error straight to the browser, no buffering
function wp_lib_local_error( text ) {
	wp_lib_render_notification( [ 1, text ] );
}

// Formats a notification with appropriate tags to utilise WordPress and Plugin CSS
function wp_lib_format_notification( notification ) {
	// Creates unique ID for notification (to keep track of it)
	var uID = 'wp_lib_nid_' + notificationCount++;
	
	// Initialises variables
	var classes = uID + ' ';
	var message = '';
	var onClick = " onclick='wp_lib_hide_notification(this)";
	
	// If notification has no error code (defaulted to 0 )
	if ( notification[0] == 0 ) {
		// Uses notification classes, which displays a green flared box
		classes += 'wp-lib-notification updated';
		message = notification[1];
	} else if ( notification[0] == 1 ) {
		// Uses error classes, which displays a red flared box
		classes += 'wp-lib-error error';
		message = "<strong style='color: red;'>WP-Librarian Error: " + notification[1] + "</strong>";
	} else {
		// Uses error classes, which displays a red flared box
		classes += 'wp-lib-error error';
		message = "<strong style='color: red;'>WP-Librarian Error " + notification[0] + ": " + notification[1] + "</strong>";
	}
	
	// Returns HTML formatted notification
	return [ uID, "<div onclick='wp_lib_hide_notification(this)' class='" + classes + "'><p>" + message + "</p></div>" ];
}

// Hides then deletes notification, called when notification is clicked or some time after it appeared
function wp_lib_hide_notification( element ) {
	// Selects notification
	var notification = jQuery( element );
	
	// Removes notification, in style!
	notification.fadeOut("fast");
	
	// Deletes notification
	notification.remove();
}

// JavaScript to allow buttons to act as hyperlinks
function wp_lib_click_button( e ) {
	// Redirects page to clicked button's href attribute
	location.href = jQuery( e ).attr('href');
}

// Attempts to parse JSON and catches any failures
function wp_lib_parse_json( rawJSON ) {
	try {
		var parsedJSON = JSON.parse( rawJSON );
	}
	catch(e) {
		console.log( "WP-Librarian: Server returned invalid JSON" );
		wp_lib_local_error( "Unable to complete request. Server returned invalid response" );
		
		// Debugging - Renders un-parseable returned data to workspace
		jQuery('<div/>', {
			'style'	: 'background:grey;',
			'html'	: '<strong style="color:red;">Server Response</strong></br/>' + rawJSON
		}).appendTo( '#wp-lib-workspace' );
		
		parsedJSON = 0;
	}
	return parsedJSON;
}

// Concatenates array into string with spaces, can handle string if needed
function wp_lib_add_classes( newClasses ) {
	if ( newClasses instanceof Array ){
		return ' ' + newClasses.join(' ');
	}
	else if ( typeof newClasses === "string" ) {
		return ' ' + newClasses;
	} else {
		return '';
	}
}

// Configures basic DOM object properties
function wp_lib_init_object( pageItem ) {
	var $ = jQuery;
	
	// Initialises object that will contain element's arguments (ID, Value, Class, etc. )
	var elementObject = {
		'class'	: ''
	};

	// Iterates through common html properties and sets them if they exist
	$( [ 'id', 'name', 'value', 'html' ] ).each( function( i, e ) {
		if ( pageItem[e] ) {
			elementObject[e] = pageItem[e];
		}
	});

	// If page element has classes, sets
	if ( pageItem.classes ) {
		elementObject.class += wp_lib_add_classes( pageItem.classes );
	}
	
	return elementObject;
}