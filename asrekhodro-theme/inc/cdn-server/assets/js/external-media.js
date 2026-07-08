jQuery( function ( $ ) {
	var isAdding = false;
	var isUploading = false;

	function clearPanel() {
		$( '#emwi-urls' ).val( '' );
		$( '#emwi-hidden' ).hide();
		$( '#emwi-error' ).text( '' );
		$( '#emwi-width' ).val( '' );
		$( '#emwi-height' ).val( '' );
		$( '#emwi-mime-type' ).val( '' );
	}

	function escapeHtml( text ) {
		return String( text )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	function renderUploadDebug( $panel, rows ) {
		if ( ! $panel || ! $panel.length ) {
			return;
		}

		if ( ! rows || ! rows.length ) {
			$panel.hide().empty();
			return;
		}

		var i18n = ( typeof akExternalMedia !== 'undefined' && akExternalMedia.i18n ) ? akExternalMedia.i18n : {};
		var title = i18n.debugTitle || 'جزئیات مسیر آپلود روی FTP';
		var html = '<p class="emwi-cdn-debug__title">' + escapeHtml( title ) + '</p>';
		html += '<table class="widefat striped"><tbody>';

		rows.forEach( function ( row ) {
			html += '<tr><th scope="row">' + escapeHtml( row.label ) + '</th><td><code>'
				+ escapeHtml( row.value ) + '</code></td></tr>';
		} );

		html += '</tbody></table>';
		$panel.html( html ).show();
	}

	function showStandaloneUploadSuccess( data, $target ) {
		var i18n = ( typeof akExternalMedia !== 'undefined' && akExternalMedia.i18n ) ? akExternalMedia.i18n : {};
		var url = data && data.url ? data.url : '';
		var message = i18n.uploadSuccess || 'فایل با موفقیت روی CDN آپلود و در کتابخانه رسانه ثبت شد.';

		if ( url ) {
			message += ' ' + url;
		}

		$target
			.css( 'color', '#008a20' )
			.text( message );
	}

	function normalizeAjaxResponse( response ) {
		if ( response && typeof response.success !== 'undefined' ) {
			if ( ! response.success ) {
				var errorData = response.data || {};
				return {
					error: errorData.message || errorData.error || 'An unknown network error occurred.',
					message: errorData.message || '',
				};
			}

			return response.data || {};
		}

		return response || {};
	}

	function urlAddSucceeded( response ) {
		if ( ! response || response.error ) {
			return false;
		}

		if ( response.attachments && response.attachments.length ) {
			return true;
		}

		return !!( response.attachment_ids && response.attachment_ids.length );
	}

	function getLibraryUrl() {
		if ( typeof akExternalMedia !== 'undefined' && akExternalMedia.libraryUrl ) {
			return akExternalMedia.libraryUrl;
		}

		if ( typeof akExternalMedia !== 'undefined' && akExternalMedia.uploadBaseUrl ) {
			return akExternalMedia.uploadBaseUrl;
		}

		return 'upload.php';
	}

	function redirectStandaloneToLibrary() {
		window.setTimeout( function () {
			window.location.href = getLibraryUrl();
		}, 600 );
	}

	function getUrlPanelFromTrigger( $trigger ) {
		return $trigger.closest( '#emwi-media-new-panel' );
	}

	function isModalUrlPanel( $panel ) {
		return $panel.closest( '.media-modal' ).length > 0;
	}

	function buildUrlPostData( $panel ) {
		return {
			urls: $panel.find( '#emwi-urls' ).val(),
			width: $panel.find( '#emwi-width' ).val(),
			height: $panel.find( '#emwi-height' ).val(),
			'mime-type': $panel.find( '#emwi-mime-type' ).val(),
			nonce: akExternalMedia.nonce,
		};
	}

	function handleStandaloneUrlResponse( response, $panel ) {
		if ( response.error ) {
			$panel.find( '#emwi-error' ).text( response.error );
			$panel.find( '#emwi-width' ).val( response.width );
			$panel.find( '#emwi-height' ).val( response.height );
			$panel.find( '#emwi-mime-type' ).val( response[ 'mime-type' ] );
			$panel.find( '#emwi-hidden' ).show();
			$panel.find( '#emwi-urls' ).val( response.urls || '' );
			return;
		}

		if ( ! urlAddSucceeded( response ) ) {
			$panel.find( '#emwi-error' ).text( 'An unknown network error occurred.' );
			$panel.find( '#emwi-hidden' ).show();
			return;
		}

		redirectStandaloneToLibrary();
	}

	function submitUrlPanel( $panel, $button ) {
		var i18n = akExternalMedia.i18n || {};
		var urls = $.trim( $panel.find( '#emwi-urls' ).val() );

		if ( urls === '' ) {
			$panel.find( '#emwi-error' ).text( i18n.urlsRequired || 'Please fill in at least one URL.' );
			$panel.find( '#emwi-hidden' ).show();
			return;
		}

		isAdding = true;
		$button.prop( 'disabled', true );
		$panel.find( '#emwi-buttons-row .spinner' ).css( 'visibility', 'visible' );
		$panel.find( '#emwi-error' ).text( '' );

		$.ajax( {
			url: ( typeof akExternalMedia !== 'undefined' && akExternalMedia.ajaxUrl ) ? akExternalMedia.ajaxUrl : window.ajaxurl,
			type: 'POST',
			data: $.extend(
				{ action: akExternalMedia.action },
				buildUrlPostData( $panel )
			),
		} )
			.done( function ( response ) {
				if ( response && response.success === false ) {
					var errorData = normalizeAjaxResponse( response );
					$panel.find( '#emwi-error' ).text( errorData.message || errorData.error || 'An unknown network error occurred.' );
					$panel.find( '#emwi-hidden' ).show();
					return;
				}

				var data = normalizeAjaxResponse( response );

				if ( isModalUrlPanel( $panel ) ) {
					completeModalUpload( data );

					if ( data.error ) {
						$panel.find( '#emwi-error' ).text( data.error );
						$panel.find( '#emwi-width' ).val( data.width );
						$panel.find( '#emwi-height' ).val( data.height );
						$panel.find( '#emwi-mime-type' ).val( data[ 'mime-type' ] );
						$panel.find( '#emwi-hidden' ).show();
					} else {
						clearPanel();
						$panel.find( '#emwi-hidden' ).hide();
						hideUrlPanel();
					}

					$panel.find( '#emwi-urls' ).val( data.urls || '' );
				} else {
					handleStandaloneUrlResponse( data, $panel );
				}
			} )
			.fail( function () {
				$panel.find( '#emwi-error' ).text( 'An unknown network error occurred.' );
				$panel.find( '#emwi-hidden' ).show();
			} )
			.always( function () {
				$panel.find( '#emwi-buttons-row .spinner' ).css( 'visibility', 'hidden' );
				$button.prop( 'disabled', false );
				isAdding = false;
			} );
	}

	function getMediaFrame() {
		if ( typeof wp === 'undefined' || ! wp.media ) {
			return null;
		}

		if ( wp.media.frame ) {
			return wp.media.frame;
		}

		if ( wp.media.featuredImage && typeof wp.media.featuredImage.frame === 'function' ) {
			return wp.media.featuredImage.frame();
		}

		return null;
	}

	function isMediaModalOpen() {
		var $modal = $( '#wp-media-modal, .media-modal' ).filter( ':visible' );

		return $modal.length > 0;
	}

	function hideUrlPanel() {
		$( '#emwi-media-new-panel' ).hide();
	}

	function completeModalUpload( response ) {
		if ( ! isMediaModalOpen() ) {
			return false;
		}

		try {
			var added = addAttachmentsToFrame( response );

			if ( added ) {
				hideUrlPanel();
				$( '.emwi-cdn-error' ).text( '' ).css( 'color', '' );
			}

			return added;
		} catch ( error ) {
			return false;
		}
	}

	function addAttachmentsToFrame( response ) {
		if ( typeof wp === 'undefined' || ! wp.media || ! response || ! response.attachments ) {
			return false;
		}

		var frame = getMediaFrame();
		if ( ! frame || ! frame.content || typeof frame.content.mode !== 'function' || typeof frame.state !== 'function' ) {
			return false;
		}

		if ( ! isMediaModalOpen() ) {
			return false;
		}

		frame.content.mode( 'browse' );

		if ( frame.router && typeof frame.router.navigate === 'function' ) {
			frame.router.navigate( 'browse', { trigger: true } );
		}

		var state = frame.state();
		if ( ! state || typeof state.get !== 'function' ) {
			return false;
		}

		var library = state.get( 'library' ) || frame.library;
		if ( ! library ) {
			return false;
		}

		var selection = state.get( 'selection' );
		var selected = [];

		response.attachments.forEach( function ( item ) {
			var attachment = wp.media.model.Attachment.get( item.id );
			attachment.set( item );
			attachment.fetch();
			library.add( attachment, { at: 0 } );
			selected.push( attachment );
		} );

		if ( selection && selected.length ) {
			if ( typeof selection.reset === 'function' ) {
				selection.reset( selected );
			} else {
				selected.forEach( function ( attachment ) {
					selection.add( attachment );
				} );
			}
		}

		if ( typeof frame.trigger === 'function' && selected.length ) {
			frame.trigger( 'selection:toggle', selected[ 0 ] );
		}

		return true;
	}

	$( 'body' ).on( 'click', '#emwi-clear', function () {
		clearPanel();
	} );

	$( 'body' ).on( 'click', '#emwi-show', function ( event ) {
		$( this ).closest( '#emwi-in-upload-ui' ).find( '#emwi-media-new-panel' ).show();
		event.preventDefault();
	} );

	$( 'body' ).on( 'click', '.emwi-url-add', function ( event ) {
		event.preventDefault();

		if ( isAdding || typeof akExternalMedia === 'undefined' || typeof wp === 'undefined' || ! wp.media ) {
			return;
		}

		var $button = $( this );
		var $panel = getUrlPanelFromTrigger( $button );

		if ( ! $panel.length ) {
			return;
		}

		submitUrlPanel( $panel, $button );
	} );

	$( 'body' ).on( 'click', '#emwi-in-upload-ui #emwi-cancel', function ( event ) {
		var $container = $( this ).closest( '#emwi-in-upload-ui' );

		clearPanel();
		$container.find( '#emwi-media-new-panel' ).hide();
		$container.find( '#emwi-buttons-row .spinner' ).css( 'visibility', 'hidden' );
		$container.find( '.emwi-url-add' ).prop( 'disabled', false );
		isAdding = false;
		event.preventDefault();
	} );

	$( 'body' ).on( 'click', '.emwi-upload-cdn', function ( event ) {
		event.preventDefault();

		if ( isUploading || typeof akExternalMedia === 'undefined' || ! akExternalMedia.cdnEnabled ) {
			return;
		}

		var i18n = akExternalMedia.i18n || {};
		var $section = $( this ).closest( '.emwi-cdn-section' );
		var $error = $section.find( '.emwi-cdn-error' );
		var $spinner = $section.find( '.emwi-cdn-spinner' );
		var $debug = $section.find( '.emwi-cdn-debug' );
		var $button = $( this );
		var input = $section.find( '.emwi-cdn-file' )[ 0 ];
		var file = input && input.files ? input.files[ 0 ] : null;

		$error.text( '' ).css( 'color', '' );
		$debug.hide().empty();

		if ( ! file ) {
			$error.text( i18n.pickFile || 'Please select a file first.' );
			return;
		}

		if ( akExternalMedia.maxSize && file.size > akExternalMedia.maxSize ) {
			$error.text( i18n.tooLarge || 'File is too large.' );
			return;
		}

		var formData = new FormData();
		formData.append( 'action', akExternalMedia.uploadAction );
		formData.append( 'nonce', akExternalMedia.uploadNonce );
		formData.append( 'file', file );

		isUploading = true;
		$button.prop( 'disabled', true );
		$spinner.css( 'visibility', 'visible' );

		$.ajax( {
			url: ( typeof akExternalMedia !== 'undefined' && akExternalMedia.ajaxUrl ) ? akExternalMedia.ajaxUrl : window.ajaxurl,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
		} )
			.done( function ( response ) {
				var debug = response && response.data && response.data.upload_debug
					? response.data.upload_debug
					: null;

				try {
					if ( response && response.success && response.data ) {
						var addedToFrame = completeModalUpload( response.data );

						if ( ! addedToFrame ) {
							showStandaloneUploadSuccess( response.data, $error );
							renderUploadDebug( $debug, debug );
							redirectStandaloneToLibrary();
						} else {
							$error.css( 'color', '' ).text( '' );
							renderUploadDebug( $debug, null );
						}

						if ( input ) {
							input.value = '';
						}
					} else {
						var message = response && response.data && response.data.message
							? response.data.message
							: ( i18n.networkFail || 'Upload failed.' );
						$error.css( 'color', '#d63638' ).text( message );
						renderUploadDebug( $debug, debug );
					}
				} catch ( error ) {
					$error.css( 'color', '#d63638' ).text( i18n.networkFail || 'Upload failed.' );
					renderUploadDebug( $debug, debug );
				}
			} )
			.fail( function () {
				$error.text( i18n.networkFail || 'Upload failed.' );
			} )
			.always( function () {
				$spinner.css( 'visibility', 'hidden' );
				$button.prop( 'disabled', false );
				isUploading = false;
			} );
	} );

	$( '.emwi-url-add' ).prop( 'disabled', false );
} );
