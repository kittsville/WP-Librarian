jQuery( document ).ready(function($) {
	// Workaround for IE9 and older (God I hate IE so much...)
	$('[autofocus]:not(:focus)').eq(0).focus();
	
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