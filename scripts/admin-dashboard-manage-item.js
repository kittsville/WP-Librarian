jQuery(function($){
	// Selects member select field
	var memberSelect = $( 'form#lib-form select.member-select' );
	
	// Fetches select field's current value
	var currentMember = memberSelect.val();
	
	// Initialises cache of fetched members
	var metaBoxCache = [];
	
	// Updates currently displayed member meta box when selected member is changed
	// Thanks to Adeneo's (http://stackoverflow.com/users/965051) answer (http://stackoverflow.com/questions/11993751)
	memberSelect.on({
		keyup: function(e){
			// Uses flags to avoid triggering a change event twice
			flag = false;
			if ([33, 34, 35, 36, 37, 38, 39, 40].indexOf(e.which) !=-1) $(this).trigger('change');
			setTimeout(function() {flag=true}, 200);
		},
		change: function(e){
			// Fetches member select's updated value
			var memberID = memberSelect.val();
			
			// Updates current member meta box displaying based on new member selected
			currentMember = update_displayed_member( memberID, currentMember );
		}
	});
	
	// Updates current member meta box (if any) being displayed
	function update_displayed_member( newMember, currentMember ) {
		// If member selected hasn't changed, no action is necessary
		if ( currentMember === newMember ) {
			return currentMember;
		}
		
		// Hides previous metabox
		$( '.member-man.lib-metabox' ).hide();
		
		// If given value isn't a member ID (e.g. if 'Select' is chosen), no meta needs to be fetched
		if ( isNaN(parseInt(newMember)) ) {
			return '';
		}
		
		// Sets default cache retrieval success
		var cacheSuccess = false;
		
		// If member's meta box has already been fetched, stops meta box being hidden. Otherwise fetches from server
		metaBoxCache.every( function( e ) {
			if ( e == newMember ) {
				// Displays cached member's meta box
				$( '.member-man.lib-metabox.member-' + e ).fadeIn( 30 );
				
				// Sets cache retrival success
				cacheSuccess = true;
				
				// Prevents loop continuing
				return false;
			}
		});
		
		// If member meta box could not be fetched from the cache request meta box from server
		if ( cacheSuccess === false ) {
			// Sets up AJAX query params
			var ajaxData = {
			'api_request'	: 'member-metabox',
			'member_id'		: newMember
			};
			
			// Queries server for member meta box
			wp_lib_api_call( ajaxData, function( serverResponse ) {
				// If server responded successfully
				if ( serverResponse[0] === 4 ) {
					// Renders meta box to header, labels with member's ID then fades in
					wp_lib_render_page_element(serverResponse[1][0], $('#wp-lib-header')).addClass('member-' + newMember).hide().fadeIn(30 );
					
					// Adds ID of fetched member meta box to cache
					metaBoxCache.push( newMember );
				}
			});
		}
		
		return newMember;
	}
});