( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var list = document.getElementById( 'dabar-messages' );
		var template = document.getElementById( 'dabar-message-template' );
		var add = document.getElementById( 'dabar-add-message' );

		if ( list && template && add ) {
			add.addEventListener( 'click', function () {
				var row = template.content.firstElementChild.cloneNode( true );
				list.appendChild( row );
				row.querySelector( 'textarea' ).focus();
			} );

			list.addEventListener( 'click', function ( event ) {
				var button = event.target.closest( '.dabar-remove-message' );
				if ( ! button ) {
					return;
				}

				var row = button.closest( '.dabar-message-row' );
				if ( list.querySelectorAll( '.dabar-message-row' ).length > 1 ) {
					row.remove();
				} else {
					row.querySelector( 'textarea' ).value = '';
				}
			} );
		}

		document.querySelectorAll( '.dabar-color' ).forEach( function ( field ) {
			var picker = field.querySelector( 'input[type="color"]' );
			var text = field.querySelector( 'input[type="text"]' );

			picker.addEventListener( 'input', function () {
				text.value = picker.value;
			} );

			text.addEventListener( 'input', function () {
				var value = text.value.trim();
				if ( /^#[0-9a-f]{6}$/i.test( value ) ) {
					picker.value = value;
				}
			} );
		} );
	} );
} )();
