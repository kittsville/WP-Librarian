// Makes the slug preview dynamic so that the user can see what the full url would look like
// var text is the input box where the user types the slug
// var textPreview is an html span that updates to show whatever var text currently is
jQuery( document ).ready(function($) {
	$('.slug-input').each(function(index,element){
		var text = $(element);
		text.on( 'input', function(e) {
			var textID = $(element).attr("id");
			textPreview = $( '.' + textID + '-text' ); 
			textPreview.text(text.val().replace(/\ /g, '-').toLowerCase());
		});
	});
});