/**
 * Admin JavaScript for CalcForge settings page.
 *
 * Handles tab switching and clipboard copy interactivity, and keeps the
 * settings page header free of notices that other admin tooling relocates.
 */

/* global navigator, sessionStorage, MutationObserver */

( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		// ---------------------------------------------------------------
		// Tab switching
		// ---------------------------------------------------------------
		const tabBtns = document.querySelectorAll(
			'.calcforge-settings__tab-btn'
		);
		const tabPanels = document.querySelectorAll(
			'.calcforge-settings__tab-panel'
		);
		const saveBar = document.querySelector(
			'.calcforge-settings__save-bar'
		);
		const STORAGE_KEY = 'calcforge_active_tab';

		/*
		 * A tablist is a single widget, so only the active tab stays in the
		 * tab order; the rest are reached with the arrow keys. This keeps
		 * keyboard users from tabbing through every dead-end tab.
		 */
		function activateTab( targetId ) {
			tabBtns.forEach( function ( btn ) {
				const isActive = btn.getAttribute( 'data-tab' ) === targetId;
				btn.setAttribute(
					'aria-selected',
					isActive ? 'true' : 'false'
				);
				btn.setAttribute( 'tabindex', isActive ? '0' : '-1' );
			} );

			tabPanels.forEach( function ( panel ) {
				const isActive = panel.getAttribute( 'id' ) === targetId;
				panel.setAttribute(
					'aria-hidden',
					isActive ? 'false' : 'true'
				);
			} );

			/*
			 * The save bar belongs to the settings form, and reference-only tabs
			 * render outside that form because they have nothing to save. Hide it
			 * whenever the active panel is not one of the form's own panels.
			 */
			if ( saveBar ) {
				const activePanel = document.getElementById( targetId );
				const isFormTab =
					!! activePanel && !! activePanel.closest( 'form' );
				saveBar.hidden = ! isFormTab;
			}

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

			/*
			 * Arrow keys move between tabs, Home/End jump to the ends, and the
			 * tab is activated as focus arrives. Requiring Enter to activate
			 * would break the "automatic activation" behaviour screen readers
			 * announce for a tablist.
			 */
			btn.addEventListener( 'keydown', function ( event ) {
				const tabs = Array.prototype.slice.call( tabBtns );
				const current = tabs.indexOf( btn );
				let next = null;

				switch ( event.key ) {
					case 'ArrowRight':
					case 'ArrowDown':
						next = ( current + 1 ) % tabs.length;
						break;
					case 'ArrowLeft':
					case 'ArrowUp':
						next = ( current - 1 + tabs.length ) % tabs.length;
						break;
					case 'Home':
						next = 0;
						break;
					case 'End':
						next = tabs.length - 1;
						break;
					default:
						return;
				}

				event.preventDefault();

				const target = tabs[ next ];
				activateTab( target.getAttribute( 'data-tab' ) );
				target.focus();
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
			const validId =
				stored && document.getElementById( stored ) ? stored : firstId;
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

				const label = button.querySelector(
					'.calcforge-copy-btn__text'
				);
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
					navigator.clipboard
						.writeText( text )
						.then( setCopied )
						.catch( function () {
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

		// ---------------------------------------------------------------
		// Developer API sample request builder
		// ---------------------------------------------------------------
		const apiRoot = document.querySelector( '[data-calcforge-api]' );

		if ( apiRoot ) {
			const endpoint = apiRoot.getAttribute( 'data-endpoint' );
			const output = apiRoot.querySelector( '[data-api-request]' );
			const copyButton = apiRoot.querySelector(
				'.calcforge-settings__code-box .calcforge-copy-btn'
			);
			const warning = apiRoot.querySelector(
				'.calcforge-settings__api-warning'
			);
			const inputs = apiRoot.querySelectorAll( '[data-api-param]' );

			/*
			 * Builds the JSON body in the same parameter order the docs list, so
			 * the copied command reads the way the reference is written.
			 * JSON.stringify preserves insertion order for string keys, and
			 * querySelectorAll returns document order.
			 */
			const buildRequest = function () {
				const body = {};

				inputs.forEach( function ( input ) {
					const name = input.getAttribute( 'data-api-param' );

					if ( input.type === 'checkbox' ) {
						// Only sent when enabled, matching the API default of false.
						if ( input.checked ) {
							body[ name ] = true;
						}
						return;
					}

					const value = input.valueAsNumber;

					if ( ! isFinite( value ) ) {
						return;
					}

					body[ name ] =
						'true' === input.getAttribute( 'data-api-integer' )
							? Math.round( value )
							: value;
				} );

				// amount is the only required parameter, so refuse to emit a
				// command that the endpoint would reject.
				const valid = Object.prototype.hasOwnProperty.call(
					body,
					'amount'
				);

				if ( ! valid ) {
					if ( output ) {
						output.textContent = '';
					}
					if ( copyButton ) {
						copyButton.setAttribute( 'data-clipboard-text', '' );
						copyButton.disabled = true;
					}
					if ( warning ) {
						warning.hidden = false;
					}
					return;
				}

				if ( warning ) {
					warning.hidden = true;
				}
				if ( copyButton ) {
					copyButton.disabled = false;
				}

				/*
				 * The body is wrapped in double quotes, so its own quotes are
				 * escaped. Escaped double quotes work in cmd.exe and POSIX
				 * shells alike; single quotes would survive cmd.exe and be sent
				 * as literal characters, which the endpoint rejects.
				 */
				const json = JSON.stringify( body ).replace( /"/g, '\\"' );
				const command =
					'curl -X POST ' +
					endpoint +
					' -H "Content-Type: application/json"' +
					' -d "' +
					json +
					'"';

				if ( output ) {
					output.textContent = command;
				}
				if ( copyButton ) {
					copyButton.setAttribute( 'data-clipboard-text', command );
				}
			};

			apiRoot.addEventListener( 'input', buildRequest );
			apiRoot.addEventListener( 'change', buildRequest );
			buildRequest();
		}

		// ---------------------------------------------------------------
		// Shortcode builder
		// ---------------------------------------------------------------
		const shortcodeRoot = document.querySelector(
			'[data-calcforge-shortcode]'
		);

		if ( shortcodeRoot ) {
			const output = shortcodeRoot.querySelector(
				'[data-shortcode-request]'
			);
			/*
			 * This panel shows two snippets: the plain [calcforge] example and the
			 * generated sample further down. The copy button has to be the one
			 * sitting next to the sample, otherwise the plain example's button
			 * would be rewritten with the sample and the sample's own button
			 * would stay empty. Resolving it from the output element scopes it
			 * to that code box.
			 */
			const sampleBox = output
				? output.closest( '.calcforge-settings__code-box' )
				: null;
			const copyButton = sampleBox
				? sampleBox.querySelector( '.calcforge-copy-btn' )
				: null;
			const controls = shortcodeRoot.querySelectorAll(
				'[data-shortcode-param]'
			);

			/*
			 * Builds the shortcode in the order the reference lists the
			 * attributes, so the generated snippet reads like the docs. Fields
			 * are skipped when they are blank, which is what "use the site
			 * default" means for a shortcode attribute.
			 */
			const buildShortcode = function () {
				const attrs = [];

				controls.forEach( function ( control ) {
					let value;

					// Number fields type as you go, so a half-typed value must
					// not end up in the snippet.
					if ( 'number' === control.type ) {
						const numeric = control.valueAsNumber;

						if ( ! isFinite( numeric ) ) {
							return;
						}

						value =
							'true' ===
							control.getAttribute( 'data-shortcode-integer' )
								? String( Math.round( numeric ) )
								: String( numeric );
					} else {
						value = control.value.trim();
					}

					if ( '' === value ) {
						return;
					}

					const name = control.getAttribute( 'data-shortcode-param' );

					/*
					 * A literal double quote would break shortcode_parse_atts(),
					 * so swap it for a single quote rather than emit a snippet
					 * that cannot be inserted.
					 */
					attrs.push(
						name + '="' + value.replace( /"/g, "'" ) + '"'
					);
				} );

				const shortcode =
					'[calcforge' +
					( attrs.length ? ' ' + attrs.join( ' ' ) : '' ) +
					']';

				if ( output ) {
					output.textContent = shortcode;
				}
				if ( copyButton ) {
					copyButton.setAttribute( 'data-clipboard-text', shortcode );
				}
			};

			shortcodeRoot.addEventListener( 'input', buildShortcode );
			shortcodeRoot.addEventListener( 'change', buildShortcode );
			buildShortcode();
		}

		// ---------------------------------------------------------------
		// Stray notices
		// ---------------------------------------------------------------
		/*
		 * WordPress prints "Settings saved." above the page, but on sites
		 * running other admin tooling that notice gets relocated, and here
		 * it lands inside the page header - squeezed between the title and
		 * the version badge. This plugin never asks for the notice, so
		 * rather than depend on which script moved it, any notice that ends
		 * up inside the header is put back at the top of the page, which is
		 * where a save confirmation belongs. The observer covers the case
		 * where the other script runs after this one.
		 */
		const settingsHeader = document.querySelector(
			'.calcforge-settings__header'
		);
		const settingsPage = document.querySelector(
			'.wrap.calcforge-settings'
		);

		function relocateStrayNotices() {
			if ( ! settingsHeader || ! settingsPage ) {
				return;
			}

			settingsHeader
				.querySelectorAll( '.notice' )
				.forEach( ( notice ) => {
					settingsPage.insertBefore( notice, settingsHeader );
				} );
		}

		relocateStrayNotices();

		if ( settingsHeader && typeof MutationObserver === 'function' ) {
			new MutationObserver( relocateStrayNotices ).observe(
				settingsHeader,
				{ childList: true, subtree: true }
			);
		}
	} );
} )();
