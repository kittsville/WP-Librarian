var barcodeLength = 8;

jQuery( document ).ready(function($) {
	// Workaround for IE9 and older (God I hate IE so much...)
	$('[autofocus]:not(:focus)').eq(0).focus();
	
	// Selects barcode input field
	var barcodeInput = $( '#barcode-input' );
	
	// Checks if the entered numbers are long enough to be the expected barcode length
	barcodeInput.on( 'input', function() {
		if ( barcodeInput.val().length == barcodeLength ) {
			wp_lib_do_action( 'scan-barcode', {
				'item_barcode'	: barcodeInput.val()
			});
		}
	});
});