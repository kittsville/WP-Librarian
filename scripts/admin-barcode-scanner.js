jQuery( document ).ready(function($) {
	// Workaround for IE9 and older (God I hate IE so much...)
	$('[autofocus]:not(:focus)').eq(0).focus();
	
	// Triggers barcode lookup on 'Scan' item button being clicked
	$('a#barcode-submit').click(function(e){
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
					'item_id'	: serverResult[1]
				});
			}
		});
	});
	
	/* Commented out until feature is actually ready
	// Selects barcode input field
	var barcodeInput = $( '#barcode-input' );
	
	// Checks if the entered numbers are long enough to be the expected barcode length
	barcodeInput.on( 'input', function() {
		if ( barcodeInput.val().length == 8 ) {
			$( '[value="scan-barcode"]' ).click();
		}
	});
	*/
	
});