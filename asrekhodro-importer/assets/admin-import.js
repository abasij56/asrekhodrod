( function ( $ ) {
	var running = false;
	var cancelRequested = false;
	var activeRequest = null;
	var activeMode = 'full';
	var contentToken = '';

	function getResetFlags() {
		var flags = {};
		$( '.ak-import-reset:checked:not(:disabled)' ).each( function () {
			var match = ( this.name || '' ).match( /^reset\[([^\]]+)\]$/ );
			if ( match ) {
				flags[ 'reset[' + match[ 1 ] + ']' ] = '1';
			}
		} );
		return flags;
	}

	function getPostBatchSize() {
		var min = akImporter.postBatch.min;
		var max = akImporter.postBatch.max;
		var value = parseInt( $( '#ak-import-post-batch' ).val(), 10 );

		if ( isNaN( value ) ) {
			value = akImporter.postBatch.default;
		}

		return Math.max( min, Math.min( max, value ) );
	}

	function getStepTimeout( batchSize ) {
		return Math.max( 120000, batchSize * 1200 );
	}

	function post( action, data, timeout ) {
		data = data || {};
		data.action = action;
		data.nonce = akImporter.nonce;

		activeRequest = $.ajax( {
			url: akImporter.ajaxUrl,
			method: 'POST',
			data: data,
			timeout: timeout || 600000,
		} );

		return activeRequest;
	}

	function getErrorMessage( xhr, fallback ) {
		if ( xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) {
			return xhr.responseJSON.data.message;
		}

		if ( xhr && xhr.responseText ) {
			try {
				var parsed = JSON.parse( xhr.responseText );
				if ( parsed && parsed.data && parsed.data.message ) {
					return parsed.data.message;
				}
			} catch ( error ) {}
		}

		if ( xhr && xhr.statusText ) {
			return fallback + ' (' + xhr.statusText + ')';
		}

		return fallback;
	}

	function setStatus( text ) {
		$( '#ak-import-status-text' ).text( text );
	}

	function updateBars( payload ) {
		var phasePct = typeof payload.phase_percent === 'number'
			? payload.phase_percent
			: ( payload.phase_total > 0
				? Math.round( ( payload.phase_done / payload.phase_total ) * 100 )
				: ( payload.phase_done > 0 ? 100 : 0 ) );
		var overallPct = typeof payload.overall_percent === 'number'
			? payload.overall_percent
			: ( payload.overall_total > 0
				? Math.round( ( payload.overall_done / payload.overall_total ) * 100 )
				: ( payload.overall_done > 0 ? 100 : 0 ) );

		if ( ! payload.done && payload.phase_done > 0 && phasePct < 2 ) {
			phasePct = 2;
		}
		if ( ! payload.done && payload.overall_done > 0 && overallPct < 2 ) {
			overallPct = 2;
		}

		$( '#ak-import-phase-label' ).text( payload.phase_label || '' );
		$( '#ak-import-phase-counter' ).text( payload.phase_done + ' / ' + payload.phase_total );
		$( '#ak-import-phase-bar' ).css( 'width', phasePct + '%' );

		$( '#ak-import-overall-counter' ).text( payload.overall_done + ' / ' + payload.overall_total );
		$( '#ak-import-overall-bar' ).css( 'width', overallPct + '%' );

		if ( payload.current_file ) {
			var fileInfo = payload.current_file;
			if ( payload.total_chunks > 1 && payload.current_chunk ) {
				fileInfo += ' (' + payload.current_chunk + '/' + payload.total_chunks + ')';
			}
			setStatus( payload.label ? ( payload.label + ' — ' + fileInfo ) : fileInfo );
		} else if ( payload.label ) {
			setStatus( payload.label );
		}

		if ( payload.counts ) {
			$( '#ak-import-count-posts' ).text( payload.counts.posts || 0 );
			$( '#ak-import-count-content-updated' ).text( payload.counts.updated || 0 );
			$( '#ak-import-count-content-skipped' ).text( payload.counts.skipped_not_found || 0 );
			$( '#ak-import-count-content-no-embed' ).text( payload.counts.skipped_no_embed || 0 );
			$( '#ak-import-count-ads' ).text( payload.counts.ads || 0 );
			$( '#ak-import-count-comments' ).text( payload.counts.comments || 0 );
			$( '#ak-import-count-media' ).text( payload.counts.external_media || 0 );
		}

		if ( payload.log_tail && payload.log_tail.length ) {
			$( '#ak-import-log' ).text( payload.log_tail.join( '\n' ) );
		}
	}

	function showPanel( mode ) {
		activeMode = mode || 'full';
		$( '#ak-import-progress' ).show();
		$( '#ak-import-start' ).prop( 'disabled', true );
		$( '#ak-content-reimport-start' ).prop( 'disabled', true );
		$( '#ak-import-post-batch' ).prop( 'disabled', true );
		$( '.ak-import-reset' ).prop( 'disabled', true );
		if ( activeMode === 'content' ) {
			$( '#ak-content-reimport-cancel' ).show().prop( 'disabled', false );
			$( '#ak-import-cancel' ).hide();
		} else {
			$( '#ak-import-cancel' ).show().prop( 'disabled', false );
			$( '#ak-content-reimport-cancel' ).hide();
		}
	}

	function resetImportControls( finished ) {
		running = false;
		cancelRequested = false;
		activeRequest = null;
		contentToken = '';
		activeMode = 'full';
		$( '#ak-import-cancel' ).hide().prop( 'disabled', false );
		$( '#ak-content-reimport-cancel' ).hide().prop( 'disabled', false );
		$( '#ak-import-post-batch' ).prop( 'disabled', false );
		$( '.ak-import-reset' ).prop( 'disabled', false );
		$( '.ak-import-reset[data-export-empty="1"]' ).prop( 'disabled', true );
		$( '#ak-import-start' ).prop( 'disabled', false ).text( finished ? 'Run Import Again' : 'Run Import' );
		$( '#ak-content-reimport-start' ).prop( 'disabled', false );
	}

	function showResult( result ) {
		if ( ! result ) {
			return;
		}

		$( '#ak-import-result' ).show().find( 'pre' ).text( JSON.stringify( result, null, 2 ) );
	}

	function runSteps( token, stepTimeout ) {
		if ( cancelRequested ) {
			return $.Deferred().resolve().promise();
		}

		return post( 'asrekhodro_import_step', { token: token }, stepTimeout ).then( function ( response ) {
			if ( cancelRequested ) {
				return;
			}

			if ( ! response || ! response.success ) {
				throw new Error( ( response && response.data && response.data.message ) || 'Import step failed.' );
			}

			var payload = response.data;
			updateBars( payload );

			if ( payload.done ) {
				resetImportControls( true );
				setStatus( akImporter.strings.finished );
				showResult( payload.result );
				return;
			}

			return runSteps( token, stepTimeout );
		} ).catch( function ( xhr ) {
			if ( cancelRequested || ( xhr && xhr.statusText === 'abort' ) ) {
				return;
			}

			if ( xhr instanceof Error ) {
				throw xhr;
			}

			throw new Error( getErrorMessage( xhr, 'Import step failed.' ) );
		} );
	}

	function pollBackground( token ) {
		if ( cancelRequested ) {
			return $.Deferred().resolve().promise();
		}

		return post( 'asrekhodro_import_background_status', { token: token }, 60000 ).then( function ( response ) {
			if ( cancelRequested ) {
				return;
			}

			if ( ! response || ! response.success ) {
				throw new Error( ( response && response.data && response.data.message ) || 'Import status failed.' );
			}

			var payload = response.data;
			updateBars( payload );

			if ( payload.done ) {
				resetImportControls( true );
				setStatus( akImporter.strings.finished );
				showResult( payload.result );
				return;
			}

			return new Promise( function ( resolve ) {
				window.setTimeout( resolve, 3000 );
			} ).then( function () {
				return pollBackground( token );
			} );
		} ).catch( function ( xhr ) {
			if ( cancelRequested || ( xhr && xhr.statusText === 'abort' ) ) {
				return;
			}

			if ( xhr instanceof Error ) {
				throw xhr;
			}

			throw new Error( getErrorMessage( xhr, 'Import status failed.' ) );
		} );
	}

	function cancelImport() {
		if ( ! running || cancelRequested ) {
			return;
		}

		if ( ! window.confirm( akImporter.strings.confirmCancel ) ) {
			return;
		}

		cancelRequested = true;
		$( '#ak-import-cancel' ).prop( 'disabled', true );
		setStatus( akImporter.strings.cancelling );

		if ( activeRequest ) {
			activeRequest.abort();
		}

		post( 'asrekhodro_import_cancel' )
			.then( function ( response ) {
				var message = akImporter.strings.cancelled;
				var counts = null;

				if ( response && response.success && response.data ) {
					if ( response.data.message ) {
						message = response.data.message;
					}
					counts = response.data.counts;
				}

				resetImportControls( false );
				setStatus( message );

				if ( counts ) {
					showResult( { cancelled: true, counts: counts } );
				}
			} )
			.catch( function () {
				resetImportControls( false );
				setStatus( akImporter.strings.cancelled );
			} );
	}

	function pollContentReimport( token ) {
		if ( cancelRequested ) {
			return $.Deferred().resolve().promise();
		}

		return post( 'asrekhodro_content_reimport_status', { token: token }, 60000 ).then( function ( response ) {
			if ( cancelRequested ) {
				return;
			}

			if ( ! response || ! response.success ) {
				throw new Error( ( response && response.data && response.data.message ) || 'Content re-import status failed.' );
			}

			var payload = response.data;
			updateBars( payload );

			if ( payload.done ) {
				resetImportControls( true );
				setStatus( akImporter.strings.contentFinished );
				showResult( payload.result );
				return;
			}

			return new Promise( function ( resolve ) {
				window.setTimeout( resolve, 3000 );
			} ).then( function () {
				return pollContentReimport( token );
			} );
		} ).catch( function ( xhr ) {
			if ( cancelRequested || ( xhr && xhr.statusText === 'abort' ) ) {
				return;
			}

			if ( xhr instanceof Error ) {
				throw xhr;
			}

			throw new Error( getErrorMessage( xhr, 'Content re-import status failed.' ) );
		} );
	}

	function startContentReimport() {
		if ( running ) {
			return;
		}

		var postBatchSize = getPostBatchSize();
		var rawBatchSize = parseInt( $( '#ak-import-post-batch' ).val(), 10 );
		if ( isNaN( rawBatchSize ) || rawBatchSize < akImporter.postBatch.min || rawBatchSize > akImporter.postBatch.max ) {
			window.alert( akImporter.strings.invalidBatch );
			return;
		}

		$( '#ak-import-post-batch' ).val( postBatchSize );

		running = true;
		cancelRequested = false;
		showPanel( 'content' );
		setStatus( akImporter.strings.contentStarting );
		$( '#ak-import-result' ).hide();
		$( '#ak-import-log' ).text( '' );
		$( '#ak-import-count-content-updated' ).text( '0' );
		$( '#ak-import-count-content-skipped' ).text( '0' );
		$( '#ak-import-count-content-no-embed' ).text( '0' );

		post( 'asrekhodro_content_reimport_start', { post_batch_size: postBatchSize }, getStepTimeout( postBatchSize ) )
			.then( function ( response ) {
				if ( cancelRequested ) {
					return;
				}

				if ( ! response || ! response.success ) {
					throw new Error( ( response && response.data && response.data.message ) || 'Could not start content re-import.' );
				}

				var data = response.data;
				contentToken = data.token || '';
				updateBars( data );
				setStatus( akImporter.strings.contentStarted );

				return pollContentReimport( contentToken );
			} )
			.catch( function ( error ) {
				if ( cancelRequested || ( error && error.statusText === 'abort' ) ) {
					return;
				}

				resetImportControls( false );
				var message = error && error.message ? error.message : akImporter.strings.contentError;
				setStatus( message );
			} );
	}

	function cancelContentReimport() {
		if ( ! running || cancelRequested || activeMode !== 'content' ) {
			return;
		}

		if ( ! window.confirm( akImporter.strings.confirmContentCancel ) ) {
			return;
		}

		cancelRequested = true;
		$( '#ak-content-reimport-cancel' ).prop( 'disabled', true );
		setStatus( akImporter.strings.contentCancelling );

		if ( activeRequest ) {
			activeRequest.abort();
		}

		post( 'asrekhodro_content_reimport_cancel' )
			.then( function ( response ) {
				var message = akImporter.strings.contentCancelled;
				if ( response && response.success && response.data && response.data.message ) {
					message = response.data.message;
				}

				resetImportControls( false );
				setStatus( message );
			} )
			.catch( function () {
				resetImportControls( false );
				setStatus( akImporter.strings.contentCancelled );
			} );
	}

	function startImport() {
		if ( running ) {
			return;
		}

		var postBatchSize = getPostBatchSize();
		var rawBatchSize = parseInt( $( '#ak-import-post-batch' ).val(), 10 );
		if ( isNaN( rawBatchSize ) || rawBatchSize < akImporter.postBatch.min || rawBatchSize > akImporter.postBatch.max ) {
			window.alert( akImporter.strings.invalidBatch );
			return;
		}

		$( '#ak-import-post-batch' ).val( postBatchSize );

		var resetFlags = getResetFlags();
		if ( Object.keys( resetFlags ).length && ! window.confirm( akImporter.strings.confirmReset ) ) {
			return;
		}

		var stepTimeout = getStepTimeout( postBatchSize );

		running = true;
		cancelRequested = false;
		showPanel( 'full' );
		setStatus( akImporter.strings.starting );
		$( '#ak-import-result' ).hide();
		$( '#ak-import-log' ).text( '' );

		post( 'asrekhodro_import_background_start', $.extend( { post_batch_size: postBatchSize }, resetFlags ), stepTimeout )
			.then( function ( response ) {
				if ( cancelRequested ) {
					return;
				}

				if ( ! response || ! response.success ) {
					throw new Error( ( response && response.data && response.data.message ) || 'Could not start import.' );
				}

				var data = response.data;
				updateBars( {
					phase_label: data.phase_label,
					phase_done: 0,
					phase_total: data.phase === 'reset'
						? Math.max( 1, ( data.reset_total || 1 ) )
						: ( data.totals && data.totals.categories ? data.totals.categories : 0 ),
					overall_done: 0,
					overall_total: data.overall_total || 1,
					phase_percent: 0,
					overall_percent: 0,
					label: akImporter.strings.started,
					counts: { posts: 0, external_media: 0 },
				} );

				return pollBackground( data.token );
			} )
			.catch( function ( error ) {
				if ( cancelRequested || ( error && error.statusText === 'abort' ) ) {
					return;
				}

				resetImportControls( false );
				var message = error && error.message ? error.message : akImporter.strings.error;
				setStatus( message );
			} );
	}

	$( '#ak-import-start' ).on( 'click', function ( event ) {
		event.preventDefault();
		startImport();
	} );

	$( '#ak-import-cancel' ).on( 'click', function ( event ) {
		event.preventDefault();
		cancelImport();
	} );

	$( '#ak-content-reimport-start' ).on( 'click', function ( event ) {
		event.preventDefault();
		startContentReimport();
	} );

	$( '#ak-content-reimport-cancel' ).on( 'click', function ( event ) {
		event.preventDefault();
		cancelContentReimport();
	} );
}( jQuery ) );
