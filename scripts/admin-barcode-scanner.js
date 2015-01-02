jQuery( document ).ready(function($) {
	// Workaround for IE9 and older (God I hate IE so much...)
	$('[autofocus]:not(:focus)').eq(0).focus();
	
	var barcodeSubmit = $('form.lib-form a#barcode-submit');
	
	// Fetches barcode length and auto-fetch parameters
	wp_lib_api_call( {'api_request':'barcode-setup'}, function( serverResult ) {
		/*
		 * If API call was successful, settings exist and auto-fetch is on, set up automatic lookup
		 * Server result content (serverResult[1][2]) consists of an array containing:
		 * 0 - Autofetch toggle, if to automatically lookup the given barcode if the input reaches a certain length
		 * 1 - Length, triggering length for automatic lookup
		 */
		if ( serverResult[0] === 4 && serverResult[1][2] instanceof Array && serverResult[1][2][0] === true ) {
			// Selects barcode input field
			var barcodeInput = $( '#barcode-input' );
			
			// If input reaches/exceeds target length for automatic lookup, lookup barcode
			barcodeInput.on( 'input', function() {
				if ( barcodeInput.val().length >= serverResult[1][2][1] ) {
					barcodeSubmit.click();
				}
			});
		}
	});
	
	// Triggers barcode lookup on 'Scan' item button being clicked
	barcodeSubmit.click(function(e){
		// Sets up API call params
		var params = {
			'api_request'	: 'scan-barcode',
			'code'			: $(e.target).siblings('input#barcode-input').val()
		};
		
		wp_lib_api_call( params, function( serverResult ) {
			// If server successfully found an item with that barcode
			if ( serverResult[0] === 4 ) {
				wp_lib_load_page({
					'dash_page'	: 'manage-item',
					'item_id'	: serverResult[1][2]
				});
			}
		});
	});
});