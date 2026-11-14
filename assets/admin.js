/* Clean Maintenance – settings page. */
jQuery( function ( $ ) {
	var l10n = window.cleanMaintenance || {};

	function paintSwatch( color ) {
		$( '.cm-bg-swatch' ).css( 'background', color || '' );
	}
	if ( $.fn.wpColorPicker ) {
		$( '.cm-color' ).not( '#cm-bg-color' ).wpColorPicker();
		$( '#cm-bg-color' ).wpColorPicker( {
			change: function ( e, ui ) { paintSwatch( ui.color.toString() ); },
			clear: function () { paintSwatch( '' ); }
		} );
	}
	paintSwatch( $( '#cm-bg-color' ).val() );

	function syncWhen() {
		$( '.cm-when' ).toggle( $( 'input[name="clean_maintenance[mode]"]:checked' ).val() === 'scheduled' );
	}
	$( 'input[name="clean_maintenance[mode]"]' ).on( 'change', syncWhen );
	syncWhen();

	function syncDesign() {
		var classic = $( 'input[name="clean_maintenance[design]"]:checked' ).val() === 'classic';
		$( '.cm-classic' ).toggle( classic );
		$( '.cm-page-row' ).toggle( ! classic );
	}
	$( 'input[name="clean_maintenance[design]"]' ).on( 'change', syncDesign );
	syncDesign();

	$( '#cm-bg-pick' ).on( 'click', function ( e ) {
		e.preventDefault();
		var frame = wp.media( {
			title: l10n.pickTitle,
			button: { text: l10n.pickButton },
			library: { type: 'image' },
			multiple: false
		} );
		frame.on( 'select', function () {
			var a = frame.state().get( 'selection' ).first().toJSON();
			$( '#cm-bg-image' ).val( a.id );
			$( '#cm-bg-preview' ).attr( 'src', a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url ).removeClass( 'hidden' );
			$( '#cm-bg-remove' ).removeClass( 'hidden' );
		} );
		frame.open();
	} );

	$( '#cm-bg-remove' ).on( 'click', function ( e ) {
		e.preventDefault();
		$( '#cm-bg-image' ).val( '' );
		$( '#cm-bg-preview' ).addClass( 'hidden' );
		$( this ).addClass( 'hidden' );
	} );

	$( '.cm-ip-add' ).on( 'click', function () {
		var $t = $( '#' + $( this ).data( 'target' ) );
		var ip = String( $( this ).data( 'ip' ) );
		var v = $t.val().replace( /\s+$/, '' );
		if ( ( '\n' + v + '\n' ).indexOf( '\n' + ip ) === -1 ) {
			$t.val( ( v ? v + '\n' : '' ) + ip ).trigger( 'change' ).focus();
		}
	} );

	/* Copy the router link. */
	$( '.cm-copy-btn' ).on( 'click', function () {
		var $b = $( this );
		var el = document.getElementById( $b.data( 'target' ) );
		var done = function () {
			var label = $b.text();
			$b.text( $b.data( 'done' ) );
			setTimeout( function () { $b.text( label ); }, 1600 );
		};
		el.select();
		if ( navigator.clipboard ) {
			navigator.clipboard.writeText( el.value ).then( done );
		} else {
			document.execCommand( 'copy' );
			done();
		}
	} );

	/* Home address: a pasted URL such as https://abc.myfritz.net:42243/ becomes abc.myfritz.net. */
	function cmHost( value ) {
		return String( value ).trim().toLowerCase()
			.replace( /^[a-z][a-z0-9+.-]*:\/\//, '' )
			.replace( /^[^@\/]*@/, '' )
			.replace( /[\/?#].*$/, '' )
			.replace( /:\d*$/, '' )
			.replace( /\.+$/, '' );
	}
	$( '#cm-network-host' ).on( 'paste change blur', function () {
		var $f = $( this );
		setTimeout( function () {
			var before = $f.val();
			var after = cmHost( before );
			if ( after !== before.trim() ) {
				$f.val( after );
				$( '#cm-host-note' ).text( $( '#cm-host-note' ).data( 'text' ) ).prop( 'hidden', false );
			}
		}, 0 );
	} );

	/* Tunnel guide: pops up as soon as private ranges are entered while the request arrives from a public address. */
	var $tunnel = $( '#cm-tunnel' );
	var privateRe = /^\s*(10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.|f[cd][0-9a-f]{2}:|fe80:)/im;
	function cmTunnel( force ) {
		var show = force || ( $tunnel.data( 'public' ) === 1 && privateRe.test( $( '#cm-network-allow' ).val() || '' ) );
		if ( show && $tunnel.prop( 'hidden' ) ) {
			$tunnel.prop( 'hidden', false ).addClass( 'cm-pop' );
			$( '#cm-tunnel-toggle' ).prop( 'hidden', true );
		}
	}
	$( '#cm-network-allow' ).on( 'input change', function () { cmTunnel( false ); } );
	$( '#cm-tunnel-toggle' ).on( 'click', function () { cmTunnel( true ); } );

	$( '#cm-all' ).on( 'change', function () {
		$( '.cm-site:visible .cm-site-check' ).prop( 'checked', this.checked );
	} );

	/* Turning on and off without reloading. Without JavaScript or on an error the links and
	 * the form work as before. */
	function announce( text ) {
		var $live = $( '#cm-live' );
		if ( ! $live.length ) {
			$live = $( '<p id="cm-live" class="screen-reader-text" aria-live="polite"></p>' ).appendTo( 'body' );
		}
		$live.text( text );
	}

	$( document ).on( 'click', '[data-cm-site][data-cm-mode]', function ( e ) {
		if ( ! l10n.ajax ) {
			return;
		}
		e.preventDefault();
		var $b = $( this ), href = $b.attr( 'href' );
		var context = $b.closest( '.cm-widget' ).length ? 'dashboard' : 'overview';
		if ( $b.hasClass( 'is-busy' ) ) {
			return;
		}
		$b.addClass( 'is-busy' ).attr( 'aria-busy', 'true' );
		$.post( l10n.ajax, {
			action: 'clean_maintenance_toggle',
			_ajax_nonce: l10n.nonce,
			site: $b.attr( 'data-cm-site' ),
			mode: $b.attr( 'data-cm-mode' ),
			context: context
		} ).done( function ( r ) {
			if ( ! r || ! r.success ) {
				window.location.href = href;
				return;
			}
			if ( 'dashboard' === context ) {
				$b.closest( '.cm-widget' ).replaceWith( r.data.widget );
			} else {
				$b.closest( '.cm-site' ).replaceWith( r.data.card );
				$.each( r.data.counts, function ( key, n ) {
					$( '[data-cm-count="' + key + '"]' ).text( n );
				} );
			}
			announce( l10n.saved );
		} ).fail( function () {
			window.location.href = href;
		} );
	} );

	$( document ).on( 'submit', '.cm-site-widget', function ( e ) {
		if ( ! l10n.ajax ) {
			return;
		}
		e.preventDefault();
		var form = this, $f = $( form ), $btn = $f.find( '[type="submit"]' );
		var data = $.grep( $f.serializeArray(), function ( f ) {
			return 'action' !== f.name;
		} );
		data.push( { name: 'action', value: 'clean_maintenance_widget_save' } );
		$btn.prop( 'disabled', true );
		$.post( l10n.ajax, $.param( data ) ).done( function ( r ) {
			if ( ! r || ! r.success ) {
				form.submit();
				return;
			}
			$f.find( '.cm-widget-state' ).replaceWith( r.data.pill );
			var $msg = $f.find( '.cm-widget-saved' );
			if ( ! $msg.length ) {
				$msg = $( '<p class="cm-widget-saved"></p>' ).insertAfter( $f.find( '.cm-widget-state' ) );
			}
			$msg.text( l10n.saved ).prop( 'hidden', false );
			if ( ! r.data.active ) {
				$( '#wp-admin-bar-cm-maintenance' ).remove();
			}
			announce( l10n.saved );
		} ).fail( function () {
			form.submit();
		} ).always( function () {
			$btn.prop( 'disabled', false );
		} );
	} );

	/* The quick switch in the admin bar turned maintenance off: show it in the widget too. */
	document.addEventListener( 'clean-maintenance-off', function ( e ) {
		var $w = $( '.cm-site-widget' );
		if ( $w.length && e.detail && e.detail.pill ) {
			$w.find( '.cm-widget-state' ).replaceWith( e.detail.pill );
			$w.find( 'input[name="mode"][value="off"]' ).prop( 'checked', true );
		}
		announce( l10n.saved );
	} );
} );
