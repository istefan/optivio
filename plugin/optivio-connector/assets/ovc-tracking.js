/*!
 * OptiVio Connector — first-party attribution cookie + tel: click tracking.
 * @author Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 */
( function () {
	'use strict';

	if ( ! window.OVC || ! window.OVC.endpoint ) {
		return;
	}

	var COOKIE = 'ovc_attr';
	var PARAMS = [ 'gclid', 'gbraid', 'wbraid', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ];

	function readCookie() {
		var match = document.cookie.match( /(?:^|;\s*)ovc_attr=([^;]+)/ );
		if ( ! match ) {
			return null;
		}
		try {
			return JSON.parse( decodeURIComponent( escape( atob( match[ 1 ].replace( /-/g, '+' ).replace( /_/g, '/' ) ) ) ) );
		} catch ( e ) {
			return null;
		}
	}

	function writeCookie( data ) {
		var json = unescape( encodeURIComponent( JSON.stringify( data ) ) );
		var value = btoa( json ).replace( /\+/g, '-' ).replace( /\//g, '_' ).replace( /=+$/, '' );
		var expires = new Date( Date.now() + window.OVC.cookieDays * 864e5 ).toUTCString();
		document.cookie = COOKIE + '=' + value + '; expires=' + expires + '; path=/; SameSite=Lax' + ( 'https:' === location.protocol ? '; Secure' : '' );
	}

	function externalReferrer() {
		if ( ! document.referrer ) {
			return '';
		}
		try {
			var host = new URL( document.referrer ).hostname;
			return host === location.hostname ? '' : document.referrer.slice( 0, 500 );
		} catch ( e ) {
			return '';
		}
	}

	// Attribution: last non-direct touch wins; a direct visit never overwrites.
	var current = readCookie();
	var query = new URLSearchParams( location.search );
	var touch = {};
	var hasCampaign = false;
	PARAMS.forEach( function ( key ) {
		var v = query.get( key );
		if ( v ) {
			touch[ key ] = v.slice( 0, 255 );
			hasCampaign = true;
		}
	} );
	var ref = externalReferrer();
	if ( hasCampaign || ref || ! current ) {
		touch.referrer = ref;
		touch.landing_page = ( location.origin + location.pathname ).slice( 0, 500 );
		touch.first_seen = current && current.first_seen ? current.first_seen : new Date().toISOString();
		writeCookie( touch );
		current = touch;
	}

	function uuid() {
		if ( window.crypto && crypto.randomUUID ) {
			return crypto.randomUUID();
		}
		var b = new Uint8Array( 16 );
		crypto.getRandomValues( b );
		b[ 6 ] = ( b[ 6 ] & 0x0f ) | 0x40;
		b[ 8 ] = ( b[ 8 ] & 0x3f ) | 0x80;
		var h = Array.prototype.map.call( b, function ( x ) {
			return ( '0' + x.toString( 16 ) ).slice( -2 );
		} ).join( '' );
		return h.slice( 0, 8 ) + '-' + h.slice( 8, 12 ) + '-' + h.slice( 12, 16 ) + '-' + h.slice( 16, 20 ) + '-' + h.slice( 20 );
	}

	// tel: clicks (event delegation, works for links added later).
	document.addEventListener( 'click', function ( event ) {
		var link = event.target && event.target.closest ? event.target.closest( 'a[href^="tel:"]' ) : null;
		if ( ! link ) {
			return;
		}
		var payload = JSON.stringify( {
			uuid: uuid(),
			phone: decodeURIComponent( link.getAttribute( 'href' ).slice( 4 ) ),
			page_url: location.href.split( '#' )[ 0 ].slice( 0, 500 ),
			attribution: readCookie() || current || {}
		} );
		var sent = false;
		if ( navigator.sendBeacon ) {
			sent = navigator.sendBeacon( window.OVC.endpoint, new Blob( [ payload ], { type: 'application/json' } ) );
		}
		if ( ! sent && window.fetch ) {
			fetch( window.OVC.endpoint, { method: 'POST', body: payload, keepalive: true, headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin' } );
		}
	}, true );
}() );
