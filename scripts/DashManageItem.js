jQuery(function($) {
	var DashManageItem = {
		// Settings
		s: {
			memberSelect:       jQuery('form.lib-form select.member-select'),
			metaBoxWrap:        jQuery('div#member-metabox-wrap'),
			currentMemberID:    NaN,
			metaBoxCache:       {},                                             // Holds member metaboxes that have already been loaded
			navigationKeys:     [33, 34, 35, 36, 38, 40, 104, 98],              // In order: PG UP, PG Down, End, Home, Up, Down, NumPad 8, NumPad 2
		},
		
		init: function() {
			this.bindUIActions();
		},
		
		bindUIActions: function() {
			this.s.memberSelect.on({
				change: function() {
					DashManageItem.updateMemberMetabox();
				},
				keyup: function(event) {
					if (DashManageItem.s.navigationKeys.indexOf(event.which) !== -1) {
						DashManageItem.updateMemberMetabox();
					}
				},
			});
		},
		
		updateMemberMetabox: function() {
			var newMemberID = parseInt(DashManageItem.s.memberSelect.val()),
			currentMemberID = DashManageItem.s.currentMemberID,
			metaBoxCache    = DashManageItem.s.metaBoxCache;
			
			if (newMemberID === currentMemberID) {
				return;
			}
			
			if (!isNaN(currentMemberID)) {
				metaBoxCache[currentMemberID].detach();
			}
			
			DashManageItem.s.currentMemberID = newMemberID;
			
			if (isNaN(newMemberID)) {
				return;
			}
			
			if (metaBoxCache.hasOwnProperty(newMemberID)) {
				DashManageItem.s.metaBoxWrap.html(metaBoxCache[newMemberID]);
			} else {
				var params = {
					'api_request'   : 'member-metabox',
					'member_id'     : newMemberID,
				};
				
				wp_lib_api_call(params, function(serverResponse) {
					// If server responded successfully
					if (serverResponse[0] === 4) {
						var newMemberMetabox = serverResponse[1][2];
						
						metaBoxCache[newMemberID] = wp_lib_render_page_element(newMemberMetabox);
						
						DashManageItem.s.metaBoxWrap.html(metaBoxCache[newMemberID]);
					}
				});
			}
		},
	};
	
	// Allows others scripts to access this module
	wp_lib_scripts.DashManageItem = DashManageItem;
});