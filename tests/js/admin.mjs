/**
 * DOM checks for the settings page JavaScript.
 *
 * Mirrors the server-rendered markup from Amortexa_Settings and drives
 * amortexa-admin.js through jsdom. Covers the ARIA tabs pattern (roving
 * tabindex, arrow keys, Home/End, form-only save bar) and the shortcode
 * builder, including per-code-box copy button scoping.
 *
 * Also guards the save notice: WordPress prints "Settings saved." from
 * admin-header.php, and on this site an admin script moves it into the title
 * row, where it used to be squeezed between the heading and the version badge.
 */
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { JSDOM } from 'jsdom';

const here = dirname( fileURLToPath( import.meta.url ) );
const adminJs = readFileSync( join( here, '../../assets/admin/amortexa-admin.js' ), 'utf8' );
const adminCss = readFileSync( join( here, '../../assets/admin/amortexa-admin.css' ), 'utf8' );

const TABS = [
	{ id: 'general', icon: 'sliders', title: 'General' },
	{ id: 'currency', icon: 'coins', title: 'Currency' },
	{ id: 'design', icon: 'palette', title: 'Design' },
	{ id: 'advanced', icon: 'gear', title: 'Advanced' },
];

// Panels 0-3 live inside the form; panel 4 (reference) renders outside it.
const REFERENCE = { id: 'reference', title: 'Reference' };

function buildMarkup() {
	const tabButtons = [ ...TABS, REFERENCE ]
		.map(
			( tab, i ) =>
				`<button id="amortexa-tab-${ tab.id }" class="amortexa-settings__tab-btn" role="tab" type="button" ` +
				`data-tab="${ tab.id }" aria-controls="${ tab.id }" ` +
				`aria-selected="${ i === 0 ? 'true' : 'false' }" tabindex="${ i === 0 ? '0' : '-1' }">${ tab.title }</button>`
		)
		.join( '' );

	const formPanels = TABS.map(
		( tab, i ) =>
			`<div class="amortexa-settings__tab-panel" id="${ tab.id }" role="tabpanel" ` +
			`aria-labelledby="amortexa-tab-${ tab.id }" aria-hidden="${ i === 0 ? 'false' : 'true' }">` +
			shortcodePanel() +
			`</div>`
	).join( '' );

	return `<!DOCTYPE html><html><body>
		<div class="wrap">
			<div class="amortexa-settings__tabs" role="tablist">${ tabButtons }</div>
			<div class="amortexa-settings__tab-panels">
				<form>
					${ formPanels }
					<div class="amortexa-settings__save-bar"><input type="submit" value="Save"></div>
				</form>
				<div class="amortexa-settings__tab-panel" id="reference" role="tabpanel" aria-labelledby="amortexa-tab-reference" aria-hidden="true">Reference</div>
			</div>
		</div>
	</body></html>`;
}

/*
 * The shortcode panel carries two code boxes: a static plain example and the
 * generated sample. Each has its own copy button, which is exactly the
 * arrangement that once made the two buttons swap their clipboard text.
 */
function shortcodePanel() {
	return `<div data-amortexa-shortcode>
			<div class="amortexa-settings__code-box" data-box="static">
				<code>[amortexa-mortgage-calculator]</code>
				<button class="amortexa-copy-btn" data-clipboard-text="[amortexa-mortgage-calculator]"><span class="amortexa-copy-btn__text">Copy</span></button>
			</div>
			<label>Loan amount <input type="number" data-shortcode-param="loanamount" data-shortcode-integer="true" value="250000"></label>
			<label>Rate <input type="number" data-shortcode-param="interestrate" value="4.75"></label>
			<label>Term <input type="number" data-shortcode-param="loanterm" data-shortcode-integer="true" value="30"></label>
			<label>Down payment <input type="number" data-shortcode-param="downpayment" value=""></label>
			<label>Theme <input type="text" data-shortcode-param="theme" value=""></label>
			<div class="amortexa-settings__code-box" data-box="sample">
				<code data-shortcode-request>[amortexa-mortgage-calculator]</code>
				<button class="amortexa-copy-btn" data-clipboard-text=""><span class="amortexa-copy-btn__text">Copy</span></button>
			</div>
		</div>`;
}

const ORIGIN = 'http://localhost/wp-admin/options-general.php?page=amortexa';

function boot() {
	const dom = new JSDOM( buildMarkup(), {
		runScripts: 'outside-only',
		pretendToBeVisual: true,
		url: ORIGIN,
	} );
	dom.window.eval( adminJs );
	return dom;
}

function start( dom ) {
	dom.window.document.dispatchEvent( new dom.window.Event( 'DOMContentLoaded', { bubbles: true } ) );
	return dom;
}

let failures = 0;
function check( condition, label ) {
	if ( ! condition ) {
		failures++;
		console.log( `FAIL ${ label }` );
	} else {
		console.log( `ok   ${ label }` );
	}
}

const dom = start( boot() );
const { document } = dom.window;
const btns = [ ...document.querySelectorAll( '.amortexa-settings__tab-btn' ) ];
const panels = [ ...document.querySelectorAll( '.amortexa-settings__tab-panel' ) ];
const saveBar = document.querySelector( '.amortexa-settings__save-bar' );

const selected = () => btns.find( ( b ) => 'true' === b.getAttribute( 'aria-selected' ) );
const panel = ( id ) => document.getElementById( id );
const focused = () => document.activeElement;
const roving = () => btns.map( ( b ) => b.getAttribute( 'tabindex' ) );

// A key event lands on the tab that currently has focus, exactly as a real
// browser delivers it, so the handler can work out where to move from.
function press( key ) {
	const active = document.activeElement;
	const origin = btns.includes( active ) ? active : selected();
	const event = new dom.window.KeyboardEvent( 'keydown', { key, bubbles: true, cancelable: true } );
	origin.dispatchEvent( event );
	return event;
}

// --- initial state -----------------------------------------------------
check( selected() === btns[ 0 ], 'the first tab is selected on load' );
check( panel( 'general' ).getAttribute( 'aria-hidden' ) === 'false', 'the first panel is visible on load' );
check( panel( 'currency' ).getAttribute( 'aria-hidden' ) === 'true', 'other panels start hidden' );
check( roving().join() === [ '0', '-1', '-1', '-1', '-1' ].join(), 'only the active tab is in the tab order' );
check( saveBar.hidden === false, 'the save bar shows for a form tab' );

// --- arrow keys --------------------------------------------------------
let ev = press( 'ArrowRight' );
check( ev.defaultPrevented, 'ArrowRight is handled (default scroll prevented)' );
check( selected() === btns[ 1 ], 'ArrowRight activates the next tab' );
check( focused() === btns[ 1 ], 'ArrowRight moves focus with the selection' );
check( panel( 'currency' ).getAttribute( 'aria-hidden' ) === 'false', 'ArrowRight reveals the matching panel' );
check( panel( 'general' ).getAttribute( 'aria-hidden' ) === 'true', 'ArrowRight hides the previous panel' );
check( roving().join() === [ '-1', '0', '-1', '-1', '-1' ].join(), 'tabindex roves to the new tab' );

press( 'ArrowRight' );
press( 'ArrowRight' );
check( selected() === btns[ 3 ], 'ArrowRight walks forward across tabs' );
press( 'ArrowRight' );
check( selected() === btns[ 4 ], 'ArrowRight wraps from the last tab to the first' );
check( focused() === btns[ 4 ], 'focus follows the wrap' );
press( 'ArrowLeft' );
check( selected() === btns[ 3 ], 'ArrowLeft moves back one tab' );
press( 'ArrowLeft' );
press( 'ArrowLeft' );
press( 'ArrowLeft' );
check( selected() === btns[ 0 ], 'ArrowLeft wraps from the first tab to the last' );
press( 'ArrowUp' );
check( selected() === btns[ 4 ], 'ArrowUp behaves as previous' );
press( 'ArrowDown' );
check( selected() === btns[ 0 ], 'ArrowDown behaves as next' );

// --- Home / End --------------------------------------------------------
press( 'End' );
check( selected() === btns[ 4 ], 'End jumps to the last tab' );
check( focused() === btns[ 4 ], 'End moves focus to the last tab' );
press( 'Home' );
check( selected() === btns[ 0 ], 'Home jumps to the first tab' );
check( focused() === btns[ 0 ], 'Home moves focus to the first tab' );

// --- save bar follows the active tab ----------------------------------
press( 'End' );
check( saveBar.hidden === true, 'the save bar hides on a reference-only tab' );
press( 'Home' );
check( saveBar.hidden === false, 'the save bar returns on a form tab' );

// --- unrelated keys are left alone ------------------------------------
ev = press( 'Enter' );
check( ! ev.defaultPrevented, 'Enter is not intercepted' );
ev = press( 'a' );
check( ! ev.defaultPrevented, 'letter keys are not intercepted' );
check( selected() === btns[ 0 ], 'unrelated keys do not change the selection' );

// --- click still works, and roves -------------------------------------
btns[ 2 ].dispatchEvent( new dom.window.MouseEvent( 'click', { bubbles: true } ) );
check( selected() === btns[ 2 ], 'clicking a tab activates it' );
check( roving().join() === [ '-1', '-1', '0', '-1', '-1' ].join(), 'clicking a tab roves tabindex' );

// --- session storage still restores ----------------------------------
// Store a tab, then load a fresh page the way a real reload would.
const first = start( boot() );
const firstBtns = [ ...first.window.document.querySelectorAll( '.amortexa-settings__tab-btn' ) ];
firstBtns[ 2 ].dispatchEvent( new first.window.MouseEvent( 'click', { bubbles: true } ) );
check(
	first.window.sessionStorage.getItem( 'amortexa_active_tab' ) === 'design',
	'choosing a tab is remembered for the next page load'
);

const dom3 = boot();
dom3.window.sessionStorage.setItem( 'amortexa_active_tab', 'design' );
start( dom3 );
const btns3 = [ ...dom3.window.document.querySelectorAll( '.amortexa-settings__tab-btn' ) ];
check(
	btns3.find( ( b ) => 'true' === b.getAttribute( 'aria-selected' ) ) === btns3[ 2 ],
	'a remembered tab is restored on the next page load'
);
check(
	btns3[ 2 ].getAttribute( 'tabindex' ) === '0' && btns3[ 0 ].getAttribute( 'tabindex' ) === '-1',
	'tabindex is corrected for a restored tab'
);

// --- shortcode builder -------------------------------------------------
const scRoot = document.querySelector( '[data-amortexa-shortcode]' );
const scOutput = scRoot.querySelector( '[data-shortcode-request]' );
const scStaticBox = scRoot.querySelector( '[data-box="static"]' );
const scSampleBox = scRoot.querySelector( '[data-box="sample"]' );
const staticBtn = scStaticBox.querySelector( '.amortexa-copy-btn' );
const sampleBtn = scSampleBox.querySelector( '.amortexa-copy-btn' );
const fields = Object.fromEntries(
	[ ...scRoot.querySelectorAll( '[data-shortcode-param]' ) ].map( ( el ) => [
		el.getAttribute( 'data-shortcode-param' ),
		el,
	] )
);

function setField( name, value ) {
	fields[ name ].value = value;
	fields[ name ].dispatchEvent( new dom.window.Event( 'input', { bubbles: true } ) );
}

check(
	scOutput.textContent === '[amortexa-mortgage-calculator loanamount="250000" interestrate="4.75" loanterm="30"]',
	'the shortcode is built from the prefilled fields, skipping blanks'
);
check(
	! scOutput.textContent.includes( 'downpayment' ),
	'a blank field leaves its attribute out'
);
check(
	sampleBtn.getAttribute( 'data-clipboard-text' ) === scOutput.textContent,
	"the sample copy button carries the generated shortcode"
);
check(
	staticBtn.getAttribute( 'data-clipboard-text' ) === '[amortexa-mortgage-calculator]',
	'the static copy button keeps its own plain example'
);

setField( 'downpayment', '50000' );
check(
	scOutput.textContent.includes( 'downpayment="50000"' ),
	'filling a previously blank field adds its attribute'
);
setField( 'theme', 'forest' );
check( scOutput.textContent.includes( 'theme="forest"' ), 'a text field is included' );

setField( 'loanamount', 'abc' );
check(
	! scOutput.textContent.includes( 'loanamount' ),
	'a half-typed number is left out until it parses'
);
setField( 'loanamount', '250000.7' );
check(
	scOutput.textContent.includes( 'loanamount="250001"' ),
	'an integer field is rounded'
);
setField( 'interestrate', '4.756' );
check(
	scOutput.textContent.includes( 'interestrate="4.756"' ),
	'a decimal field keeps its fraction'
);

setField( 'theme', 'say "hi"' );
check(
	scOutput.textContent.includes( `theme="say 'hi'"` ),
	'quotes are swapped so the attribute cannot break the shortcode'
);

setField( 'theme', '' );
setField( 'interestrate', '' );
setField( 'loanamount', '' );
setField( 'downpayment', '' );
check( scOutput.textContent === '[amortexa-mortgage-calculator loanterm="30"]', 'clearing every field but one leaves just that attribute' );

/*
 * The save notice. The plugin never asks for it - WordPress prints it above the
 * page - but other admin tooling on this site relocates it into the header,
 * between the title and the version badge. The header is a flex row, so the
 * notice gets squeezed into the gap instead of reading as a confirmation.
 * The admin script moves any such notice back above the frame that holds the
 * header and the layout, both for a notice already present at load and one
 * injected afterwards.
 */
function noticeMarkup( notice ) {
	return `<!DOCTYPE html><html><body>
		<div class="wrap amortexa-settings">
			<div class="amortexa-settings__frame">
				<div class="amortexa-settings__header">
					<div class="amortexa-settings__header-brand">
						<div>
							<div class="amortexa-settings__title-row">
								<h1 class="amortexa-settings__title">Amortexa</h1>
								${ notice }
								<span class="amortexa-settings__version-badge">v1.0.0</span>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</body></html>`;
}

const SAVED_NOTICE =
	'<div id="setting-error-settings_updated" class="notice notice-success settings-error is-dismissible">' +
	'<p><strong>Settings saved.</strong></p></div>';

function bootNotices( markup ) {
	const noticeDom = new JSDOM( markup, {
		runScripts: 'outside-only',
		pretendToBeVisual: true,
		url: 'http://localhost/wp-admin/options-general.php?page=amortexa-settings',
	} );
	noticeDom.window.eval( adminJs );
	return start( noticeDom );
}

// A notice that is already in the header when the script runs.
const present = bootNotices( noticeMarkup( SAVED_NOTICE ) );
const presentDoc = present.window.document;
check(
	! presentDoc.querySelector( '.amortexa-settings__header .notice' ),
	'a notice sitting in the header is moved out of it'
);
const presentPage = presentDoc.querySelector( '.wrap.amortexa-settings' );
check(
	presentPage.firstElementChild.classList.contains( 'notice' ),
	'the relocated notice becomes the first thing on the page, above the frame'
);
check(
	! presentDoc.querySelector( '.amortexa-settings__frame .notice' ),
	'the relocated notice is not left inside the frame'
);
check(
	!! presentPage.querySelector( '.amortexa-settings__frame' ),
	'the frame still holds the header after the notice moves out'
);
check(
	presentDoc.querySelector( '.amortexa-settings__title-row' ).textContent.includes( 'Amortexa' ) &&
		presentDoc.querySelector( '.amortexa-settings__title-row' ).textContent.includes( 'v1.0.0' ),
	'the title and version badge are left intact'
);

// A notice injected after the script has already run, which is the ordering
// that the MutationObserver has to cover.
const late = bootNotices( noticeMarkup( '' ) );
const lateDoc = late.window.document;
const lateNotice = lateDoc.createElement( 'div' );
lateNotice.className = 'notice notice-success settings-error is-dismissible';
lateNotice.innerHTML = '<p><strong>Settings saved.</strong></p>';
lateDoc.querySelector( '.amortexa-settings__version-badge' ).before( lateNotice );
await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
check(
	! lateDoc.querySelector( '.amortexa-settings__header .notice' ),
	'a notice injected after load is moved out of the header too'
);
check(
	lateDoc.querySelector( '.wrap.amortexa-settings' ).firstElementChild.classList.contains( 'notice' ),
	'a late notice also ends up above the frame'
);

check(
	/flex-wrap:\s*wrap/.test(
		adminCss.match( /\.amortexa-settings__title-row\s*\{([^}]*)\}/ )?.[ 1 ] || ''
	),
	'the title row can still wrap, e.g. for a long translated version badge'
);

console.log( failures ? `\n${ failures } failure(s).` : '\nAll tab checks passed.' );
process.exit( failures ? 1 : 0 );
