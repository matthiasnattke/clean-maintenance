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

	/* Maintenance page in the widget: a search instead of a long list. The select stays the
	 * field that is saved, so the form works the same with and without this. */
	$( '.cm-page-picker' ).each( function () {
		if ( ! l10n.pages || ! window.fetch ) {
			return;
		}
		var $box = $( this ), $select = $box.find( 'select' ), listId = 'cm-page-list';
		var $field = $( '<div class="cm-picker-field"></div>' );
		var $input = $( '<input type="search" class="cm-picker-input" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false">' )
			.attr( { id: $select.attr( 'id' ), placeholder: l10n.search, 'aria-controls': listId } );
		var $state = $( '<span class="cm-picker-state"></span>' );
		var $list = $( '<ul class="cm-picker-list" role="listbox" hidden></ul>' ).attr( 'id', listId );
		$select.removeAttr( 'id' ).attr( { hidden: true, 'aria-hidden': 'true', tabindex: -1 } );
		$field.append( $input, $state );
		$box.append( $field, $list );

		function stateOf( text ) {
			var m = /\(([^)]+)\)$/.exec( text || '' );
			return m ? m[ 1 ] : '';
		}
		function showCurrent() {
			var $o = $select.find( 'option:selected' ), text = $o.text();
			var st = '0' === $select.val() ? '' : stateOf( text );
			$input.val( '0' === $select.val() ? '' : text.replace( /\s*\([^)]+\)$/, '' ) );
			$input.attr( 'placeholder', '0' === $select.val() ? l10n.simple : l10n.search );
			$state.text( st ).prop( 'hidden', ! st );
		}
		function close() {
			$list.prop( 'hidden', true ).empty();
			$input.attr( 'aria-expanded', 'false' ).removeAttr( 'aria-activedescendant' );
		}
		function choose( id, title, status ) {
			var label = title + ( status && 'publish' !== status ? ' (' + ( l10n.states[ status ] || status ) + ')' : '' );
			if ( ! $select.find( 'option[value="' + id + '"]' ).length ) {
				$select.append( $( '<option></option>' ).val( id ).text( label ) );
			}
			$select.val( String( id ) ).trigger( 'change' );
			showCurrent();
			close();
		}
		function render( pages, heading ) {
			$list.empty();
			var add = function ( id, title, status, extra ) {
				var $li = $( '<li role="option" class="cm-picker-option"></li>' ).attr( 'id', 'cm-page-opt-' + id ).data( 'page', { id: id, title: title, status: status } );
				$li.append( $( '<span></span>' ).text( title ) );
				if ( status && 'publish' !== status ) {
					$li.append( $( '<em></em>' ).text( l10n.states[ status ] || status ) );
				}
				if ( extra ) {
					$li.addClass( extra );
				}
				if ( String( id ) === $select.val() ) {
					$li.attr( 'aria-selected', 'true' );
				}
				$list.append( $li );
			};
			add( 0, l10n.simple, '', 'cm-picker-simple' );
			if ( heading ) {
				$list.append( $( '<li class="cm-picker-head" role="presentation"></li>' ).text( heading ) );
			}
			if ( ! pages.length ) {
				$list.append( $( '<li class="cm-picker-note" role="presentation"></li>' ).text( l10n.none ) );
			}
			$.each( pages, function ( i, pg ) {
				var t = $( '<textarea>' ).html( pg.title && pg.title.rendered ? pg.title.rendered : '#' + pg.id ).text();
				add( pg.id, t, pg.status );
			} );
			$list.prop( 'hidden', false );
			$input.attr( 'aria-expanded', 'true' );
		}
		var timer, last = null;
		function search() {
			var q = $.trim( $input.val() );
			if ( q === last && ! $list.prop( 'hidden' ) ) {
				return;
			}
			last = q;
			var url = l10n.pages + ( l10n.pages.indexOf( '?' ) === -1 ? '?' : '&' ) + 'per_page=8&status=publish,private,draft,pending,future&_fields=id,title,status&context=edit'
				+ ( q ? '&search=' + encodeURIComponent( q ) : '&orderby=modified&order=desc' );
			fetch( url, { credentials: 'same-origin', headers: { 'X-WP-Nonce': l10n.restNonce } } )
				.then( function ( r ) { return r.ok ? r.json() : []; } )
				.then( function ( pages ) {
					if ( q === $.trim( $input.val() ) ) {
						render( pages || [], q ? '' : l10n.recent );
					}
				} )
				.catch( function () { render( [], '' ); } );
		}
		$input.on( 'focus', function () {
			$input.select();
			last = null;
			$input.val( '' );
			search();
		} ).on( 'input', function () {
			clearTimeout( timer );
			timer = setTimeout( search, 200 );
		} ).on( 'keydown', function ( e ) {
			var $opts = $list.find( '.cm-picker-option' ), $cur = $opts.filter( '.is-active' ), i = $opts.index( $cur );
			if ( 'ArrowDown' === e.key || 'ArrowUp' === e.key ) {
				e.preventDefault();
				if ( $list.prop( 'hidden' ) ) {
					search();
					return;
				}
				i = 'ArrowDown' === e.key ? Math.min( i + 1, $opts.length - 1 ) : Math.max( i - 1, 0 );
				$opts.removeClass( 'is-active' );
				var $n = $opts.eq( i ).addClass( 'is-active' );
				$input.attr( 'aria-activedescendant', $n.attr( 'id' ) );
				$n[ 0 ].scrollIntoView( { block: 'nearest' } );
			} else if ( 'Enter' === e.key && $cur.length ) {
				e.preventDefault();
				var d = $cur.data( 'page' );
				choose( d.id, d.title, d.status );
			} else if ( 'Escape' === e.key ) {
				close();
				showCurrent();
			}
		} ).on( 'blur', function () {
			setTimeout( function () {
				if ( ! $box[ 0 ].contains( document.activeElement ) ) {
					close();
					showCurrent();
				}
			}, 150 );
		} );
		$list.on( 'mousedown', '.cm-picker-option', function ( e ) {
			e.preventDefault();
			var d = $( this ).data( 'page' );
			choose( d.id, d.title, d.status );
			$input.trigger( 'blur' );
		} );
		showCurrent();
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
