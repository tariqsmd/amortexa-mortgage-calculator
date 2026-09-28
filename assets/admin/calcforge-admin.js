/**
 * Admin JavaScript for CalcForge settings page.
 *
 * Handles tab switching and clipboard copy interactivity.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {

		// ---------------------------------------------------------------
		// Tab switching
		// ---------------------------------------------------------------
		const tabBtns = document.querySelectorAll( '.calcforge-settings__tab-btn' );
		const tabPanels = document.querySelectorAll( '.calcforge-settings__tab-panel' );
		const STORAGE_KEY = 'calcforge_active_tab';

		function activateTab( targetId ) {
			tabBtns.forEach( function ( btn ) {
				const isActive = btn.getAttribute( 'data-tab' ) === targetId;
				btn.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
			} );

			tabPanels.forEach( function ( panel ) {
				const isActive = panel.getAttribute( 'id' ) === targetId;
				panel.setAttribute( 'aria-hidden', isActive ? 'false' : 'true' );
			} );

			try {
				sessionStorage.setItem( STORAGE_KEY, targetId );
			} catch ( e ) {
				// Storage unavailable; continue silently.
			}
		}

		tabBtns.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				activateTab( btn.getAttribute( 'data-tab' ) );
			} );
		} );

		// Restore last active tab, default to first.
		if ( tabBtns.length > 0 ) {
			let stored = null;
			try {
				stored = sessionStorage.getItem( STORAGE_KEY );
			} catch ( e ) {
				// Storage unavailable; continue silently.
			}

			const firstId = tabBtns[ 0 ].getAttribute( 'data-tab' );
			const validId = stored && document.getElementById( stored ) ? stored : firstId;
			activateTab( validId );
		}

		// ---------------------------------------------------------------
		// Clipboard copy
		// ---------------------------------------------------------------
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
