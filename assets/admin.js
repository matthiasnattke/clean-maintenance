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

	$( '#cm-all' ).on( 'change', function () {
		$( '.cm-site' ).prop( 'checked', this.checked );
	} );
} );
