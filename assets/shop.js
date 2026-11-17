/**
 * Grow Shop basket. The basket is a list of { id, qty, opts } kept in
 * localStorage; every price comes from the server (/quote), so pages stay
 * cache-safe and the browser's numbers are never trusted.
 */
( function () {
	'use strict';

	var cfg = window.growShop;
	if ( ! cfg ) {
		return;
	}
	var t = cfg.i18n;
	var state = load();
	var quote = null;
	var timer = null;
	var lastFocus = null;
	var busy = false;

	// ---- Storage -------------------------------------------------------

	function load() {
		try {
			var raw = window.localStorage.getItem( cfg.storageKey );
			var s = raw ? JSON.parse( raw ) : null;
			if ( s && Array.isArray( s.items ) ) {
				return s;
			}
		} catch ( e ) {}
		return { items: [], zone: -1, method: -1 };
	}

	function save() {
		try {
			window.localStorage.setItem( cfg.storageKey, JSON.stringify( state ) );
		} catch ( e ) {}
		updateCount();
	}

	function sameOpts( a, b ) {
		return JSON.stringify( norm( a ) ) === JSON.stringify( norm( b ) );
	}

	function norm( o ) {
		var out = {};
		Object.keys( o || {} ).sort().forEach( function ( k ) {
			out[ k ] = parseInt( o[ k ], 10 );
		} );
		return out;
	}

	function count() {
		return state.items.reduce( function ( n, i ) {
			return n + ( parseInt( i.qty, 10 ) || 0 );
		}, 0 );
	}

	function updateCount() {
		var n = count();
		document.querySelectorAll( '[data-grs-count]' ).forEach( function ( el ) {
			el.textContent = n;
			el.hidden = n < 1;
		} );
	}

	// ---- Server --------------------------------------------------------

	function post( path, body ) {
		var headers = { 'Content-Type': 'application/json' };
		if ( cfg.nonce ) {
			headers[ 'X-WP-Nonce' ] = cfg.nonce;
		}
		return fetch( cfg.rest + path, {
			method: 'POST',
			headers: headers,
			body: JSON.stringify( body ),
			credentials: 'same-origin'
		} ).then( function ( r ) {
			return r.json().catch( function () {
				return { ok: false, errors: [ t.error ] };
			} );
		} );
	}

	function refresh() {
		clearTimeout( timer );
		timer = setTimeout( function () {
			post( 'quote', { items: state.items, zone: state.zone, method: state.method } ).then( function ( q ) {
				if ( ! q || ! q.lines ) {
					showErrors( ( q && q.errors ) || [ t.error ] );
					return;
				}
				adopt( q );
				render();
			} ).catch( function () {
				showErrors( [ t.error ] );
			} );
		}, 150 );
	}

	// Take the server's view of the basket: clamped quantities, filled-in
	// options, unavailable items dropped.
	function adopt( q ) {
		quote = q;
		state.items = q.lines.filter( function ( l ) {
			return ! l.remove;
		} ).map( function ( l ) {
			return { id: l.id, qty: l.qty, opts: norm( l.opts ) };
		} );
		state.zone = q.zone;
		state.method = q.method;
		save();
	}

	// ---- Rendering -----------------------------------------------------

	function el( tag, cls, text ) {
		var e = document.createElement( tag );
		if ( cls ) {
			e.className = cls;
		}
		if ( text !== undefined && text !== null ) {
			e.textContent = text;
		}
		return e;
	}

	function baskets() {
		return document.querySelectorAll( '[data-grs-basket]' );
	}

	function render() {
		baskets().forEach( renderOne );
	}

	function renderOne( root ) {
		var q = quote;
		var lines = root.querySelector( '[data-grs-lines]' );
		var empty = root.querySelector( '[data-grs-empty]' );
		var foot = root.querySelector( '[data-grs-foot]' );
		lines.textContent = '';
		var has = q && q.count > 0;
		empty.hidden = !! has;
		foot.hidden = ! has;
		fillList( root.querySelector( '[data-grs-notices]' ), has ? q.notices : [] );
		if ( ! has ) {
			fillList( root.querySelector( '[data-grs-errors]' ), [] );
			return;
		}

		q.lines.forEach( function ( l, index ) {
			if ( l.remove ) {
				return;
			}
			var li = el( 'li', 'grs-line' );
			if ( l.image ) {
				var img = el( 'img', 'grs-line__img' );
				img.src = l.image;
				img.alt = '';
				img.width = 64;
				img.height = 64;
				img.loading = 'lazy';
				li.appendChild( img );
			} else {
				li.appendChild( el( 'span', 'grs-line__img grs-line__img--blank' ) );
			}
			var info = el( 'div', 'grs-line__info' );
			var a = el( 'a', 'grs-line__name', l.name );
			a.href = l.url;
			info.appendChild( a );
			if ( l.options ) {
				info.appendChild( el( 'span', 'grs-line__opts', l.options ) );
			}
			var price = el( 'span', 'grs-line__unit' );
			if ( l.was_text ) {
				price.appendChild( el( 's', '', l.was_text ) );
				price.appendChild( document.createTextNode( ' ' ) );
			}
			price.appendChild( document.createTextNode( l.unit_text + ' ' + t.each ) );
			info.appendChild( price );

			var controls = el( 'div', 'grs-line__controls' );
			var qty = el( 'span', 'grs-qty' );
			var minus = el( 'button', 'grs-step', '−' );
			minus.type = 'button';
			minus.setAttribute( 'aria-label', t.qty + ' -1' );
			var input = el( 'input' );
			input.type = 'number';
			input.min = 0;
			input.max = l.max;
			input.value = l.qty;
			input.setAttribute( 'inputmode', 'numeric' );
			input.setAttribute( 'aria-label', t.qty + ': ' + l.name );
			var plus = el( 'button', 'grs-step', '+' );
			plus.type = 'button';
			plus.setAttribute( 'aria-label', t.qty + ' +1' );
			qty.appendChild( minus );
			qty.appendChild( input );
			qty.appendChild( plus );
			controls.appendChild( qty );
			var remove = el( 'button', 'grs-line__remove', t.remove );
			remove.type = 'button';
			controls.appendChild( remove );
			info.appendChild( controls );
			li.appendChild( info );
			li.appendChild( el( 'span', 'grs-line__total', l.total_text ) );

			var setQty = function ( n ) {
				n = Math.max( 0, Math.min( parseInt( n, 10 ) || 0, l.max ) );
				var item = findItem( l.id, l.opts );
				if ( ! item ) {
					return;
				}
				if ( n < 1 ) {
					state.items.splice( state.items.indexOf( item ), 1 );
				} else {
					item.qty = n;
				}
				save();
				refresh();
			};
			minus.addEventListener( 'click', function () {
				setQty( l.qty - 1 );
			} );
			plus.addEventListener( 'click', function () {
				setQty( l.qty + 1 );
			} );
			input.addEventListener( 'change', function () {
				setQty( input.value );
			} );
			remove.addEventListener( 'click', function () {
				setQty( 0 );
			} );
			lines.appendChild( li );
		} );

		// Postage.
		var zoneSel = root.querySelector( '[data-grs-zone]' );
		var methodSel = root.querySelector( '[data-grs-method]' );
		fillSelect( zoneSel, q.zones.map( function ( z ) {
			return [ z.i, z.name ];
		} ), q.zone );
		fillSelect( methodSel, q.methods.map( function ( m ) {
			return [ m.i, m.name + ' (' + m.cost_text + ')' ];
		} ), q.method );
		root.querySelector( '[data-grs-zone-wrap]' ).hidden = q.zones.length < 2;
		root.querySelector( '[data-grs-method-wrap]' ).hidden = q.methods.length < 2;
		zoneSel.onchange = function () {
			state.zone = parseInt( zoneSel.value, 10 );
			state.method = -1;
			save();
			refresh();
		};
		methodSel.onchange = function () {
			state.method = parseInt( methodSel.value, 10 );
			save();
			refresh();
		};

		root.querySelector( '[data-grs-subtotal]' ).textContent = q.subtotal_text;
		root.querySelector( '[data-grs-shipping]' ).textContent = q.shipping_text;
		root.querySelector( '[data-grs-total]' ).textContent = q.total_text;
		fillList( root.querySelector( '[data-grs-errors]' ), q.errors );
		root.querySelector( '[data-grs-checkout]' ).disabled = ! q.ok || busy;
	}

	function findItem( id, opts ) {
		for ( var i = 0; i < state.items.length; i++ ) {
			if ( state.items[ i ].id === id && sameOpts( state.items[ i ].opts, opts ) ) {
				return state.items[ i ];
			}
		}
		return null;
	}

	function fillSelect( sel, rows, current ) {
		sel.textContent = '';
		rows.forEach( function ( r ) {
			var o = el( 'option', '', r[ 1 ] );
			o.value = r[ 0 ];
			o.selected = r[ 0 ] === current;
			sel.appendChild( o );
		} );
	}

	function fillList( ul, items ) {
		if ( ! ul ) {
			return;
		}
		ul.textContent = '';
		( items || [] ).forEach( function ( text ) {
			ul.appendChild( el( 'li', '', text ) );
		} );
		ul.hidden = ! items || ! items.length;
	}

	function showErrors( errors ) {
		baskets().forEach( function ( root ) {
			fillList( root.querySelector( '[data-grs-errors]' ), errors );
		} );
	}

	function message( text, kind ) {
		baskets().forEach( function ( root ) {
			var m = root.querySelector( '[data-grs-message]' );
			m.textContent = '';
			if ( ! text ) {
				m.hidden = true;
				return;
			}
			( Array.isArray( text ) ? text : [ text ] ).forEach( function ( line ) {
				m.appendChild( el( 'p', '', line ) );
			} );
			m.className = 'grs-basket__message grs-basket__message--' + ( kind || 'info' );
			m.hidden = false;
		} );
	}

	// ---- Panel ---------------------------------------------------------

	function panel() {
		return document.querySelector( '.grs-basket--panel' );
	}

	function open() {
		if ( cfg.style === 'page' && cfg.basketPage && ! document.querySelector( '.grs-basket--inline' ) ) {
			window.location.href = cfg.basketPage;
			return;
		}
		var p = panel();
		if ( ! p ) {
			var inline = document.querySelector( '.grs-basket--inline' );
			if ( inline ) {
				inline.scrollIntoView( { behavior: 'smooth' } );
			}
			return;
		}
		lastFocus = document.activeElement;
		p.hidden = false;
		document.querySelector( '.grs-overlay' ).hidden = false;
		document.documentElement.classList.add( 'grs-open' );
		requestAnimationFrame( function () {
			p.classList.add( 'is-open' );
		} );
		toggles( true );
		var close = p.querySelector( '.grs-basket__close' );
		if ( close ) {
			close.focus();
		}
		refresh();
	}

	function close() {
		var p = panel();
		if ( ! p || p.hidden ) {
			return;
		}
		p.classList.remove( 'is-open' );
		document.documentElement.classList.remove( 'grs-open' );
		document.querySelector( '.grs-overlay' ).hidden = true;
		toggles( false );
		setTimeout( function () {
			p.hidden = true;
		}, 250 );
		if ( lastFocus && lastFocus.focus ) {
			lastFocus.focus();
		}
	}

	function toggles( expanded ) {
		document.querySelectorAll( '.grs-basket-toggle' ).forEach( function ( b ) {
			b.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
		} );
	}

	// ---- Add to basket -------------------------------------------------

	function onAdd( form ) {
		var id = parseInt( form.getAttribute( 'data-grs-id' ), 10 );
		var qtyInput = form.querySelector( '[data-grs-qty]' );
		var qty = qtyInput ? Math.max( 1, parseInt( qtyInput.value, 10 ) || 1 ) : 1;
		var opts = {};
		form.querySelectorAll( '[data-grs-group]' ).forEach( function ( s ) {
			opts[ s.getAttribute( 'data-grs-group' ) ] = parseInt( s.value, 10 );
		} );
		var item = findItem( id, opts );
		if ( item ) {
			item.qty += qty;
		} else {
			state.items.push( { id: id, qty: qty, opts: norm( opts ) } );
		}
		save();
		var done = form.querySelector( '.grs-add__done' );
		if ( done ) {
			done.textContent = t.added;
			setTimeout( function () {
				done.textContent = '';
			}, 4000 );
		}
		message( '' );
		open();
	}

	// ---- Checkout ------------------------------------------------------

	function checkout( button ) {
		if ( busy ) {
			return;
		}
		busy = true;
		button.disabled = true;
		var label = button.textContent;
		button.textContent = t.redirect;
		post( 'checkout', { items: state.items, zone: state.zone, method: state.method, 'return': window.location.href } ).then( function ( r ) {
			if ( r && r.ok && r.url ) {
				window.location.href = r.url;
				return;
			}
			busy = false;
			button.textContent = label;
			if ( r && r.quote ) {
				adopt( r.quote );
				render();
			}
			showErrors( ( r && r.errors ) || [ t.error ] );
			button.disabled = false;
		} ).catch( function () {
			busy = false;
			button.textContent = label;
			button.disabled = false;
			showErrors( [ t.error ] );
		} );
	}

	// ---- Coming back from Stripe ---------------------------------------

	function handleReturn() {
		var params = new URLSearchParams( window.location.search );
		var flag = params.get( 'grs' );
		if ( ! flag ) {
			return;
		}
		var session = params.get( 'grs_session' );
		params.delete( 'grs' );
		params.delete( 'grs_session' );
		var clean = window.location.pathname + ( params.toString() ? '?' + params.toString() : '' ) + window.location.hash;
		window.history.replaceState( null, '', clean );

		if ( flag === 'cancelled' ) {
			message( cfg.cancelled, 'info' );
			open();
			return;
		}
		if ( flag === 'thanks' && session ) {
			message( t.checking, 'info' );
			state.items = [];
			save();
			open();
			var tries = 0;
			var ask = function () {
				post( 'confirm', { session: session } ).then( function ( r ) {
					if ( r && r.ok ) {
						message( [ r.message, t.orderNo.replace( '%s', r.number ) ], 'success' );
					} else if ( tries++ < 3 ) {
						setTimeout( ask, 2500 );
					} else {
						message( t.notYet, 'info' );
					}
				} ).catch( function () {
					message( t.notYet, 'info' );
				} );
			};
			ask();
		}
	}

	// ---- Wiring --------------------------------------------------------

	document.addEventListener( 'submit', function ( e ) {
		var form = e.target.closest( '.grs-add' );
		if ( form ) {
			e.preventDefault();
			onAdd( form );
		}
	} );

	document.addEventListener( 'click', function ( e ) {
		var step = e.target.closest( '.grs-add [data-grs-step]' );
		if ( step ) {
			var input = step.parentNode.querySelector( '[data-grs-qty]' );
			var max = parseInt( input.max, 10 ) || 99;
			input.value = Math.max( 1, Math.min( max, ( parseInt( input.value, 10 ) || 1 ) + parseInt( step.getAttribute( 'data-grs-step' ), 10 ) ) );
			return;
		}
		if ( e.target.closest( '.grs-basket-toggle' ) ) {
			e.preventDefault();
			var p = panel();
			if ( p && ! p.hidden ) {
				close();
			} else {
				message( '' );
				open();
			}
			return;
		}
		if ( e.target.closest( '[data-grs-close]' ) ) {
			close();
			return;
		}
		var go = e.target.closest( '[data-grs-checkout]' );
		if ( go ) {
			checkout( go );
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Escape' ) {
			close();
		}
	} );

	// Another tab changed the basket.
	window.addEventListener( 'storage', function ( e ) {
		if ( e.key === cfg.storageKey ) {
			state = load();
			updateCount();
			if ( document.querySelector( '.grs-basket--inline' ) || ( panel() && ! panel().hidden ) ) {
				refresh();
			}
		}
	} );

	updateCount();
	if ( document.querySelector( '.grs-basket--inline' ) ) {
		refresh();
	}
	handleReturn();
}() );
