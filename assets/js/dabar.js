/**
 * Definitive Announcement Bar: rotation, dismissal and scheduling.
 */
( function () {
	'use strict';

	var STORAGE_KEY = 'dabar_dismissed';

	function remember( version, days ) {
		try {
			if ( days > 0 ) {
				window.localStorage.setItem( STORAGE_KEY, version + '|' + ( Date.now() + days * 864e5 ) );
			} else {
				window.sessionStorage.setItem( STORAGE_KEY, version );
			}
		} catch ( e ) {}
	}

	function init() {
		var bar = document.getElementById( 'dabar' );
		if ( ! bar ) {
			return;
		}

		var root = document.documentElement;
		var now = Date.now() / 1000;
		var start = parseInt( bar.getAttribute( 'data-start' ), 10 ) || 0;
		var end = parseInt( bar.getAttribute( 'data-end' ), 10 ) || 0;

		// Also covers full-page caches that serve markup generated before/after the schedule.
		if ( root.classList.contains( 'dabar-dismissed' ) || ( end && now >= end ) ) {
			bar.parentNode.removeChild( bar );
			return;
		}

		if ( bar.hasAttribute( 'data-relocate' ) ) {
			document.body.insertBefore( bar, document.body.firstChild );
			bar.removeAttribute( 'data-relocate' );
		}

		if ( bar.hidden ) {
			if ( start && now >= start ) {
				bar.hidden = false;
			} else {
				return;
			}
		}

		function updateHeight() {
			root.style.setProperty( '--dabar-height', ( bar.isConnected ? bar.offsetHeight : 0 ) + 'px' );
		}

		updateHeight();
		if ( 'ResizeObserver' in window ) {
			new ResizeObserver( updateHeight ).observe( bar );
		} else {
			window.addEventListener( 'resize', updateHeight );
		}
		document.body.classList.add( 'dabar-active' );
		if ( bar.classList.contains( 'dabar--bottom' ) ) {
			root.classList.add( 'dabar-bottom' );
		}

		// Rotation.
		var messages = bar.querySelectorAll( '.dabar__message' );
		var interval = Math.max( 1000, parseInt( bar.getAttribute( 'data-interval' ), 10 ) || 5000 );
		var index = 0;
		var paused = false;
		var timer = null;

		function show( next ) {
			messages[ index ].classList.remove( 'is-active' );
			messages[ index ].setAttribute( 'aria-hidden', 'true' );
			index = ( next + messages.length ) % messages.length;
			messages[ index ].classList.add( 'is-active' );
			messages[ index ].removeAttribute( 'aria-hidden' );
		}

		function startTimer() {
			window.clearInterval( timer );
			timer = window.setInterval( function () {
				if ( ! paused && ! document.hidden ) {
					show( index + 1 );
				}
			}, interval );
		}

		if ( messages.length > 1 ) {
			startTimer();

			bar.addEventListener( 'mouseenter', function () { paused = true; } );
			bar.addEventListener( 'mouseleave', function () { paused = false; } );
			bar.addEventListener( 'focusin', function () { paused = true; } );
			bar.addEventListener( 'focusout', function () { paused = false; } );
		}

		// Dismissal.
		var close = bar.querySelector( '.dabar__close' );
		if ( ! close ) {
			return;
		}

		close.addEventListener( 'click', function () {
			var finished = false;

			function finish() {
				if ( finished ) {
					return;
				}
				finished = true;
				if ( bar.parentNode ) {
					bar.parentNode.removeChild( bar );
				}
				root.style.setProperty( '--dabar-height', '0px' );
				root.classList.remove( 'dabar-bottom' );
				document.dispatchEvent( new CustomEvent( 'dabar:dismissed' ) );
			}

			window.clearInterval( timer );
			remember( bar.getAttribute( 'data-version' ) || '', parseInt( bar.getAttribute( 'data-dismiss-days' ), 10 ) || 0 );
			document.body.classList.remove( 'dabar-active' );

			bar.style.height = bar.offsetHeight + 'px';
			void bar.offsetHeight; // Force layout so the height transition runs.
			bar.classList.add( 'is-closing' );
			bar.style.height = '0px';

			bar.addEventListener( 'transitionend', finish );
			window.setTimeout( finish, 400 );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
