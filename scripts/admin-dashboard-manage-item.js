jQuery(function($){
	// Selects member select field
	var memberSelect = $( 'form.lib-form select.member-select' );
	
	// Fetches select field's current value
	var currentMemberID = memberSelect.val();
	
	// Initialises cache of fetched members
	var metaBoxCache = {};
	
	// Updates currently displayed member meta box when selected member is changed
	// Thanks to Adeneo's (http://stackoverflow.com/users/965051) answer about keyboard selection (http://stackoverflow.com/questions/11993751)
	memberSelect.on({
		keyup: function(e){
			// Uses flags to avoid triggering a change event twice
			flag = false;
			if ([33, 34, 35, 36, 37, 38, 39, 40].indexOf(e.which) !=-1) $(this).trigger('change');
			setTimeout(function() {flag=true}, 200);
		},
		change: function(e){
			// Fetches member select's updated value
			var newMemberID = parseInt( memberSelect.val() );
			
			// Updates current member meta box displaying based on new member selected
			currentMemberID = update_displayed_member( newMemberID, currentMemberID );
		}
	});
	
	/*
	 * Updates currently displaying member meta box, based on new member selected
	 * Caches fetched members, to re-use if the member is re-selected
	 * @param int|NaN newMemberID		ID to new member selected, or NaN if default has been selected
	 * @param int|NaN currentMemberID	ID of member last selected, or NaN if default was last selected
	 */
	function update_displayed_member( newMemberID, currentMemberID ) {
		// If member selected hasn't changed, no action is necessary
		if ( currentMemberID === newMemberID ) {
			return currentMemberID;
		}
		
		// Hides previous meta box, if one exists
		if ( metaBoxCache.hasOwnProperty(currentMemberID)) {
			metaBoxCache[currentMemberID].hide();
		}
		
		// If given newMemberID isn't a member ID (if 'Select' is chosen), no meta needs to be fetched
		if ( isNaN(newMemberID) ) {
			return NaN;
		}
		
		// If member's meta box exists in cache, fetch and use
		// Otherwise fetch from server, then cache
		if ( metaBoxCache.hasOwnProperty(newMemberID)) {
			// Displays cached member's meta box
			metaBoxCache[newMemberID].show();
		} else {
			// Queries server for member meta box
			wp_lib_api_call({
				'api_request'	: 'member-metabox',
				'member_id'		: newMemberID
			},
			function( serverResponse ) {
				// If server responded successfully
				if ( serverResponse[0] === 4 ) {
					// Renders meta box to header, labels with member's ID then fades in then caches meta box at a position in the array based on the member ID
					metaBoxCache[newMemberID] = wp_lib_render_page_element(serverResponse[1][2]).insertAfter('div#wp-lib-workspace > div.item-man').addClass('member-man').hide().fadeIn(30);
				}
			});
		}
	return newMemberID;
	}
});