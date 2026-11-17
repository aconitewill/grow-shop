/* Grow Shop admin: the email logo picker. */
( function ( $ ) {
	var frame;
	$( document ).on( 'click', '[data-grs-logo-pick]', function ( e ) {
		e.preventDefault();
		if ( ! frame ) {
			frame = wp.media( { title: 'Email logo', library: { type: 'image' }, multiple: false } );
			frame.on( 'select', function () {
				var a = frame.state().get( 'selection' ).first().toJSON();
				var url = ( a.sizes && a.sizes.thumbnail ) ? a.sizes.thumbnail.url : a.url;
				$( '[data-grs-logo]' ).val( a.id );
				$( '[data-grs-logo-preview]' ).html( $( '<img>' ).attr( { src: url, alt: '' } ).css( { maxHeight: '60px', width: 'auto' } ) );
			} );
		}
		frame.open();
	} );
	$( document ).on( 'click', '[data-grs-logo-clear]', function ( e ) {
		e.preventDefault();
		$( '[data-grs-logo]' ).val( '' );
		$( '[data-grs-logo-preview]' ).empty();
	} );
}( jQuery ) );
