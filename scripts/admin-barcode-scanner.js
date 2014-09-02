var barcodeLength = 8;

jQuery( document ).ready(function($) {
	// Workaround for IE9 and older (God I hate IE so much...)
	$('[autofocus]:not(:focus)').eq(0).focus();
	
	// Selects barcode input field
	var barcodeInput = $( '#barcode-input' );
	
	// Checks if the entered numbers are long enough to be the expected barcode length
	barcodeInput.on( 'input', function() {
		if ( barcodeInput.val().length == barcodeLength ) {
			wp_lib_lookup_barcode( barcodeInput.val() );
		}
	});
});

// Searches for item with given barcode
// Returns item ID on success, false on failure
function wp_lib_lookup_barcode( input ) {
	var barcode = parseInt( input, 10 );

	if ( !( typeof barcode== "number" && isFinite( barcode ) && barcode%1===0 ) ) {
		wp_lib_local_error( "The barcode needs to be a number" );
		wp_lib_display_notifications();
		return false;
	}
	
	// Sets up AJAX data
	var ajaxData = {
		action	: 'wp_lib_lookup_barcode',
		code	: barcode
	};
	
	// Sends request to the server
	jQuery.post( ajaxurl, ajaxData, function( response ) {
		response = JSON.parse( response );
		if ( response ) {
			wp_lib_load_page( 'manage-item', { item_id : response } );
		} else {
			wp_lib_local_error( "Unable to find item with that barcode" );
		}
	})
	.fail( function() {
		wp_lib_ajax_fail();
	});
}