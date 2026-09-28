/**
 * Admin JavaScript for CalcForge settings page.
 *
 * Adds clipboard copy interactivity for shortcodes and code snippets.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		const copyButtons = document.querySelectorAll( '.calcforge-copy-btn' );

		copyButtons.forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				const text = button.getAttribute( 'data-clipboard-text' );

				if ( ! text ) {
					return;
				}

				const label = button.querySelector( '.calcforge-copy-btn__text' );
				const originalText = label ? label.textContent : '';

				const setCopied = function () {
					button.classList.add( 'calcforge-copied' );
					if ( label ) {
						label.textContent = 'Copied!';
					}

					setTimeout( function () {
						button.classList.remove( 'calcforge-copied' );
						if ( label ) {
							label.textContent = originalText;
						}
					}, 2000 );
				};

				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( text ).then( setCopied ).catch( function () {
						fallbackCopy( text, setCopied );
					} );
				} else {
					fallbackCopy( text, setCopied );
				}
			} );
		} );

		function fallbackCopy( text, callback ) {
			const textarea = document.createElement( 'textarea' );
			textarea.value = text;
			textarea.style.position = 'fixed';
			textarea.style.opacity = '0';
			document.body.appendChild( textarea );
			textarea.select();

			try {
				document.execCommand( 'copy' );
				callback();
			} catch ( err ) {
				// Copy failed gracefully.
			}

			document.body.removeChild( textarea );
		}
	} );
} )();
