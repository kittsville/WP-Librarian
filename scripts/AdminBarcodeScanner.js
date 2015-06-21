var AdminBarcodeScanner = {
	// Settings
	s: {
		searchButton:	jQuery('a#barcode-submit'),
		barcodeInput:	jQuery('input#barcode-input'),
	},
	
	init: function() {
		this.bindUIActions();
	},
	
	bindUIActions: function() {
		this.s.searchButton.on('click', function() {
			AdminBarcodeScanner.lookupBarcode(AdminBarcodeScanner.s.barcodeInput.val());
		});
	},
	
	// Searches library for item matching barcode or ISBN
	lookupBarcode: function(itemBarcode) {
		var params = {
			'api_request'	: 'scan-barcode',
			'code'			: AdminBarcodeScanner.s.barcodeInput.val(),
		};
		
		wp_lib_api_call(params, function(serverResult) {
			// If server successfully found an item with that barcode
			if (serverResult[0] === 4) {
				wp_lib_load_page({
					'dash_page':	'manage-item',
					'item_id':		serverResult[1][2],
				});
			}
		});
	},
};
