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
} );
