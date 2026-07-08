jQuery( function ( $ ) {
	if ( typeof akCdnSettings === 'undefined' ) {
		return;
	}

	var i18n = akCdnSettings.i18n || {};

	function readAcfField( name ) {
		var $field = $( '.acf-field[data-name="' + name + '"]' );
		if ( ! $field.length ) {
			return '';
		}

		var $checkbox = $field.find( 'input[type="checkbox"]' );
		if ( $checkbox.length ) {
			return $checkbox.is( ':checked' ) ? '1' : '0';
		}

		var $select = $field.find( 'select' );
		if ( $select.length ) {
			return $select.val() || '';
		}

		var $input = $field.find( 'input, textarea' ).first();
		return $input.length ? ( $input.val() || '' ) : '';
	}

	function collectTestPayload() {
		return {
			action: akCdnSettings.testAction,
			nonce: akCdnSettings.testNonce,
			from_form: '1',
			protocol: readAcfField( 'cdn_protocol' ),
			host: readAcfField( 'cdn_host' ),
			port: readAcfField( 'cdn_port' ),
			user: readAcfField( 'cdn_user' ),
			pass: readAcfField( 'cdn_pass' ),
			remote_base_path: readAcfField( 'cdn_remote_base_path' ),
		};
	}

	function renderDebugPanel( debug ) {
		var $details = $( '#ak-cdn-test-details' );
		if ( ! debug || !debug.length ) {
			$details.hide().empty();
			return;
		}

		var title = i18n.debugTitle || 'مشخصات اتصال استفاده‌شده:';
		var html = '<p class="ak-cdn-test-details__title"><strong>' + title + '</strong></p>';
		html += '<table class="widefat striped ak-cdn-test-details__table"><tbody>';

		debug.forEach( function ( row ) {
			html += '<tr><th scope="row">' + escapeHtml( row.label ) + '</th><td><code>'
				+ escapeHtml( row.value ) + '</code></td></tr>';
		} );

		html += '</tbody></table>';
		$details.html( html ).show();
	}

	function escapeHtml( text ) {
		return String( text )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	$( 'body' ).on( 'click', '#ak-cdn-test-btn', function ( event ) {
		event.preventDefault();

		var $btn = $( this );
		var $result = $( '#ak-cdn-test-result' );
		var $details = $( '#ak-cdn-test-details' );

		$btn.prop( 'disabled', true );
		$result.css( 'color', '#646970' ).text( i18n.testing || 'Testing…' );
		$details.hide().empty();

		$.post( window.ajaxurl, collectTestPayload() )
			.done( function ( response ) {
				var debug = response && response.data && response.data.debug
					? response.data.debug
					: null;

				if ( response && response.success ) {
					$result.css( 'color', '#008a20' ).text(
						( response.data && response.data.message ) || 'OK'
					);
				} else {
					var message = response && response.data && response.data.message
						? response.data.message
						: ( i18n.failed || 'Connection failed.' );
					$result.css( 'color', '#d63638' ).text( message );
				}

				renderDebugPanel( debug );
			} )
			.fail( function () {
				$result.css( 'color', '#d63638' ).text( i18n.failed || 'Connection failed.' );
			} )
			.always( function () {
				$btn.prop( 'disabled', false );
			} );
	} );
} );
