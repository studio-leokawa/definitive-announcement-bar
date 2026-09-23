( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var list = document.getElementById( 'dabar-messages' );
		var template = document.getElementById( 'dabar-message-template' );
		var add = document.getElementById( 'dabar-add-message' );

		if ( list && template && add ) {
			list.querySelectorAll( 'textarea' ).forEach( setupEditor );

			add.addEventListener( 'click', function () {
				var row = template.content.firstElementChild.cloneNode( true );
				var textarea = row.querySelector( 'textarea' );
				list.appendChild( row );
				setupEditor( textarea );

				var editor = getEditor( textarea );
				if ( editor ) {
					editor.focus();
				} else {
					textarea.focus();
				}
			} );

			list.addEventListener( 'click', function ( event ) {
				var button = event.target.closest( '.dabar-remove-message' );
				if ( ! button ) {
					return;
				}

				var row = button.closest( '.dabar-message-row' );
				var textarea = row.querySelector( 'textarea' );

				if ( list.querySelectorAll( '.dabar-message-row' ).length > 1 ) {
					if ( window.wp && window.wp.editor && window.wp.editor.remove ) {
						window.wp.editor.remove( textarea.id );
					}
					row.remove();
				} else {
					var editor = getEditor( textarea );
					if ( editor ) {
						editor.setContent( '' );
					}
					textarea.value = '';
					textarea.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				}
			} );

			// Make sure the latest editor content is in the textareas when saving.
			list.closest( 'form' ).addEventListener( 'submit', function () {
				if ( window.tinymce ) {
					window.tinymce.triggerSave();
				}
			} );
		}

		var buttonText = document.getElementById( 'dabar-button_text' );
		if ( buttonText ) {
			var toggleButtonSettings = function () {
				document.querySelectorAll( '.dabar-requires-button' ).forEach( function ( row ) {
					row.hidden = buttonText.value.trim() === '';
				} );
			};
			buttonText.addEventListener( 'input', toggleButtonSettings );
			toggleButtonSettings();
		}

		var placement = document.getElementById( 'dabar-placement' );
		if ( placement ) {
			var toggleTopSettings = function () {
				document.querySelectorAll( '.dabar-requires-top' ).forEach( function ( row ) {
					row.hidden = placement.value === 'bottom';
				} );
			};
			placement.addEventListener( 'change', toggleTopSettings );
			toggleTopSettings();
		}

		var dismissible = document.getElementById( 'dabar-dismissible' );
		if ( dismissible ) {
			var toggleCloseSettings = function () {
				document.querySelectorAll( '.dabar-requires-dismissible' ).forEach( function ( row ) {
					row.hidden = ! dismissible.checked;
				} );
			};
			dismissible.addEventListener( 'change', toggleCloseSettings );
			toggleCloseSettings();
		}

		document.querySelectorAll( '.dabar-color' ).forEach( function ( field ) {
			var picker = field.querySelector( 'input[type="color"]' );
			var text = field.querySelector( 'input[type="text"]' );

			picker.addEventListener( 'input', function () {
				text.value = picker.value;
				field.classList.remove( 'is-empty' );
			} );

			text.addEventListener( 'input', function () {
				var value = text.value.trim();
				field.classList.toggle( 'is-empty', value === '' );
				if ( /^#[0-9a-f]{6}$/i.test( value ) ) {
					picker.value = value;
				}
			} );
		} );

		initPreview();
	} );

	var editorCount = 0;

	function getEditor( textarea ) {
		return window.tinymce && textarea.id ? window.tinymce.get( textarea.id ) : null;
	}

	// Mirrors the textarea events, so the preview follows the visual editor too.
	function bindEditor( editor ) {
		var textarea = editor.getElement();

		editor.on( 'input keyup change undo redo ExecCommand', function () {
			editor.save();
			textarea.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		} );
		editor.on( 'focus', function () {
			textarea.dispatchEvent( new FocusEvent( 'focusin', { bubbles: true } ) );
		} );
		editor.on( 'blur', function () {
			textarea.dispatchEvent( new FocusEvent( 'focusout', { bubbles: true } ) );
		} );
	}

	// Turns a message textarea into a small WordPress editor with the formatting the bar allows.
	function setupEditor( textarea ) {
		if ( ! window.wp || ! window.wp.editor || ! window.wp.editor.initialize ) {
			return;
		}

		if ( ! textarea.id ) {
			do {
				editorCount++;
			} while ( document.getElementById( 'dabar-message-' + editorCount ) );
			textarea.id = 'dabar-message-' + editorCount;
		}

		window.wp.editor.initialize( textarea.id, {
			tinymce: {
				wpautop: false,
				indent: false,
				forced_root_block: false,
				height: 60,
				min_height: 60,
				wp_autoresize_on: false,
				menubar: false,
				content_style: 'body.mce-content-body { margin: 8px 10px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 14px; line-height: 1.5; }',
				toolbar1: 'bold,italic,underline,strikethrough,link,unlink,undo,redo',
				toolbar2: '',
				valid_elements: 'a[href|title|target|rel|class],strong/b,em/i,u,s/strike,small,br,span[class]',
				formats: {
					underline: { inline: 'u' },
					strikethrough: { inline: 's' }
				},
				setup: bindEditor
			},
			quicktags: {
				buttons: 'strong,em,link'
			},
			mediaButtons: false
		} );
	}

	var CLOSE_ICON = '<svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true" focusable="false"><path d="M13.5 4.5l-9 9M4.5 4.5l9 9" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path></svg>';

	// Mirrors DABAR_Settings::message_allowed_html().
	var ALLOWED_TAGS = {
		A: [ 'href', 'title', 'target', 'rel', 'class' ],
		B: [],
		STRONG: [],
		EM: [],
		I: [],
		U: [],
		S: [],
		SMALL: [],
		BR: [],
		SPAN: [ 'class' ]
	};

	// Keeps allowed tags and the text of everything else, like wp_kses() on save.
	function cleanNode( parent ) {
		Array.prototype.slice.call( parent.childNodes ).forEach( function ( node ) {
			if ( node.nodeType === 3 ) {
				return;
			}

			if ( node.nodeType !== 1 || ! ALLOWED_TAGS[ node.nodeName ] ) {
				if ( node.nodeType === 1 ) {
					cleanNode( node );
					while ( node.firstChild ) {
						parent.insertBefore( node.firstChild, node );
					}
				}
				parent.removeChild( node );
				return;
			}

			Array.prototype.slice.call( node.attributes ).forEach( function ( attr ) {
				var allowed = ALLOWED_TAGS[ node.nodeName ].indexOf( attr.name ) !== -1;
				if ( ! allowed || ( 'href' === attr.name && /^\s*(javascript|data|vbscript):/i.test( attr.value ) ) ) {
					node.removeAttribute( attr.name );
				}
			} );

			cleanNode( node );
		} );
	}

	function initPreview() {
		var host = document.getElementById( 'dabar-preview' );
		var form = host && host.closest( 'form' );
		if ( ! form ) {
			return;
		}

		var defaults = JSON.parse( host.getAttribute( 'data-defaults' ) || '{}' );
		var note = document.getElementById( 'dabar-preview-note' );
		var timer = null;
		var index = 0;
		var focused = -1;
		var hovered = false;
		var interval = 0;

		function field( key ) {
			return form.elements[ 'dabar_settings[' + key + ']' ];
		}

		function value( key ) {
			var input = field( key );
			return input ? input.value.trim() : '';
		}

		function isChecked( key ) {
			var input = field( key );
			return !! ( input && input.checked );
		}

		function clamp( key, min, max ) {
			var number = parseInt( value( key ), 10 );
			return isNaN( number ) ? defaults[ key ] : Math.min( max, Math.max( min, number ) );
		}

		// Same rules as DABAR_Settings::sanitize_color() and sanitize_css_value().
		function color( key ) {
			var raw = value( key );
			var valid = /^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i.test( raw ) ||
				/^(rgba?|hsla?|oklch|oklab)\([0-9a-z%.,\s\/+-]*\)$/i.test( raw ) ||
				/^var\(--[a-z0-9_-]+\)$/i.test( raw ) ||
				/^[a-z]{3,30}$/i.test( raw );
			return valid ? raw : defaults[ key ];
		}

		function cssValue( key ) {
			var raw = value( key );
			return raw && raw.length <= 100 && /^[a-z0-9.%\s()+*\/,_-]+$/i.test( raw ) ? raw : defaults[ key ];
		}

		// Same rules as DABAR_Settings::sanitize_font_family().
		function fontFamily() {
			var raw = value( 'font_family' );
			var quotes = function ( quote ) {
				return raw.split( quote ).length - 1;
			};
			var valid = raw.length <= 200 &&
				/^(?:[a-z0-9\s,"'._-]|var\(--[a-z0-9_-]+\))+$/i.test( raw ) &&
				quotes( '"' ) % 2 === 0 &&
				quotes( "'" ) % 2 === 0;
			return valid ? raw : '';
		}

		function messageFields() {
			return Array.prototype.slice.call( form.querySelectorAll( '#dabar-messages textarea' ) ).filter( function ( textarea ) {
				return textarea.value.trim() !== '';
			} );
		}

		function showMessage( next ) {
			var messages = host.querySelectorAll( '.dabar__message' );
			if ( ! messages[ next ] ) {
				return;
			}
			if ( messages[ index ] ) {
				messages[ index ].classList.remove( 'is-active' );
			}
			index = next;
			messages[ index ].classList.add( 'is-active' );
		}

		function render() {
			var textareas = messageFields();
			var enabled = isChecked( 'enabled' );
			var dismissible = isChecked( 'dismissible' );

			var bar = document.createElement( 'div' );
			bar.id = 'dabar';
			bar.className = 'dabar' + ( isChecked( 'uppercase' ) ? ' dabar--uppercase' : '' ) + ( dismissible ? ' dabar--dismissible' : '' ) + ( value( 'align' ) === 'start' ? ' dabar--align-start' : '' );
			bar.style.setProperty( '--dabar-link-color', color( 'link_color' ) );
			bar.style.setProperty( '--dabar-bg', color( 'background_color' ) );
			bar.style.setProperty( '--dabar-color', color( 'text_color' ) );
			bar.style.setProperty( '--dabar-close-color', color( 'close_color' ) );
			bar.style.setProperty( '--dabar-font-size', cssValue( 'font_size' ) );
			bar.style.setProperty( '--dabar-padding', cssValue( 'padding' ) );
			bar.style.setProperty( '--dabar-font-family', fontFamily() );
			bar.style.setProperty( '--dabar-min-height', cssValue( 'height' ) );
			bar.style.setProperty( '--dabar-button-bg', color( 'button_bg' ) );
			bar.style.setProperty( '--dabar-button-color', color( 'button_color' ) );
			bar.style.setProperty( '--dabar-fade', clamp( 'fade_duration', 0, 5000 ) + 'ms' );

			var inner = document.createElement( 'div' );
			inner.className = 'dabar__inner';
			var content = document.createElement( 'div' );
			content.className = 'dabar__content';
			var list = document.createElement( 'div' );
			list.className = 'dabar__messages';

			if ( ! textareas.length ) {
				var empty = document.createElement( 'div' );
				empty.className = 'dabar__message is-active';
				empty.textContent = host.getAttribute( 'data-empty' );
				list.appendChild( empty );
			}

			textareas.forEach( function ( textarea ) {
				var doc = document.implementation.createHTMLDocument( '' );
				var message = document.createElement( 'div' );
				message.className = 'dabar__message';

				doc.body.innerHTML = textarea.value.trim().replace( /\r?\n/g, '<br>' );
				cleanNode( doc.body );
				while ( doc.body.firstChild ) {
					message.appendChild( document.adoptNode( doc.body.firstChild ) );
				}
				list.appendChild( message );
			} );

			content.appendChild( list );

			if ( value( 'button_text' ) && value( 'button_url' ) ) {
				var button = document.createElement( 'a' );
				button.className = 'dabar__button';
				button.href = '#';
				button.textContent = value( 'button_text' );
				content.appendChild( button );
			}

			inner.appendChild( content );

			if ( dismissible ) {
				var close = document.createElement( 'button' );
				close.type = 'button';
				close.className = 'dabar__close';
				close.setAttribute( 'aria-label', host.getAttribute( 'data-close-label' ) );
				close.innerHTML = CLOSE_ICON;
				inner.appendChild( close );
			}

			bar.appendChild( inner );
			host.replaceChildren( bar );
			host.classList.toggle( 'is-off', ! enabled );
			note.textContent = host.getAttribute( enabled ? 'data-note-on' : 'data-note-off' );

			index = Math.min( index, list.children.length - 1 );
			list.children[ index ].classList.add( 'is-active' );

			var nextInterval = clamp( 'interval', 1000, 60000 );
			if ( nextInterval !== interval ) {
				interval = nextInterval;
				window.clearInterval( timer );
				timer = window.setInterval( rotate, interval );
			}
		}

		function rotate() {
			var count = host.querySelectorAll( '.dabar__message' ).length;
			if ( count > 1 && ! hovered && focused < 0 && ! document.hidden ) {
				showMessage( ( index + 1 ) % count );
			}
		}

		// Links and the close button do nothing in the preview.
		host.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( 'a, button' ) ) {
				event.preventDefault();
			}
		} );
		host.addEventListener( 'mouseenter', function () { hovered = true; } );
		host.addEventListener( 'mouseleave', function () { hovered = false; } );

		// While a message is being edited, keep it on screen.
		form.addEventListener( 'focusin', function ( event ) {
			if ( event.target.matches( '#dabar-messages textarea' ) ) {
				focused = messageFields().indexOf( event.target );
				if ( focused > -1 ) {
					showMessage( focused );
				}
			}
		} );
		form.addEventListener( 'focusout', function () {
			focused = -1;
		} );

		form.addEventListener( 'input', function ( event ) {
			render();
			if ( event.target.matches( '#dabar-messages textarea' ) ) {
				focused = messageFields().indexOf( event.target );
				if ( focused > -1 ) {
					showMessage( focused );
				}
			}
		} );
		form.addEventListener( 'change', render );

		var messages = document.getElementById( 'dabar-messages' );
		if ( messages && 'MutationObserver' in window ) {
			new MutationObserver( render ).observe( messages, { childList: true } );
		}

		render();
	}
} )();
