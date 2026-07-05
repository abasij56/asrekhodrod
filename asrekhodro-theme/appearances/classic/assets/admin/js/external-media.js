jQuery( function ( $ ) {
	var isAdding = false;

	function clearPanel() {
		$( '#emwi-urls' ).val( '' );
		$( '#emwi-hidden' ).hide();
		$( '#emwi-error' ).text( '' );
		$( '#emwi-width' ).val( '' );
		$( '#emwi-height' ).val( '' );
		$( '#emwi-mime-type' ).val( '' );
	}

	$( 'body' ).on( 'click', '#emwi-clear', function () {
		clearPanel();
	} );

	$( 'body' ).on( 'click', '#emwi-show', function ( event ) {
		$( '#emwi-media-new-panel' ).show();
		event.preventDefault();
	} );

	$( 'body' ).on( 'click', '#emwi-in-upload-ui #emwi-add', function ( event ) {
		if ( isAdding || typeof akExternalMedia === 'undefined' ) {
			return;
		}

		isAdding = true;
		$( '#emwi-in-upload-ui #emwi-add' ).prop( 'disabled', true );

		var postData = {
			urls: $( '#emwi-urls' ).val(),
			width: $( '#emwi-width' ).val(),
			height: $( '#emwi-height' ).val(),
			'mime-type': $( '#emwi-mime-type' ).val(),
			nonce: akExternalMedia.nonce,
		};

		wp.media.post( akExternalMedia.action, postData )
			.done( function ( response ) {
				var frame = wp.media.frame || wp.media.library;
				if ( frame && response.attachments ) {
					frame.content.mode( 'browse' );
					var library = frame.state().get( 'library' ) || frame.library;
					response.attachments.forEach( function ( item ) {
						var attachment = wp.media.model.Attachment.create( item );
						attachment.fetch();
						library.add( attachment ? [ attachment ] : [] );
						if ( wp.media.frame._state !== 'library' ) {
							var selection = frame.state().get( 'selection' );
							if ( selection ) {
								selection.add( attachment );
							}
						}
					} );
				}

				if ( response.error ) {
					$( '#emwi-error' ).text( response.error );
					$( '#emwi-width' ).val( response.width );
					$( '#emwi-height' ).val( response.height );
					$( '#emwi-mime-type' ).val( response[ 'mime-type' ] );
					$( '#emwi-hidden' ).show();
				} else {
					clearPanel();
					$( '#emwi-hidden' ).hide();
				}

				$( '#emwi-urls' ).val( response.urls || '' );
				$( '#emwi-buttons-row .spinner' ).css( 'visibility', 'hidden' );
				$( '#emwi-in-upload-ui #emwi-add' ).prop( 'disabled', false );
				isAdding = false;
			} )
			.fail( function () {
				$( '#emwi-error' ).text( 'An unknown network error occurred.' );
				$( '#emwi-buttons-row .spinner' ).css( 'visibility', 'hidden' );
				$( '#emwi-in-upload-ui #emwi-add' ).prop( 'disabled', false );
				isAdding = false;
			} );

		event.preventDefault();
		$( '#emwi-buttons-row .spinner' ).css( 'visibility', 'visible' );
	} );

	$( 'body' ).on( 'click', '#emwi-in-upload-ui #emwi-cancel', function ( event ) {
		clearPanel();
		$( '#emwi-media-new-panel' ).hide();
		$( '#emwi-buttons-row .spinner' ).css( 'visibility', 'hidden' );
		$( '#emwi-in-upload-ui #emwi-add' ).prop( 'disabled', false );
		isAdding = false;
		event.preventDefault();
	} );
} );
