// Psudo-constant
var WP_LIB_NONCE = 'wp_lib_ajax_nonce';

wp_lib_vars.onClick = 'wp_lib_click_button(this)';

jQuery(function($){
	// Selects the notification holder
	window.notificationHolder = jQuery('#wp-lib-notifications');
});

// Collects of a submitted form's parameters, removes the blank ones, then returns the result as an object
function wp_lib_collect_form_params(clickedElement) {
	
	// Fetches all form elements and creates an array of objects
	var objects = jQuery(clickedElement).closest('form').serializeArray();
	
	// Initialises results object
	var result = {};
	
	// Iterates through each object, if the value is set, it is added to the results
	objects.forEach(function(object) {
		if (object.hasOwnProperty('name') && object.hasOwnProperty('value') && object.value != '') {
			result[object.name] = object.value;
		}
	});
	return result;
}

// Adds notification to client-side buffer
function wp_lib_add_notification(array) {
	if (typeof window.wp_lib_notification_buffer === 'undefined') {
		window.wp_lib_notification_buffer = [];
	}
	window.wp_lib_notification_buffer.push(array);
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
	wp_lib_render_notifications(wp_lib_fetch_local_notifications());
}

// Given an array of notifications, renders them to the notification holder
function wp_lib_render_notifications(notificationsArray) {
	
	// Iterates through notifications, rendering them to the notification holder
	notificationsArray.forEach(function(notificationText) {
		wp_lib_render_notification(notificationText);
	});
}

// Renders a single notification to a specified notification holder
function wp_lib_render_notification(notificationText) {
	// Formats notification inside div and gives notification ID (to keep track of it)
	var formattedNotification = wp_lib_format_notification(notificationText);
	
	// Notification is an error and browser supports the browser console, logs to console as well
	if (notificationText[0] != 0 && typeof console !== 'undefined') {
		console.log(formattedNotification.consoleMessage);
	}

	// Adds notification to the notification holder
	notificationHolder.append(formattedNotification.element).hide().fadeIn(500);
	
	// Calculates how long to display the notification, based on its length
	var displayTime = Math.min(Math.max(notificationText[1].length * 150, 3500), 9000);
	
	// Sets notification to fade away after 5 seconds then get deleted
	setTimeout(function(){
		wp_lib_hide_notification(formattedNotification.element);
	}, displayTime);
}

// Renders client-side notification straight to the browser, no buffering
function wp_lib_local_notification(text) {
	wp_lib_render_notification([ 0, text ]);
}

// Renders client-side error straight to the browser, no buffering
function wp_lib_local_error(text) {
	wp_lib_render_notification([ 1, text ]);
}

// Formats a notification with appropriate tags to utilise WordPress and Plugin CSS
function wp_lib_format_notification(notification) {
	var classes, message, consoleMessage, uID = Math.random().toString().substring(2, 8);
	
	// If notification has no error code (defaulted to 0)
	if (notification[0] == 0) {
		// Uses notification classes, which displays a green flared box
		classes = 'wp-lib-notification updated';
		message = notification[1];
	} else if (notification[0] == 1) {
		// Uses error classes, which displays a red flared box
		classes = 'wp-lib-error error';
		consoleMessage = 'WP-Librarian Error: ' + notification[1];
		message = '<strong style="color: red;">' + consoleMessage + '</strong>';
	} else {
		// Uses error classes, which displays a red flared box
		classes = 'wp-lib-error error';
		consoleMessage = 'WP-Librarian Error ' + notification[0] + ': ' + notification[1];
		message = '<strong style="color: red;">' + consoleMessage + '</strong>';
	}
	
	// Returns HTML formatted notification
	return {
		'element':			jQuery('<div id="' + uID + '" onclick="wp_lib_hide_notification(this)" class="' + classes + '"><p>' + message + '</p></div>'),
		'consoleMessage':	consoleMessage
	};
}

// Hides then deletes notification, called when notification is clicked or some time after it appeared
function wp_lib_hide_notification(notification) {
	// Removes notification, in style!
	notification.fadeOut("fast");
	
	// Deletes notification
	notification.remove();
}

// JavaScript to allow buttons to act as hyperlinks
function wp_lib_click_button(e) {
	// Redirects page to clicked button's href attribute
	location.href = jQuery(e).attr('href');
}

// Attempts to parse JSON and catches any failures
function wp_lib_parse_json(rawJSON) {
	try {
		var parsedJSON = JSON.parse(rawJSON);
	}
	catch(e) {
		wp_lib_local_error("Server returned invalid response");
		
		// If debugging is on, displays un-parse-able response
		if (wp_lib_vars.debugMode === '1') {
			// Debugging - Renders un-parse-able returned data to workspace
			jQuery('<div/>', {
				'style'	: 'background:grey;',
				'html'	: '<strong style="color:red;">Server Response</strong></br/>' + rawJSON
			}).appendTo('#wp-lib-workspace');
		}
		
		parsedJSON = 0;
	}
	return parsedJSON;
}

/* Performs AJAX query to WordPress AJAX url:
 * 0 - Failed to connect
 * 1 - Connected, WP-Librarian never hooked
 * 2 - Connected, server returned invalid JSON
 * 3 - Connected, server returned valid response of boolean false
 * 4 - Connected, server returned valid response of boolean true
 * Followed by server's raw response
 * Passes result to postCallFunction, if one was specified
 */
function wp_lib_send_ajax(ajaxData, postCallFunction) {
	// Queries server with AJAX data
	var outputBuffer = [];
	
	jQuery.post(ajaxurl, ajaxData)
	.done(function(response) {
		// Checks if WP_AJAX hook does not exist
		if (response === '0') {
			wp_lib_local_error("WordPress rejected AJAX request. This is likely a permissions issue.");
			
			// Sets output status
			outputBuffer[0] = 1;
			return;
		} else {
			// Attempts to parse server response
			var ajaxResult = wp_lib_parse_json(response);
			
			// If server output could not be parsed as JSON
			if (ajaxResult === 0 || !(ajaxResult instanceof Array)) {
				// Sets output status
				outputBuffer[0] = 2;
			} else {
				if (ajaxResult[0] === true) {
					// Sets output status and includes server's actual response
					outputBuffer = [ 4, ajaxResult ];
				} else {
					// Sets output status and includes server's actual response
					outputBuffer = [ 3, ajaxResult ];
				}
			}
		}
	})
	.fail(function(data, a, message) {
		if (data.status === 0) {
			wp_lib_local_error('Unable to connect to ' + wp_lib_vars.siteName + '. Check your internet connection.');
		} else {
			wp_lib_local_error('HTTP Error ' + data.status + ': ' + message);
		}
		
		if (wp_lib_vars.debugMode) {
			if (data.responseText != '') {
				console.log(jQuery(data.responseText).text());
			}
		} else {
			console.log('Please temporarily enable debugging mode to investigate the error https://github.com/kittsville/WP-Librarian/wiki/wp_lib_debug_mode');
			console.log("Unless it's an HTTP error that has nothing to do with WP-Librarian");
		}
		
		// Sets output status
		outputBuffer[0] = 0;
	})
	.always(function() {
		// Regardless of AJAX success/failure, passes output buffer to post AJAX function, if send_ajax was called with one defined
		if (typeof postCallFunction === 'function') {
			postCallFunction(outputBuffer);
		}
		
		// Merges locally buffered notifications with any existing server notifications then displays all notifications
		var notifications = wp_lib_fetch_local_notifications();
		
		if (outputBuffer[0] >= 3) {
			notifications = notifications.concat(outputBuffer[1][1]);
		}
		
		wp_lib_render_notifications(notifications);
	});
}

function wp_lib_init_object(pageItem) {
	// Initialises object properties
	elementObject = {};
	
	// Iterates over basic properties, adding them to the object if they exist
	jQuery([ 'id', 'name', 'value', 'html', 'title', 'href' ]).each(function(i, e) {
		if (e in pageItem) {
			elementObject[e] = pageItem[e];
		}
	});
	
	return elementObject;
}
