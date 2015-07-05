var wp_lib_scripts = {};

wp_lib_vars.onClick = 'wp_lib_click_button(this)';

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

// Displays notification on the Dashboard
function wp_lib_notification(message) {
	wp_lib_scripts.Notifications.displayNotification([0, message]);
}

// Displays error on the Dashboard
function wp_lib_error(message) {
	wp_lib_scripts.Notifications.displayNotification([1, message]);
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
		
		if (outputBuffer[0] >= 3) {
			wp_lib_scripts.Notifications.displayNotifications(outputBuffer[1][1]);
		}
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

jQuery(function($) {
	wp_lib_scripts.Notifications = {
		// Settings
		s: {
			notificationsWrapper: $('#wp-lib-notifications'),
		},
		
		init: function() {
			this.bindUIActions();
		},
		
		bindUIActions: function() {
			wp_lib_scripts.Notifications.s.notificationsWrapper.on('click', 'div', function(event) {
				wp_lib_scripts.Notifications.hideNotification(jQuery(event.currentTarget));
			});
		},
		
		displayNotification: function(notification) {
			var classes, message, consoleMessage, notificationElement, uID = Math.random().toString().substring(2, 8);
			
			// Non-error notification. Displays with green highlights
			if (notification[0] == 0) {
				classes = 'wp-lib-notification updated';
				message = notification[1];
			}
			// Error notification with no error code. Displays with red highlights
			else if (notification[0] == 1) {
				// Uses error classes, which displays a red flared box
				classes = 'wp-lib-error error';
				consoleMessage = 'WP-Librarian Error: ' + notification[1];
				message = '<strong style="color: red;">' + consoleMessage + '</strong>';
			
			// Error notification with an error code. Displays with red highlights
			} else {
				// Uses error classes, which displays a red flared box
				classes = 'wp-lib-error error';
				consoleMessage = 'WP-Librarian Error ' + notification[0] + ': ' + notification[1];
				message = '<strong style="color: red;">' + consoleMessage + '</strong>';
			}
			
			// Allows selection of notification by error ID
			classes += ' notification-' + notification[0];
			
			notificationElement = $('<div id="' + uID + '" class="' + classes + '"><p>' + message + '</p></div>');
			
			if (notification[0] != 0 && typeof console !== 'undefined') {
				console.log(consoleMessage);
			}
			
			// Calculates how long to display the notification, based on its length
			var displayTime = Math.min(Math.max(notification[1].length * 150, 3500), 9000);
			
			notificationElement.appendTo(wp_lib_scripts.Notifications.s.notificationsWrapper).hide().fadeIn();
			
			// Sets notification to fade away after 5 seconds then get deleted
			setTimeout(function(){
				wp_lib_scripts.Notifications.hideNotification(notificationElement);
			}, displayTime);
		},
		
		displayNotifications: function(notifications) {
			notifications.forEach(function(notification) {
				wp_lib_scripts.Notifications.displayNotification(notification);
			});
		},
		
		hideNotification: function(notificationElement) {
			// Removes notification, in style!
			notificationElement.fadeOut("fast");
			
			// Deletes notification
			notificationElement.remove();
		},
	};
	
	wp_lib_scripts.Notifications.init();
});
