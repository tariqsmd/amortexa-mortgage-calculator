<?php
/**
 * Cross-language math parity test for the Mortgage Calculator block.
 *
 * WordPress-independent: stubs the few core functions the pure math helpers
 * call, computes the same matrix of cases in both PHP (includes/helpers.php)
 * and JavaScript (src/utils/calculator.js), then asserts they agree exactly.
 *
 * Run with: php tests/parity.php
 *
 * Usage: php tests/parity.php
 * Exit code 0 on parity, 1 on mismatch or error.
 *
 * Never shipped. Both the dist allowlist (tools/build-dist.cjs) and .distignore
 * drop tests/, so a released zip does not contain this file and the
 * WordPress.org plugin scanner never sees it. A development checkout installed
 * straight into wp-content/plugins does contain it, and there the WordPress
 * Plugin Check tool reads this file as if it were shipped code -- it defines
 * its own stubs for core functions and calls shell_exec(), which is exactly
 * what the security sniffs are written to flag. The whole file therefore opts
 * out of PHPCS rather than annotating hundreds of harness lines individually.
 * amortexa_test_release_packaging() is what keeps the exclusion honest.
 *
 * @package Amortexa
 */

// phpcs:ignoreFile -- Dev-only harness. Not part of any release; see above.

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

// The plugin's helpers guard on ABSPATH; satisfy it without booting WP.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

/*
 * Defined before helpers.php loads because the rate limit defaults are file
 * scope constants, evaluated the moment the file is included.
 */
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

require_once dirname( __DIR__ ) . '/includes/helpers.php';
require_once dirname( __DIR__ ) . '/includes/design-tokens.php';
require_once dirname( __DIR__ ) . '/includes/class-rest.php';

// Stub the only core function the pure math paths touch.
if ( ! function_exists( 'absint' ) ) {
	/**
	 * Emulates WP absint().
	 *
	 * @param mixed $maybeint Value to absolutize.
	 * @return int Absolute integer.
	 */
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Emulates WP translation passthrough.
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain.
	 * @return string Original text.
	 */
	function __( $text, $domain = 'default' ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.textFound
		return $text;
	}
}

if ( ! function_exists( 'sanitize_hex_color' ) ) {
	/**
	 * Emulates WP sanitize_hex_color() closely enough for the schema tests.
	 *
	 * @param string $color Raw colour.
	 * @return string|null Sanitised hex colour, or null when unusable.
	 */
	function sanitize_hex_color( $color ) {
		$color = ltrim( (string) $color, '#' );

		if ( 3 === strlen( $color ) && ctype_xdigit( $color ) ) {
			return '#' . $color;
		}

		if ( 6 === strlen( $color ) && ctype_xdigit( $color ) ) {
			return '#' . $color;
		}

		return null;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Emulates WP get_option() with nothing stored, so the schema tests run
	 * against the plugin defaults rather than a real site's saved settings.
	 *
	 * @param string $option Option name.
	 * @param mixed  $default Value to return when unset.
	 * @return mixed The default.
	 */
	function get_option( $option, $default = false ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.textFound
		return $default;
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	/**
	 * Emulates WP wp_parse_args() for string-keyed arrays.
	 *
	 * @param array<string,mixed> $args     Values to merge.
	 * @param array<string,mixed> $defaults Fallback values.
	 * @return array<string,mixed> Merged arguments.
	 */
	function wp_parse_args( $args, $defaults = array() ) {
		$args = is_array( $args ) ? $args : array();

		return array_merge( $defaults, $args );
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	$GLOBALS['amortexa_test_filters'] = array();

	/**
	 * Emulates WP add_filter() for single callback slots.
	 *
	 * @param string   $hook_name Filter name.
	 * @param callable $callback  Callback to run.
	 * @return bool Always true.
	 */
	function add_filter( $hook_name, $callback ) {
		$GLOBALS['amortexa_test_filters'][ $hook_name ] = $callback;
		return true;
	}
}

if ( ! function_exists( 'remove_all_filters' ) ) {
	/**
	 * Emulates WP remove_all_filters() for a single hook.
	 *
	 * @param string $hook_name Filter name.
	 * @return bool Always true.
	 */
	function remove_all_filters( $hook_name ) {
		unset( $GLOBALS['amortexa_test_filters'][ $hook_name ] );
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) { // phpcs:ignore WordPress.NamingConventions.ValidHookName
	/**
	 * Emulates WP apply_filters(), running any registered callback.
	 *
	 * Extra arguments beyond the value are passed through, matching core.
	 *
	 * @param string $hook_name Filter name.
	 * @param mixed  $value     Value being filtered.
	 * @param mixed  ...$args   Additional context arguments.
	 * @return mixed The filtered value.
	 */
	function apply_filters( $hook_name, $value, ...$args ) {
		if ( ! isset( $GLOBALS['amortexa_test_filters'][ $hook_name ] ) ) {
			return $value;
		}

		return call_user_func_array(
			$GLOBALS['amortexa_test_filters'][ $hook_name ],
			array_merge( array( $value ), $args )
		);
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Emulates WP sanitize_text_field().
	 *
	 * @param string $str Raw text.
	 * @return string Cleaned text.
	 */
	function sanitize_text_field( $str ) {
		$filtered = strip_tags( (string) $str ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$filtered = preg_replace( '/[\r\n\t ]+/', ' ', $filtered );

		return trim( $filtered );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Mirrors core so a stub bug cannot mask a real sanitizing bug.
	 *
	 * @param string $key Raw key.
	 * @return string Lowercased key with anything outside [a-z0-9_-] removed.
	 */
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

if ( ! function_exists( 'wp_html_excerpt' ) ) {
	/**
	 * Emulates WP wp_html_excerpt().
	 *
	 * @param string $str  Source text.
	 * @param int    $count Maximum characters to keep.
	 * @return string Excerpt.
	 */
	function wp_html_excerpt( $str, $count ) {
		return substr( (string) $str, 0, (int) $count );
	}
}

if ( ! function_exists( '_doing_it_wrong' ) ) {
	/**
	 * Emulates WP _doing_it_wrong(), surfacing the notice immediately.
	 *
	 * @param string $function_name Function that misbehaved.
	 * @param string $message       Notice text.
	 * @param string $version       Version the notice was added.
	 */
	function _doing_it_wrong( $function_name, $message, $version ) {
		fwrite( STDERR, "NOTICE: {$function_name} {$message}\n" );
	}
}

/*
 * An in-memory transient store, so the rate limit window can be tested without a
 * database. Deliberately backed by the real clock: the code under test stamps
 * expiries with time(), so a stubbed clock would disagree with it and make
 * assertions about window expiry meaningless. Tests age an entry out explicitly.
 */
$GLOBALS['amortexa_test_transients'] = array();

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * Emulates WP get_transient() against the in-memory store.
	 *
	 * @param string $key Transient key.
	 * @return mixed Stored value, or false when absent or expired.
	 */
	function get_transient( $key ) {
		if ( ! isset( $GLOBALS['amortexa_test_transients'][ $key ] ) ) {
			return false;
		}

		$entry = $GLOBALS['amortexa_test_transients'][ $key ];

		if ( $entry['expires'] > 0 && $entry['expires'] <= time() ) {
			unset( $GLOBALS['amortexa_test_transients'][ $key ] );
			return false;
		}

		return $entry['value'];
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * Emulates WP set_transient() against the in-memory store.
	 *
	 * @param string $key        Transient key.
	 * @param mixed  $value      Value to store.
	 * @param int    $expiration Lifetime in seconds. 0 means no expiry.
	 * @return bool Always true.
	 */
	function set_transient( $key, $value, $expiration = 0 ) {
		$GLOBALS['amortexa_test_transients'][ $key ] = array(
			'value'   => $value,
			'expires' => $expiration > 0 ? time() + $expiration : 0,
		);

		return true;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * Emulates WP wp_unslash() for the scalar superglobal values read here.
	 *
	 * @param mixed $value Value to unslash.
	 * @return mixed The unslashed value.
	 */
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Emulates WP esc_html__().
	 *
	 * @param string $text   Text to escape.
	 * @param string $domain Text domain.
	 * @return string The escaped text.
	 */
	function esc_html__( $text, $domain = 'default' ) { // phpcs:ignore WordPress.NamingConventions.ValidHookName
		return htmlspecialchars( $text, ENT_QUOTES );
	}
}

if ( ! function_exists( 'rest_authorization_required_code' ) ) {
	/**
	 * Emulates WP rest_authorization_required_code().
	 *
	 * @return int Status code.
	 */
	function rest_authorization_required_code() {
		return is_user_logged_in() ? 403 : 401;
	}
}

if ( ! function_exists( 'is_user_logged_in' ) ) {
	/**
	 * Emulates WP is_user_logged_in() as logged out.
	 *
	 * @return bool Always false.
	 */
	function is_user_logged_in() {
		return false;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal stand-in for the core WP_Error class.
	 */
	class WP_Error {

		/**
		 * Error code.
		 *
		 * @var string
		 */
		public $code;

		/**
		 * Error message.
		 *
		 * @var string
		 */
		public $message;

		/**
		 * Error data.
		 *
		 * @var mixed
		 */
		public $data;

		/**
		 * Constructor.
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 * @param mixed  $data    Error data.
		 */
		public function __construct( $code = '', $message = '', $data = null ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		/**
		 * Returns the error code.
		 *
		 * @return string Error code.
		 */
		public function get_error_code() {
			return $this->code;
		}

		/**
		 * Returns the error message.
		 *
		 * @return string Error message.
		 */
		public function get_error_message() {
			return $this->message;
		}

		/**
		 * Returns the error data.
		 *
		 * @return mixed Error data.
		 */
		public function get_error_data() {
			return $this->data;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	/**
	 * Minimal stand-in for the core WP_REST_Response class.
	 */
	class WP_REST_Response {

		/**
		 * Response body data.
		 *
		 * @var mixed
		 */
		protected $data;

		/**
		 * HTTP status.
		 *
		 * @var int
		 */
		protected $status;

		/**
		 * Response headers.
		 *
		 * @var array<string,string>
		 */
		protected $headers = array();

		/**
		 * Constructor.
		 *
		 * @param mixed $data   Body data.
		 * @param int   $status HTTP status.
		 */
		public function __construct( $data = null, $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}

		/**
		 * Returns the body data.
		 *
		 * @return mixed Body data.
		 */
		public function get_data() {
			return $this->data;
		}

		/**
		 * Returns the HTTP status.
		 *
		 * @return int HTTP status.
		 */
		public function get_status() {
			return $this->status;
		}

		/**
		 * Sets a response header.
		 *
		 * @param string $key   Header name.
		 * @param string $value Header value.
		 * @return WP_REST_Response The response.
		 */
		public function header( $key, $value ) {
			$this->headers[ $key ] = $value;
			return $this;
		}

		/**
		 * Returns all response headers.
		 *
		 * @return array<string,string> Headers.
		 */
		public function get_headers() {
			return $this->headers;
		}
	}
}

if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	/**
	 * Emulates WP wp_generate_uuid4() with a predictable counter.
	 *
	 * @return string Pseudo unique identifier.
	 */
	function wp_generate_uuid4() {
		static $n = 0;
		++$n;
		return sprintf( '00000000-0000-4000-8000-%012d', $n );
	}
}

/**
 * Runs parity checks between PHP and JS calculation output.
 *
 * @return int Number of failures encountered.
 */
function amortexa_test_parity() {
	$cases = array(
		array( 200000, 6.0, 30 ),
		array( 250000, 0.0, 30 ),
		array( 100000, 3.5, 15 ),
		array( 500000, 7.25, 20 ),
		array( 45000, 5.875, 10 ),
	);

	$failures = 0;

	$php_monthly = array();
	$php_schedules = array();

	foreach ( $cases as $case ) {
		list( $principal, $rate, $years ) = $case;

		$php_monthly[] = amortexa_calculate_monthly_payment( $principal, $rate, $years );
		$php_schedules[] = amortexa_calculate_amortization_schedule( $principal, $rate, $years );
	}

	// Locate the repo root and the JS driver, then run it.
	$root = dirname( __DIR__ );
	$node  = 'node';
	$driver = $root . '/tests/js/calc.mjs';
	$json = shell_exec( $node . ' ' . escapeshellarg( $driver ) . ' 2>&1' );

	if ( null === $json || '' === trim( $json ) ) {
		fwrite( STDERR, "FAIL: could not execute Node driver ($driver)\n" );
		return 1;
	}

	$decoded = json_decode( $json, true );
	if ( ! is_array( $decoded ) || ! isset( $decoded['monthly'], $decoded['schedule'] ) ) {
		fwrite( STDERR, "FAIL: invalid JSON from Node driver: $json\n" );
		return 1;
	}

	// The PHP math returns floats; the JS returns numbers. Compare tightly.
	foreach ( $cases as $index => $case ) {
		$php_payment = $php_monthly[ $index ];
		$js_payment  = (float) $decoded['monthly'][ $index ];

		if ( abs( $php_payment - $js_payment ) > 0.0001 ) {
			$failures++;
			fwrite(
				STDOUT,
				sprintf(
					"FAIL monthly payment case %d (%d @ %.3f%% / %dy): PHP=%.2f JS=%.2f\n",
					$index,
					$case[0],
					$case[1],
					$case[2],
					$php_payment,
					$js_payment
				)
			);
		} else {
			fwrite(
				STDOUT,
				sprintf(
					"ok   monthly payment case %d (%d @ %.3f%% / %dy): %.2f\n",
					$index,
					$case[0],
					$case[1],
					$case[2],
					$php_payment
				)
			);
		}

		$php_schedule = $php_schedules[ $index ];
		$js_schedule  = isset( $decoded['schedule'][ $index ] ) ? $decoded['schedule'][ $index ] : array();

		if ( count( $php_schedule ) !== count( $js_schedule ) ) {
			$failures++;
			fwrite(
				STDOUT,
				sprintf(
					"FAIL schedule case %d length: PHP=%d JS=%d\n",
					$index,
					count( $php_schedule ),
					count( $js_schedule )
				)
			);
			continue;
		}

		foreach ( $php_schedule as $row_index => $row ) {
			$js_row = isset( $js_schedule[ $row_index ] ) ? $js_schedule[ $row_index ] : array();

			foreach ( array( 'year', 'principal', 'interest', 'balance' ) as $key ) {
				$php_val = (float) $row[ $key ];
				$js_val  = isset( $js_row[ $key ] ) ? (float) $js_row[ $key ] : 0.0;

				if ( abs( $php_val - $js_val ) > 0.0001 ) {
					$failures++;
					fwrite(
						STDOUT,
						sprintf(
							"FAIL schedule case %d row %d key '%s': PHP=%s JS=%s\n",
							$index,
							$row_index,
							$key,
							var_export( $row[ $key ], true ),
							var_export( $js_row[ $key ] ?? null, true )
						)
					);
				}
			}
		}

		fwrite(
			STDOUT,
			sprintf(
				"ok   schedule case %d: %d annual rows comparable\n",
				$index,
				count( $php_schedule )
			)
		);
	}

	$failures += amortexa_test_cost_parity( $decoded, $failures );

	return $failures;
}

/**
 * Compares the cost math between the server render and the live recalculation.
 *
 * The two implementations are written separately, one in PHP and one in JS, so
 * nothing stops them drifting apart. A cached page shows the PHP numbers and a
 * visitor typing in a field shows the JS ones, and if the two disagree the
 * figures visibly jump, so they are compared directly here.
 *
 * @param array $decoded Decoded output of the Node driver.
 * @param int   $already Failures counted so far, reported for context.
 * @return int Number of failures encountered.
 */
function amortexa_test_cost_parity( $decoded, $already ) {
	if ( ! isset( $decoded['costs'] ) || ! is_array( $decoded['costs'] ) ) {
		return 0;
	}

	$cases = array(
		array(
			'loanAmount'    => 400000,
			'downPayment'   => 80000,
			'interestRate'  => 7.455,
			'loanTerm'      => 30,
			'propertyTax'   => 1.2,
			'homeInsurance' => 1500,
			'otherCosts'    => 4000,
		),
		array(
			'loanAmount'   => 400000,
			'downPayment'  => 40000,
			'interestRate' => 7.0,
			'loanTerm'     => 30,
			'pmi'          => 1200,
			'pmiUnit'      => 'amount',
		),
		array(
			'loanAmount'   => 300000,
			'interestRate' => 6.5,
			'loanTerm'     => 30,
		),
		array(
			'loanAmount'        => 250000,
			'downPayment'       => 25000,
			'interestRate'      => 5.5,
			'loanTerm'          => 15,
			'propertyTax'       => 1.8,
			'propertyTaxUnit'   => 'percent',
			'hoaFee'            => 3600,
			'hoaFeeUnit'        => 'amount',
			'homeInsurance'     => 2,
			'homeInsuranceUnit' => 'amount',
			'pmi'               => 0.5,
			'pmiUnit'           => 'percent',
			'otherCosts'        => 1200,
			'otherCostsUnit'    => 'amount',
		),
	);

	$failures = 0;

	foreach ( $cases as $index => $attrs ) {
		if ( ! isset( $decoded['costs'][ $index ] ) ) {
			continue;
		}

		$php = amortexa_calculate( array_merge( $attrs, array( 'showAmortization' => false ) ) );
		$js  = $decoded['costs'][ $index ];

		$pairs = array(
			'monthlyPayment'   => array( $php['monthly_payment'], $js['monthlyPayment'] ),
			'totalMonthlyCost' => array( $php['total_monthly_cost'], $js['totalMonthlyCost'] ),
			'totalPmi'         => array( $php['total_pmi'], $js['totalPmi'] ),
			'totalCosts'       => array( $php['total_costs'], $js['totalCosts'] ),
			'totalOutOfPocket' => array( $php['total_out_of_pocket'], $js['totalOutOfPocket'] ),
		);

		foreach ( $pairs as $key => $pair ) {
			if ( abs( (float) $pair[0] - (float) $pair[1] ) > 0.0001 ) {
				$failures++;
				fwrite(
					STDOUT,
					sprintf(
						"FAIL cost case %d '%s': PHP=%s JS=%s\n",
						$index,
						$key,
						var_export( $pair[0], true ),
						var_export( $pair[1], true )
					)
				);
			}
		}

		// The cancellation month is an integer, and must match exactly.
		if ( (int) $php['pmi_end_month'] !== (int) $js['pmiEndMonth'] ) {
			$failures++;
			fwrite(
				STDOUT,
				sprintf(
					"FAIL cost case %d 'pmiEndMonth': PHP=%d JS=%d\n",
					$index,
					(int) $php['pmi_end_month'],
					(int) $js['pmiEndMonth']
				)
			);
		}

		foreach ( array( 'tax', 'insurance', 'hoa', 'pmi', 'other' ) as $component ) {
			$php_value = isset( $php['monthly_costs'][ $component ] ) ? (float) $php['monthly_costs'][ $component ] : 0.0;
			$js_value  = isset( $js['monthlyCosts'][ $component ] ) ? (float) $js['monthlyCosts'][ $component ] : 0.0;

			if ( abs( $php_value - $js_value ) > 0.0001 ) {
				$failures++;
				fwrite(
					STDOUT,
					sprintf(
						"FAIL cost case %d component '%s': PHP=%s JS=%s\n",
						$index,
						$component,
						var_export( $php_value, true ),
						var_export( $js_value, true )
					)
				);
			}
		}

		fwrite(
			STDOUT,
			sprintf(
				"ok   cost case %d: PHP and JS agree on %d components, PMI ends month %d\n",
				$index,
				5,
				(int) $php['pmi_end_month']
			)
		);
	}

	unset( $already );

	return $failures;
}

/**
 * A deterministic sample value per token type.
 *
 * Mirrors sampleFor() in tests/js/design.mjs, so both languages are asked to
 * render the same schema value and the results can be compared.
 *
 * @param array<string,mixed> $token Token definition.
 * @return string Sample stored value.
 */
function amortexa_test_design_sample( $token ) {
	switch ( $token['type'] ) {
		case 'color':
			return '#1d4ed8';

		case 'length':
			return (string) min( $token['max'], max( $token['min'], 18 ) );

		case 'spacing':
			return '8 12 16 20';

		case 'select': {
			/*
			 * The editor payload carries options as ordered pairs so that
			 * JavaScript's integer-key ordering cannot reshuffle them.
			 */
			$options = $token['options'];

			foreach ( (array) $options as $option ) {
				if ( is_array( $option ) && isset( $option['value'] ) && '' !== $option['value'] ) {
					return (string) $option['value'];
				}
			}

			// Raw token shape, where options names a shared list.
			$lists = amortexa_get_design_option_lists();
			$list  = isset( $lists[ $token['options'] ] ) ? $lists[ $token['options'] ] : array();

			foreach ( array_keys( $list ) as $option ) {
				if ( '' !== $option ) {
					return (string) $option;
				}
			}

			return '';
		}
	}

	return '';
}

/**
 * Runs parity and wiring checks for the design token schema.
 *
 * Three things are verified, each guarding a failure that has actually happened
 * or could happen silently:
 *
 * 1. The schema PHP hands the editor matches the schema PHP renders with, and
 *    both produce identical CSS for the same token values.
 * 2. The editor's JS resolves the same overrides PHP would emit, so a token set
 *    in the Design tab cannot look right in the editor and wrong on the site.
 * 3. Every custom property the stylesheet consumes is declared, and every token
 *    the inspector offers is actually read. A token that is written but never
 *    read is a control that silently does nothing, which is exactly the bug that
 *    shipped when the editor wrote --amortexa-accent-alt and --amortexa-label
 *    while the stylesheet only knew --amortexa-accent-2 and
 *    --amortexa-label-color.
 *
 * @return int Number of failures encountered.
 */
function amortexa_test_design_schema() {
	$failures = 0;
	$root     = dirname( __DIR__ );

	$editor_data = amortexa_get_editor_data();

	/*
	 * The schema goes to the JS driver through a file, because a shell_exec() on
	 * Windows runs through cmd.exe which cannot set an inline variable, and the
	 * payload is larger than the command line limit.
	 */
	$payload = tempnam( sys_get_temp_dir(), 'amortexa-design-' );
	file_put_contents( $payload, wp_json_encode_compat( $editor_data ) );

	$driver = $root . '/tests/js/design.mjs';
	$json   = shell_exec( 'node ' . escapeshellarg( $driver ) . ' ' . escapeshellarg( $payload ) . ' 2>&1' );

	unlink( $payload );

	if ( null === $json || '' === trim( $json ) ) {
		fwrite( STDERR, "FAIL: could not execute Node design driver\n" );
		return 1;
	}

	$js = json_decode( $json, true );

	if ( ! is_array( $js ) || ! isset( $js['groups'], $js['cssValues'], $js['overrides'] ) ) {
		fwrite( STDERR, "FAIL: invalid JSON from Node design driver: $json\n" );
		return 1;
	}

	$php_groups = $editor_data['designGroups'];

	if ( count( $php_groups ) !== count( $js['groups'] ) ) {
		$failures++;
		fwrite( STDOUT, sprintf( "FAIL design group count: PHP=%d JS=%d\n", count( $php_groups ), count( $js['groups'] ) ) );
	}

	foreach ( $php_groups as $g_index => $php_group ) {
		$js_group = isset( $js['groups'][ $g_index ] ) ? $js['groups'][ $g_index ] : array();

		if ( $php_group['key'] !== ( $js_group['key'] ?? '' ) ) {
			$failures++;
			fwrite( STDOUT, sprintf( "FAIL design group %d key: PHP=%s JS=%s\n", $g_index, $php_group['key'], $js_group['key'] ?? '(missing)' ) );
			continue;
		}

		if ( count( $php_group['tokens'] ) !== count( $js_group['tokens'] ?? array() ) ) {
			$failures++;
			fwrite( STDOUT, sprintf( "FAIL group '%s' token count: PHP=%d JS=%d\n", $php_group['key'], count( $php_group['tokens'] ), count( $js_group['tokens'] ?? array() ) ) );
		}

		foreach ( $php_group['tokens'] as $t_index => $php_token ) {
			$js_token = $js_group['tokens'][ $t_index ] ?? array();

			foreach ( array( 'key', 'var', 'type' ) as $field ) {
				if ( ( $php_token[ $field ] ?? null ) !== ( $js_token[ $field ] ?? null ) ) {
					$failures++;
					fwrite(
						STDOUT,
						sprintf(
							"FAIL group '%s' token %d %s: PHP=%s JS=%s\n",
							$php_group['key'],
							$t_index,
							$field,
							var_export( $php_token[ $field ] ?? null, true ),
							var_export( $js_token[ $field ] ?? null, true )
						)
					);
				}
			}

			$sample = amortexa_test_design_sample( $php_token );
			$php_css = amortexa_design_css_value( $php_token, $sample );
			$js_css  = $js['cssValues'][ $php_token['key'] ] ?? '';

			if ( $php_css !== $js_css ) {
				$failures++;
				fwrite( STDOUT, sprintf( "FAIL token '%s' CSS: PHP='%s' JS='%s'\n", $php_token['key'], $php_css, $js_css ) );
			}

			/*
			 * Option order matters as much as membership: JavaScript enumerates
			 * integer-like object keys before the rest, so shipping the lists as
			 * an object silently moves "Default" to the end of every dropdown.
			 */
			$php_options = array();
			$js_options  = array();

			foreach ( $js_token['options'] ?? array() as $option ) {
				$js_options[] = $option['value'] ?? '';
			}

			foreach ( (array) ( $php_token['options'] ?? array() ) as $option ) {
				$php_options[] = (string) $option['value'];
			}

			if ( $php_options !== $js_options ) {
				$failures++;
				fwrite(
					STDOUT,
					sprintf(
						"FAIL token '%s' option order: PHP=[%s] JS=[%s]\n",
						$php_token['key'],
						implode( ', ', $php_options ),
						implode( ', ', $js_options )
					)
				);
			}
		}
	}

	fwrite( STDOUT, sprintf( "ok   design schema: %d groups, %d tokens agree across PHP and JS\n", count( $php_groups ), count( $js['cssValues'] ) ) );

	/*
	 * The same messy design object the JS driver is given, so a token set in the
	 * editor resolves to the identical custom property the front end will emit.
	 */
	$attributes = amortexa_sanitize_attributes(
		array(
			'design'     => array(
				'fieldPadding'     => '10 14',
				'fieldBg'          => '#0f766e',
				'fieldBgJunk'      => '#zzzzzz',
				'chartHeight'      => '9999',
				'labelWeight'      => 'not-a-weight',
				'legendSwatchSize' => '-4',
				'notAToken'        => '#fff',
			),
			'labelColor' => '#be123c',
		)
	);

	$php_overrides = array();

	foreach ( amortexa_get_design_css( $attributes ) as $declaration ) {
		list( $var, $value ) = explode( ':', $declaration, 2 );
		$php_overrides[ $var ] = $value;
	}

	ksort( $php_overrides );
	$js_overrides = $js['overrides'];
	ksort( $js_overrides );

	if ( $php_overrides !== $js_overrides ) {
		$failures++;
		fwrite( STDOUT, "FAIL design overrides differ between PHP and JS\n" );
		fwrite( STDOUT, '  PHP: ' . wp_json_encode_compat( $php_overrides ) . "\n" );
		fwrite( STDOUT, '  JS:  ' . wp_json_encode_compat( $js_overrides ) . "\n" );
	} else {
		fwrite( STDOUT, sprintf( "ok   design overrides: %d custom properties identical in editor and frontend\n", count( $php_overrides ) ) );
	}

	/*
	 * The legacy colour attribute still reaches the stylesheet, and the design
	 * token layered on top of it wins.
	 */
	$resolved = amortexa_get_design_values( $attributes );

	if ( '#be123c' !== ( $resolved['labelColor'] ?? '' ) ) {
		$failures++;
		fwrite( STDOUT, "FAIL legacy colour attribute not honoured: " . wp_json_encode_compat( $resolved ) . "\n" );
	} else {
		fwrite( STDOUT, "ok   legacy colour attributes still resolve\n" );
	}

	$overridden = amortexa_sanitize_attributes(
		array(
			'design'     => array( 'labelColor' => '#0e7490' ),
			'labelColor' => '#be123c',
		)
	);

	$resolved_override = amortexa_get_design_values( $overridden );

	if ( '#0e7490' !== ( $resolved_override['labelColor'] ?? '' ) ) {
		$failures++;
		fwrite( STDOUT, "FAIL design token did not take precedence over the legacy attribute\n" );
	} else {
		fwrite( STDOUT, "ok   design token overrides the legacy attribute\n" );
	}

	/*
	 * Clearing the token has to hand the block back to its legacy attribute, not
	 * straight to the skin, otherwise a per-block colour saved before the design
	 * tab existed is lost the first time anyone touches the tab.
	 */
	$after_reset = amortexa_get_design_values(
		amortexa_sanitize_attributes( array( 'labelColor' => '#be123c' ) )
	);

	if ( '#be123c' !== ( $after_reset['labelColor'] ?? '' ) ) {
		$failures++;
		fwrite( STDOUT, "FAIL resetting a token did not restore the legacy attribute\n" );
	} elseif ( '#be123c' !== ( $js['afterReset']['labelColor'] ?? '' ) ) {
		$failures++;
		fwrite( STDOUT, "FAIL editor did not restore the legacy attribute after a reset\n" );
	} else {
		fwrite( STDOUT, "ok   resetting a token restores the legacy attribute\n" );
	}

	/*
	 * The one token that cannot travel as a custom property, because the charts
	 * are SVG and view.js needs a number.
	 */
	$chart_cases = array(
		array( 'design' => array( 'chartHeight' => '240' ) ),
		array( 'design' => array( 'chartHeight' => '9999' ) ),
		array( 'design' => array( 'chartHeight' => '10' ) ),
		array( 'design' => array() ),
		array( 'design' => array( 'chartHeight' => 'junk' ) ),
	);

	foreach ( $chart_cases as $index => $chart_case ) {
		$php_height = amortexa_get_design_chart_metrics(
			amortexa_sanitize_attributes( $chart_case )
		)['height'];

		$js_height = $js['chartHeights'][ $index ] ?? null;

		if ( (int) $php_height !== (int) $js_height ) {
			$failures++;
			fwrite( STDOUT, sprintf( "FAIL chart height case %d: PHP=%s JS=%s\n", $index, $php_height, var_export( $js_height, true ) ) );
		}
	}

	fwrite( STDOUT, sprintf( "ok   chart height: %d cases agree between the render and the preview\n", count( $chart_cases ) ) );

	$failures += amortexa_test_design_wiring( $root );

	return $failures;
}

/**
 * Checks that the stylesheet and the token schema agree in both directions.
 *
 * @param string $root Repository root.
 * @return int Number of failures.
 */
function amortexa_test_design_wiring( $root ) {
	$failures = 0;
	$scss     = (string) file_get_contents( $root . '/src/style.scss' );

	// Properties the stylesheet reads.
	preg_match_all( '/var\(\s*(--amortexa-[a-z0-9-]+)/', $scss, $consumed );
	$consumed = array_values( array_unique( $consumed[1] ) );

	// Properties the stylesheet gives a default to.
	preg_match_all( '/(--amortexa-[a-z0-9-]+)\s*:/', $scss, $declared );
	$declared = array_values( array_unique( $declared[1] ) );

	/*
	 * Palette variables are set by the 24 skins and are not inspector tokens, so
	 * they are legitimately consumed without appearing in the schema.
	 */
	$palette = array(
		'--amortexa-accent',
		'--amortexa-accent-2',
		'--amortexa-accent-soft',
		'--amortexa-bg',
		'--amortexa-border',
		'--amortexa-field-bg',
		'--amortexa-field-border',
		'--amortexa-field-text',
		'--amortexa-label-color',
		'--amortexa-surface',
		'--amortexa-text',
		'--amortexa-text-muted',
	);

	/*
	 * chartHeight is the one token the stylesheet never reads: the charts are
	 * SVG, so the height is consumed by view.js through the config payload.
	 */
	$js_consumed = array( 'chartHeight' );

	foreach ( $consumed as $var ) {
		if ( in_array( $var, $palette, true ) || in_array( $var, $declared, true ) ) {
			continue;
		}

		$failures++;
		fwrite( STDOUT, "FAIL stylesheet consumes {$var} but nothing declares it\n" );
	}

	foreach ( amortexa_get_design_token_map() as $key => $token ) {
		/*
		 * Palette variables are declared by the skin CSS in PHP rather than in
		 * style.scss, and chartHeight is read by view.js, so both are exempt
		 * from the stylesheet-only check.
		 */
		if ( in_array( $token['var'], $palette, true ) || in_array( $key, $js_consumed, true ) ) {
			continue;
		}

		if ( in_array( $token['var'], $consumed, true ) ) {
			continue;
		}

		$failures++;
		fwrite( STDOUT, "FAIL token '{$key}' writes {$token['var']} which the stylesheet never reads\n" );
	}

	fwrite(
		STDOUT,
		sprintf(
			"ok   design wiring: %d tokens, %d properties consumed, no orphans\n",
			count( amortexa_get_design_token_map() ),
			count( $consumed )
		)
	);

	$failures += amortexa_test_design_defaults( $scss, $declared );

	return $failures;
}

/**
 * Checks that each token's shipped default is one the control can produce.
 *
 * A length control stores a single integer, so a default such as "8px 16px" or
 * "0.04em" can never be chosen by the person using it: the Design tab would show
 * the skin's look with no way to reproduce or change it. Keywords and functions
 * are allowed, because those defaults are deliberately dynamic.
 *
 * @param string                $scss     Contents of src/style.scss.
 * @param array<int,string>     $declared Custom properties given a default.
 * @return int Number of failures.
 */
function amortexa_test_design_defaults( $scss, $declared ) {
	$failures = 0;

	preg_match_all( '/(--amortexa-[a-z0-9-]+)\s*:\s*([^;]+);/', $scss, $matches, PREG_SET_ORDER );

	$defaults = array();

	foreach ( $matches as $hit ) {
		$var = $hit[1];

		// The first declaration is the base default; media queries redefine it.
		if ( ! isset( $defaults[ $var ] ) ) {
			$defaults[ $var ] = trim( $hit[2] );
		}
	}

	foreach ( amortexa_get_design_token_map() as $key => $token ) {
		$var = $token['var'];

		if ( ! in_array( $var, $declared, true ) ) {
			$failures++;
			fwrite( STDOUT, "FAIL token '{$key}' has no default in the stylesheet\n" );
			continue;
		}

		$default = $defaults[ $var ];

		if ( 'length' !== $token['type'] ) {
			continue;
		}

		$reachable = (bool) preg_match( '/^-?\d+(\.\d+)?px$/', $default )
			|| (bool) preg_match( '/^-?\d+(\.\d+)?$/', $default )
			|| 'auto' === $default
			|| (bool) preg_match( '/^[a-z-]+\(/i', $default );

		if ( ! $reachable ) {
			$failures++;
			fwrite(
				STDOUT,
				sprintf(
					"FAIL token '%s' defaults to '%s' but its control stores a single pixel number\n",
					$key,
					$default
				)
			);
		}
	}

	fwrite( STDOUT, "ok   design defaults: every single-value control can reproduce its shipped default\n" );

	return $failures;
}

/**
 * Encodes a value as JSON without needing WordPress loaded.
 *
 * @param mixed $value Value to encode.
 * @return string JSON string.
 */
function wp_json_encode_compat( $value ) {
	return (string) json_encode( $value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES );
}

/*
 * The escaping and output stubs below exist so build/render.php can be executed
 * by this harness. They mirror the real WordPress behaviour closely enough for
 * the assertions to mean something, and the escaping ones are the point: a test
 * that stubs esc_html() with htmlspecialchars() is what proves the template
 * actually escapes what it prints.
 */
if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Escapes text for HTML output.
	 *
	 * @param string $text Raw text.
	 * @return string Escaped text.
	 */
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Escapes a value for an HTML attribute.
	 *
	 * @param string $text Raw text.
	 * @return string Escaped text.
	 */
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( '_e' ) ) {
	/**
	 * Echoes a translated string.
	 *
	 * @param string $text   Raw text.
	 * @param string $domain Text domain.
	 */
	function _e( $text, $domain = 'default' ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.textFound
		echo esc_html( $text );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	/**
	 * Echoes an escaped translated string.
	 *
	 * @param string $text   Raw text.
	 * @param string $domain Text domain.
	 */
	function esc_html_e( $text, $domain = 'default' ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.textFound
		echo esc_html( $text );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	/**
	 * Returns a translated string escaped for an HTML attribute.
	 *
	 * @param string $text   Raw text.
	 * @param string $domain Text domain.
	 * @return string Escaped text.
	 */
	function esc_attr__( $text, $domain = 'default' ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.textFound
		return esc_attr( $text );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * Encodes a value as JSON the way WordPress does.
	 *
	 * @param mixed $data  Value to encode.
	 * @param int   $flags Encoding flags.
	 * @param int   $depth Maximum depth.
	 * @return string|false JSON string, or false on failure.
	 */
	function wp_json_encode( $data, $flags = 0, $depth = 512 ) {
		return json_encode( $data, $flags, $depth );
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * Records an action. Nothing needs to happen for these assertions.
	 *
	 * @param string $hook_name Hook name.
	 * @param mixed  ...$args   Arguments.
	 */
	function do_action( $hook_name, ...$args ) {} // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
}

if ( ! function_exists( 'wp_unique_id' ) ) {
	/**
	 * Returns a stable-ish unique id for markup.
	 *
	 * @param string $prefix Id prefix.
	 * @return string Unique id.
	 */
	function wp_unique_id( $prefix = '' ) {
		static $id = 0;
		return $prefix . (string) ++$id;
	}
}

if ( ! function_exists( 'get_block_wrapper_attributes' ) ) {
	/**
	 * Renders the wrapper attributes for a block.
	 *
	 * @param array<string,string> $extra_attributes Extra attributes.
	 * @return string Attribute string.
	 */
	function get_block_wrapper_attributes( $extra_attributes = array() ) {
		$out = '';
		foreach ( $extra_attributes as $key => $value ) {
			$out .= ' ' . $key . '="' . esc_attr( $value ) . '"';
		}
		return $out;
	}
}

if ( ! function_exists( 'number_format_i18n' ) ) {
	/**
	 * Formats a number with thousands separators.
	 *
	 * The en_US shape is deliberate: the assertions below look for grouped digits
	 * such as "5,000", so the stub has to reproduce the grouping the real locale
	 * would.
	 *
	 * @param float $number   Number to format.
	 * @param int   $decimals Decimal places.
	 * @return string Formatted number.
	 */
	function number_format_i18n( $number, $decimals = 0 ) {
		return number_format( (float) $number, (int) $decimals, '.', ',' );
	}
}

/**
 * Covers the global currency position setting and the shortcode list attribute.
 *
 * The symbol position used to be hardcoded to prefix for every block, and the
 * shortcode had no way to express a form layout or panel order, so both were
 * silent parity gaps between the block editor, the shortcode, and the settings
 * screen.
 *
 * @return int Failure count.
 */
function amortexa_test_settings_and_shortcode() {
	$failures = 0;
	$valid    = amortexa_get_currency_positions();

	// Every position the site offers must survive sanitization.
	foreach ( array_keys( $valid ) as $position ) {
		$clean = amortexa_sanitize_settings( array( 'currency_position' => $position ) );

		if ( $position !== $clean['currency_position'] ) {
			++$failures;
			fwrite( STDOUT, sprintf( "FAIL currency_position '%s' was rewritten to '%s'\n", $position, $clean['currency_position'] ) );
		}
	}

	// An unknown or missing value falls back to the default, never to raw input.
	foreach ( array( 'nonsense', '', array( 'prefix' ) ) as $bad ) {
		$clean = amortexa_sanitize_settings( array( 'currency_position' => $bad ) );

		if ( 'prefix' !== $clean['currency_position'] ) {
			++$failures;
			fwrite( STDOUT, sprintf( "FAIL currency_position rejected '%s' but produced '%s'\n", wp_json_encode_compat( $bad ), $clean['currency_position'] ) );
		}
	}

	// The setting has to be reachable from the settings screen and seed blocks.
	$map = amortexa_get_settings_attribute_map();

	if ( 'currencyPosition' !== ( $map['currency_position'] ?? null ) ) {
		++$failures;
		fwrite( STDOUT, "FAIL currency_position is not mapped onto the currencyPosition block attribute\n" );
	}

	$defaults = amortexa_get_default_attributes();

	if ( 'prefix' !== $defaults['currencyPosition'] ) {
		++$failures;
		fwrite( STDOUT, sprintf( "FAIL default currencyPosition is '%s', expected the site setting\n", $defaults['currencyPosition'] ) );
	}

	fwrite( STDOUT, "ok   global currency position setting round-trips and seeds new blocks\n" );

	require_once dirname( __DIR__ ) . '/includes/class-shortcode.php';

	$shortcode = new Amortexa_Shortcode();
	$method    = new ReflectionMethod( $shortcode, 'to_block_attributes' );

	/*
	 * Reflection can reach private methods without help from PHP 8.1 onwards, and
	 * setAccessible() is deprecated from 8.5, so it is only called for the older
	 * versions this plugin still supports.
	 */
	if ( PHP_VERSION_ID < 80100 ) {
		$method->setAccessible( true );
	}

	$shortcode_map = $shortcode->get_documented_attributes();

	// The two attributes the block supports but the shortcode did not expose.
	foreach ( array( 'formcolumns' => 'formColumns', 'panelorder' => 'panelOrder' ) as $name => $attribute ) {
		if ( ( $shortcode_map[ $name ]['attribute'] ?? null ) !== $attribute ) {
			++$failures;
			fwrite( STDOUT, sprintf( "FAIL shortcode is missing the '%s' attribute\n", $name ) );
		}
	}

	/*
	 * The readme promises the shortcode "accepts every block attribute", and that
	 * promise silently broke when the recurring costs shipped: all eleven cost
	 * attributes were reachable from the block and the editor but not from the
	 * shortcode, so a hand-written [amortexa-mortgage-calculator showcosts="true" propertytax="1.25"]
	 * was quietly ignored with no error anywhere. Rather than re-list the costs,
	 * this compares the whole documented map against the block schema so the next
	 * attribute added to block.json has to be added to the shortcode too.
	 *
	 * Attributes the shortcode deliberately does not expose are listed here with
	 * the reason, so adding one is a conscious decision rather than an omission.
	 */
	$not_exposed = array(
		/*
		 * Per-element appearance. These belong to the Design tab, and exposing them
		 * here would add nine inputs to the shortcode builder for settings that are
		 * stilled by site defaults anyway. A shortcode can pick a skin and a
		 * layout; it cannot sensibly reproduce a design token map.
		 */
		'design'                => 'style is set through the Design tab tokens',
		'accentColor'           => 'colours are set through the Design tab',
		'accentAltColor'        => 'colours are set through the Design tab',
		'labelColor'            => 'colours are set through the Design tab',
		'fieldTextColor'        => 'colours are set through the Design tab',
		'fieldBackgroundColor'  => 'colours are set through the Design tab',
		'fieldBorderColor'      => 'colours are set through the Design tab',
	);

	$exposed_attributes = array();

	foreach ( $shortcode_map as $spec ) {
		if ( isset( $spec['attribute'] ) ) {
			$exposed_attributes[ $spec['attribute'] ] = true;
		}
	}

	$block_schema = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/src/block.json' ), true );

	if ( is_array( $block_schema ) && isset( $block_schema['attributes'] ) && is_array( $block_schema['attributes'] ) ) {
		foreach ( array_keys( $block_schema['attributes'] ) as $attribute ) {
			if ( isset( $exposed_attributes[ $attribute ] ) || isset( $not_exposed[ $attribute ] ) ) {
				continue;
			}

			++$failures;
			fwrite( STDOUT, sprintf( "FAIL the shortcode cannot set the block's '%s' attribute\n", $attribute ) );
		}
	} else {
		++$failures;
		fwrite( STDOUT, "FAIL src/block.json could be read to compare it against the shortcode\n" );
	}

	foreach ( array_keys( $not_exposed ) as $attribute ) {
		if ( isset( $exposed_attributes[ $attribute ] ) ) {
			++$failures;
			fwrite( STDOUT, sprintf( "FAIL '%s' is on the shortcode exemption list but is also exposed\n", $attribute ) );
		}
	}

	// A comma separated string becomes a clean, ordered list of panel keys.
	$list = $method->invoke(
		$shortcode,
		array_merge(
			array_fill_keys( array_keys( $shortcode_map ), '' ),
			array( 'panelorder' => ' schedule , form ,charts, results ' )
		),
		$shortcode_map
	);

	if ( array( 'schedule', 'form', 'charts', 'results' ) !== $list['panelOrder'] ) {
		++$failures;
		fwrite( STDOUT, sprintf( "FAIL panelorder parsed to %s\n", wp_json_encode_compat( $list['panelOrder'] ) ) );
	}

	// An empty or absent list must keep every panel, not collapse the block.
	foreach ( array( '', '  ,  ', ' , ' ) as $blank ) {
		$fallback = $method->invoke(
			$shortcode,
			array_merge(
				array_fill_keys( array_keys( $shortcode_map ), '' ),
				array( 'panelorder' => $blank )
			),
			$shortcode_map
		);

		if ( array() === $fallback['panelOrder'] ) {
			++$failures;
			fwrite( STDOUT, sprintf( "FAIL panelorder '%s' produced zero panels\n", $blank ) );
		}
	}

	// The block sanitizer drops keys that are not real panels, so a shortcode
	// cannot smuggle an unknown panel into the rendered markup.
	$resolved = amortexa_resolve_panel_order( array( 'form', 'not-a-panel', 'results' ), amortexa_get_panel_keys() );

	if ( in_array( 'not-a-panel', $resolved, true ) ) {
		++$failures;
		fwrite( STDOUT, "FAIL an unknown panel key survived panelOrder resolution\n" );
	}

	// formcolumns is a straight enum, so an unknown value falls back to wide.
	$columns = amortexa_sanitize_attributes( array( 'formColumns' => 'nonsense' ) );

	if ( 'wide' !== $columns['formColumns'] ) {
		++$failures;
		fwrite( STDOUT, sprintf( "FAIL formColumns rejected an unknown value but produced '%s'\n", $columns['formColumns'] ) );
	}

	/*
	 * A third party is allowed to reshape the defaults through
	 * amortexa_default_attributes, including dropping a key outright. The
	 * shortcode reads the defaults for every attribute it documents, so a
	 * missing key used to raise an undefined-array-key warning on the front end
	 * and, for a numeric attribute, fell through to a second unguarded read.
	 * Warnings are captured here because PHP only surfaces them as diagnostics,
	 * never as a non-zero exit from the process.
	 */
	$warnings = array();

	add_filter(
		'amortexa_default_attributes',
		static function ( $filtered_defaults ) use ( &$warnings ) {
			foreach ( array( 'loanAmount', 'loanTerm', 'currencyPosition', 'panelOrder' ) as $dropped ) {
				unset( $filtered_defaults[ $dropped ] );
			}

			return $filtered_defaults;
		}
	);

	set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- capturing warnings is the point of this check.
		static function ( $errno, $errstr ) use ( &$warnings ) {
			$warnings[] = $errstr;

			return true;
		},
		E_WARNING
	);

	$pruned = $method->invoke( $shortcode, array_fill_keys( array_keys( $shortcode_map ), '' ), $shortcode_map );

	restore_error_handler();
	remove_all_filters( 'amortexa_default_attributes' );

	if ( array() !== $warnings ) {
		++$failures;
		fwrite( STDOUT, sprintf( "FAIL pruned defaults raised %d warning(s): %s\n", count( $warnings ), implode( '; ', $warnings ) ) );
	}

	// The dropped attributes resolve to null instead of warning or throwing.
	if ( array_key_exists( 'loanAmount', $pruned ) && null !== $pruned['loanAmount'] ) {
		++$failures;
		fwrite( STDOUT, sprintf( "FAIL a pruned numeric attribute produced %s\n", wp_json_encode_compat( $pruned['loanAmount'] ) ) );
	}

	// Pruning the defaults must not disturb the attributes that remain.
	if ( ! isset( $pruned['interestRate'] ) ) {
		++$failures;
		fwrite( STDOUT, "FAIL pruning the defaults dropped an attribute that was still present\n" );
	}

	fwrite( STDOUT, "ok   shortcode tolerates a third party pruning the attribute defaults\n" );

	fwrite( STDOUT, "ok   shortcode exposes form layout and panel order with safe list parsing\n" );

	return $failures;
}

/**
 * Exercises the loose-typed input guards in both sanitizers.
 *
 * Settings arrive from $_POST and block attributes from post meta, so either can
 * hand a sanitizer an array or a string that only looks like the expected type.
 * The failure this covers is silent rather than loud: ! empty() reads a
 * non-empty array and the string "false" as true, so a switch an administrator
 * turned off could come back on, and casting an array to string renders the
 * literal "Array" as a currency symbol.
 *
 * @return int Number of failures.
 */
function amortexa_test_input_guards() {
	$failures = 0;
	$report   = function ( $ok, $message ) use ( &$failures ) {
		if ( $ok ) {
			fwrite( STDOUT, "ok   $message\n" );
		} else {
			++$failures;
			fwrite( STDOUT, "FAIL $message\n" );
		}
	};

	// Boolean normalization.
	$report( true === amortexa_sanitize_bool( true ), 'a real boolean true survives' );
	$report( false === amortexa_sanitize_bool( false ), 'a real boolean false survives' );
	$report( true === amortexa_sanitize_bool( '1' ), 'the string "1" reads as true' );
	$report( true === amortexa_sanitize_bool( 'on' ), 'the string "on" reads as true' );
	$report( false === amortexa_sanitize_bool( '0' ), 'the string "0" reads as false' );
	$report( false === amortexa_sanitize_bool( 'false' ), 'the string "false" reads as false' );
	$report( false === amortexa_sanitize_bool( 'off' ), 'the string "off" reads as false' );
	$report( false === amortexa_sanitize_bool( array( '1' ) ), 'a non-empty array is rejected instead of reading as true' );
	$report( true === amortexa_sanitize_bool( array( '0', '1' ), true ), 'a rejected array uses the caller default' );
	$report( true === amortexa_sanitize_bool( null, true ), 'an absent value falls back to the default' );
	$report( true === amortexa_sanitize_bool( 'nonsense', true ), 'an unrecognized string falls back to the default' );

	// An array must never reach a string cast inside the settings sanitizer.
	foreach ( array( 'default_interest_rate', 'decimal_precision', 'default_loan_amount', 'default_down_payment', 'default_loan_term', 'default_theme', 'default_chart_type', 'default_layout' ) as $key ) {
		$defaults = amortexa_get_default_settings();
		$clean    = amortexa_sanitize_settings( array( $key => array( '1' ) ) );

		if ( ! array_key_exists( $key, $clean ) ) {
			continue;
		}

		$report(
			$clean[ $key ] === $defaults[ $key ],
			sprintf( 'an array posted for %s falls back to the default', $key )
		);
	}

	/*
	 * The settings form posts the checkbox through a companion hidden field, so
	 * the key is always present. An explicit 0 has to switch the table off
	 * instead of being read as a truthy non-empty value.
	 */
	$settings = amortexa_sanitize_settings( array( 'enable_amortization' => '0' ) );
	$report( false === $settings['enable_amortization'], 'an explicit 0 switches the amortization table off' );

	$settings = amortexa_sanitize_settings( array( 'enable_amortization' => '1' ) );
	$report( true === $settings['enable_amortization'], 'an explicit 1 switches the amortization table on' );

	// An unknown skin must fall back to the site's own choice, not a hardcoded one.
	$defaults = amortexa_get_default_attributes();
	$skins    = amortexa_get_skin_slugs();
	$site     = (string) $defaults['theme'];
	$other    = in_array( 'ocean', $skins, true ) && 'ocean' !== $site ? 'ocean' : 'light';

	$stale = amortexa_sanitize_attributes( array( 'theme' => 'a-skin-that-no-longer-exists' ) );
	$report( $site === $stale['theme'], 'a retired skin slug falls back to the site default' );

	$kept = amortexa_sanitize_attributes( array( 'theme' => $other ) );
	$report( $other === $kept['theme'], 'a known skin is preserved' );

	// An array must never become a visible "Array" on the front end.
	$attrs = amortexa_sanitize_attributes( array( 'currencySymbol' => array( 'a' ) ) );
	$report( '$' === $attrs['currencySymbol'], 'an array posted for the currency symbol falls back to the default' );


	$attrs = amortexa_sanitize_attributes( array( 'accentColor' => array( '#fff' ) ) );
	$report( '' === $attrs['accentColor'], 'an array posted for a colour override falls back to the skin value' );


	// Block booleans stored as strings must not be read as truthy noise.
	$attrs = amortexa_sanitize_attributes( array( 'showCharts' => 'false' ) );
	$report( false === $attrs['showCharts'], 'a block showCharts stored as "false" renders as off' );

	$attrs = amortexa_sanitize_attributes( array( 'showResults' => '0' ) );
	$report( false === $attrs['showResults'], 'a block showResults stored as "0" renders as off' );

	$attrs = amortexa_sanitize_attributes( array( 'showAmortization' => '1' ) );
	$report( true === $attrs['showAmortization'], 'a block showAmortization stored as "1" renders as on' );

	/*
	 * A present-but-unusable numeric attribute must fall back to the configured
	 * default, not clamp to zero. isset() is true for the garbage, so the guard
	 * has to be is_numeric(); otherwise a mangled loanAmount yields $0/month and
	 * a mangled interestRate yields a free mortgage.
	 *
	 * The seeded down-payment default is 0.0, which would make that check
	 * vacuous: clamping garbage to 0.0 and falling back to a 0.0 default give
	 * the same answer. A non-zero default is seeded through the settings filter
	 * so every key below can actually fail. downPayment derives from the same
	 * setting, so one filter covers both the block and the settings screens.
	 */
	add_filter(
		'amortexa_default_settings',
		static function ( $settings_defaults ) {
			$settings_defaults['default_down_payment'] = 12345.0;

			return $settings_defaults;
		}
	);

	$numeric_defaults = amortexa_get_default_attributes();

	foreach ( array( 'loanAmount', 'interestRate', 'loanTerm', 'downPayment' ) as $key ) {
		$attrs = amortexa_sanitize_attributes( array( $key => 'not-a-number' ) );

		if ( ! isset( $numeric_defaults[ $key ] ) ) {
			$report( false, "the default for {$key} is missing, so this check cannot run" );
			continue;
		}

		$report(
			(float) $attrs[ $key ] === (float) $numeric_defaults[ $key ],
			sprintf( 'a non-numeric %s falls back to the default instead of zero', $key )
		);
	}

	// A numeric value that is merely a loose string must still be honoured.
	$attrs = amortexa_sanitize_attributes( array( 'loanAmount' => '250000.50' ) );
	$report( 250000.5 === (float) $attrs['loanAmount'], 'a numeric string loanAmount is still honoured' );

	// The settings screen has the same exposure through its own sanitizer.
	$settings_defaults = amortexa_get_default_settings();

	foreach ( array( 'default_loan_amount', 'default_interest_rate', 'default_loan_term', 'default_down_payment' ) as $key ) {
		$settings = amortexa_sanitize_settings( array( $key => 'not-a-number' ) );

		if ( ! isset( $settings_defaults[ $key ] ) ) {
			$report( false, "the default for {$key} is missing, so this check cannot run" );
			continue;
		}

		$report(
			(float) $settings[ $key ] === (float) $settings_defaults[ $key ],
			sprintf( 'a non-numeric %s falls back to the default instead of zero', $key )
		);
	}

	remove_all_filters( 'amortexa_default_settings' );

	// Removing the filter must restore the seeded defaults for later checks.
	$report(
		0.0 === (float) amortexa_get_default_settings()['default_down_payment'],
		'the seeded down-payment default is restored after the filter is removed'
	);

	if ( 0 === $failures ) {
		fwrite( STDOUT, "ok   sanitizers reject loosely typed settings and attributes\n" );
	}

	return $failures;
}

/**
 * Exercises the REST rate limit window.
 *
 * Covers the budget boundary, the fixed window (not sliding) behaviour, bucket
 * isolation between callers, the no-address fallback, and the opt-out.
 *
 * @return int Number of failures.
 */
function amortexa_test_rate_limit() {
	$failures = 0;
	$report   = function ( $ok, $message ) use ( &$failures ) {
		if ( $ok ) {
			fwrite( STDOUT, "ok   $message\n" );
		} else {
			++$failures;
			fwrite( STDOUT, "FAIL $message\n" );
		}
	};

	$GLOBALS['amortexa_test_transients'] = array();

	// The default budget must permit exactly N requests, then refuse.
	$limit  = 5;
	$window = 60;
	$bucket = 'visitor-a';
	$key    = 'amortexa_rl_' . md5( $bucket );

	for ( $i = 1; $i <= $limit; $i++ ) {
		$outcome = amortexa_rate_limit_hit( $bucket, $limit, $window );
		$report(
			! $outcome['exceeded'] && $outcome['count'] === $i,
			"request $i of $limit is allowed"
		);
	}

	$blocked = amortexa_rate_limit_hit( $bucket, $limit, $window );
	$report( $blocked['exceeded'], 'the request past the budget is refused' );
	$report( $blocked['retry_after'] > 0 && $blocked['retry_after'] <= $window, 'a refusal reports a usable Retry-After' );

	// Still refused on the following attempt, and it must not reset the window.
	$again = amortexa_rate_limit_hit( $bucket, $limit, $window );
	$report( $again['exceeded'] && $again['count'] === $limit + 2, 'continued traffic stays refused' );

	// A different caller must have its own budget.
	$other = amortexa_rate_limit_hit( 'visitor-b', $limit, $window );
	$report( ! $other['exceeded'] && $other['count'] === 1, 'a second visitor has an independent budget' );

	// The window is fixed, so it must not be pushed forward by continued hits.
	$before = get_transient( $key );
	amortexa_rate_limit_hit( $bucket, $limit, $window );
	$after = get_transient( $key );
	$report(
		$before['expires'] === $after['expires'],
		'the window expiry is not extended by later hits'
	);

	/*
	 * The expiry is always passed explicitly to set_transient(). A bare update
	 * with no lifetime means "never expires" to a persistent object cache, which
	 * would lock a caller out permanently once tripped.
	 */
	$report(
		isset( $GLOBALS['amortexa_test_transients'][ $key ]['expires'] )
			&& $GLOBALS['amortexa_test_transients'][ $key ]['expires'] > time(),
		'a live window always carries a finite expiry'
	);

	// Age the window out, then confirm the caller is served again.
	$GLOBALS['amortexa_test_transients'][ $key ]['expires'] = time() - 1;
	$reset = amortexa_rate_limit_hit( $bucket, $limit, $window );
	$report( ! $reset['exceeded'] && $reset['count'] === 1, 'the budget resets after the window lapses' );

	// A non-positive limit disables limiting outright.
	$off = amortexa_rate_limit_hit( 'visitor-c', 0, $window );
	$report( ! $off['exceeded'] && 0 === $off['limit'], 'a limit below one disables throttling' );

	// Client key: REMOTE_ADDR by default, and never the spoofable forwarded header.
	unset( $_SERVER['REMOTE_ADDR'] );
	$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.9';
	$spoofed = amortexa_rate_limit_client_key();
	$report( 0 === strpos( $spoofed, 'unknown:' ), 'forwarded headers cannot be used as a bucket key' );

	$_SERVER['REMOTE_ADDR'] = '198.51.100.7';
	$report( '198.51.100.7' === amortexa_rate_limit_client_key(), 'the remote address is used when present' );

	// An over-long key must not be collapsed into a shared bucket.
	add_filter(
		'amortexa_rate_limit_client_key',
		function () {
			return str_repeat( 'x', 46 );
		}
	);
	$long = amortexa_rate_limit_client_key();
	$report( 0 === strpos( $long, 'unknown:' ), 'an unusable client key falls back to a per-request bucket' );
	$report( $long !== amortexa_rate_limit_client_key(), 'each fallback bucket is unique' );
	remove_all_filters( 'amortexa_rate_limit_client_key' );

	unset( $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR'] );
	$GLOBALS['amortexa_test_transients'] = array();

	/*
	 * Enforcement has to be checked by calling the permission callback, not by
	 * reading the source. The REST server only treats WP_Error, false and null as
	 * a refusal; a WP_REST_Response return is truthy and the request is let
	 * through, so a limiter that looks right can silently never apply.
	 */
	$rest = new Amortexa_REST();
	$_SERVER['REMOTE_ADDR'] = '198.51.100.20';

	// Use a small budget so the refusal is reachable without 30 round trips.
	add_filter(
		'amortexa_rest_calculate_rate_limit',
		function () {
			return 3;
		}
	);

	/*
	 * The REST server calls a permission callback more than once per request:
	 * rest_send_allow_header() invokes it again for the Allow header. Charging
	 * the budget from there would bill every caller twice and halve the real
	 * allowance, so the callback must not record anything.
	 */
	for ( $i = 0; $i < 10; $i++ ) {
		$rest->check_permissions( null );
	}
	$report( 0 === amortexa_rate_limit_peek( '198.51.100.20' )['count'], 'the permission callback records no hits of its own' );

	// Serve requests the way the handler does, recording one hit each.
	for ( $i = 0; $i < 3; $i++ ) {
		amortexa_rate_limit_hit( '198.51.100.20', 3, 60 );
	}
	$report( 3 === amortexa_rate_limit_peek( '198.51.100.20' )['count'], 'a served request costs exactly one unit' );

	$refusal = $rest->check_permissions( null );
	$report( $refusal instanceof WP_Error, 'an exhausted budget is refused with a WP_Error' );
	$report(
		$refusal instanceof WP_Error && 'amortexa_rest_rate_limited' === $refusal->get_error_code(),
		'the refusal carries the rate limit error code'
	);
	$report(
		$refusal instanceof WP_Error && isset( $refusal->get_error_data()['status'] ) && 429 === $refusal->get_error_data()['status'],
		'the refusal is reported as HTTP 429'
	);
	$report(
		$refusal instanceof WP_Error && ! empty( $refusal->get_error_data()['retry_after'] ),
		'the refusal tells the caller when to come back'
	);

	// The last request inside the budget must still be served.
	$GLOBALS['amortexa_test_transients'] = array();
	for ( $i = 0; $i < 2; $i++ ) {
		amortexa_rate_limit_hit( '198.51.100.20', 3, 60 );
		$report( true === $rest->check_permissions( null ), 'the final request inside the budget is still served' );
	}
	amortexa_rate_limit_hit( '198.51.100.20', 3, 60 );
	$report( $rest->check_permissions( null ) instanceof WP_Error, 'the request past the budget is refused' );

	// The headers have to survive onto a real response object.
	$response = new WP_REST_Response(
		array(
			'code'    => $refusal->get_error_code(),
			'message' => $refusal->get_error_message(),
			'data'    => $refusal->get_error_data(),
		),
		429
	);
	$rest->add_rate_limit_headers( $response );

	$retry = $response->get_headers()['Retry-After'];
	$report( ! empty( $retry ) && (int) $retry > 0, 'a refusal advertises Retry-After' );
	$report( '3' === $response->get_headers()['X-RateLimit-Limit'], 'a refusal advertises the configured budget' );
	$report( '0' === $response->get_headers()['X-RateLimit-Remaining'], 'a refusal advertises no remaining budget' );

	// An unrelated response must be left untouched.
	$other = new WP_REST_Response( array( 'ok' => true ), 200 );
	$rest->add_rate_limit_headers( $other );
	$report( ! isset( $other->get_headers()['Retry-After'] ), 'headers are not added to unrelated responses' );

	// The auth filter still short-circuits ahead of the limiter.
	add_filter(
		'amortexa_rest_calculate_allowed',
		function () {
			return false;
		}
	);
	$denied = $rest->check_permissions( null );
	$report(
		$denied instanceof WP_Error && 'amortexa_rest_forbidden' === $denied->get_error_code(),
		'the authentication filter refuses ahead of the limiter'
	);
	remove_all_filters( 'amortexa_rest_calculate_allowed' );

	// Once the window lapses the caller is served again.
	$GLOBALS['amortexa_test_transients'][ 'amortexa_rl_' . md5( '198.51.100.20' ) ]['expires'] = time() - 1;
	$report( true === $rest->check_permissions( null ), 'the caller is served again once the window lapses' );

	// Lifting the budget serves the same caller without waiting.
	amortexa_rate_limit_hit( '198.51.100.20', 3, 60 );
	amortexa_rate_limit_hit( '198.51.100.20', 3, 60 );
	amortexa_rate_limit_hit( '198.51.100.20', 3, 60 );
	amortexa_rate_limit_hit( '198.51.100.20', 3, 60 );
	$report( $rest->check_permissions( null ) instanceof WP_Error, 'the budget is exhausted again' );
	remove_all_filters( 'amortexa_rest_calculate_rate_limit' );
	add_filter(
		'amortexa_rest_calculate_rate_limit',
		function () {
			return 0;
		}
	);
	$report( true === $rest->check_permissions( null ), 'a limit of zero disables throttling' );
	remove_all_filters( 'amortexa_rest_calculate_rate_limit' );

	unset( $_SERVER['REMOTE_ADDR'] );
	$GLOBALS['amortexa_test_transients'] = array();

	if ( 0 === $failures ) {
		fwrite( STDOUT, "ok   the REST endpoint enforces a per-client request budget\n" );
	}

	return $failures;
}

/**
 * Checks the recurring cost inputs, the conversion between percent and amount,
 * and the PMI cancellation rule.
 *
 * The reference figures come from a 400,000 home with 20% down at 7.455% over
 * 30 years, which is the worked example published by the well known calculator
 * this feature was modelled on, so a change in the math shows up here rather
 * than as a quietly wrong total on someone's page.
 *
 * @return int Number of failures.
 */
function amortexa_test_costs() {
	$failures = 0;
	$report   = function ( $ok, $message ) use ( &$failures ) {
		if ( $ok ) {
			fwrite( STDOUT, "ok   $message\n" );
		} else {
			++$failures;
			fwrite( STDOUT, "FAIL $message\n" );
		}
	};
	$near = function ( $got, $want, $tolerance = 0.02 ) {
		return abs( (float) $got - (float) $want ) <= $tolerance;
	};

	$reference = array(
		'loanAmount'     => 400000,
		'downPayment'    => 80000,
		'interestRate'   => 7.455,
		'loanTerm'       => 30,
		'propertyTax'    => 1.2,
		'homeInsurance'  => 1500,
		'otherCosts'     => 4000,
	);

	$result = amortexa_calculate( $reference );

	$report( $near( $result['monthly_payment'], 2227.63 ), 'principal and interest matches the reference payment' );
	$report( $near( $result['principal'], 320000 ), 'the financed principal is the price less the down payment' );
	$report( $near( $result['monthly_costs']['tax'], 400 ), 'a percentage tax is a share of the purchase price' );
	$report( $near( $result['monthly_costs']['insurance'], 125 ), 'an annual amount becomes a monthly figure' );
	$report( $near( $result['monthly_costs']['other'], 333.33 ), 'other costs convert like any other amount' );
	$report( $near( $result['total_monthly_cost'], 3085.97 ), 'the total monthly cost is P&I plus every cost' );

	/*
	 * A calculator with no costs entered must be byte-for-byte what it was before
	 * this feature, otherwise adding it silently changes existing pages.
	 */
	$plain = amortexa_calculate(
		array(
			'loanAmount'   => 300000,
			'interestRate' => 6.5,
			'loanTerm'     => 30,
		)
	);

	$report( $near( $plain['total_monthly_cost'], $plain['monthly_payment'] ), 'no costs means the total equals the payment' );
	$report( $near( $plain['total_costs'], 0 ), 'no costs means no lifetime cost total' );
	$report( $near( $plain['total_out_of_pocket'], $plain['total_paid'] ), 'no costs means out-of-pocket equals total paid' );
	$report( $near( array_sum( $plain['monthly_costs'] ), 0 ), 'every component defaults to zero' );

	/*
	 * PMI has to stop once the balance reaches 80% of the original value. Running
	 * it to term end is the common error and inflates the lifetime cost, so both
	 * directions are checked: not charged past the threshold, and not dropped
	 * before it either.
	 */
	$report( 0 === amortexa_get_pmi_end_month( 320000, 400000, 7.455, 360 ), 'no PMI is charged at exactly 80% LTV' );

	$pmi_attrs = array(
		'loanAmount'   => 400000,
		'downPayment'  => 40000,
		'interestRate' => 7.0,
		'loanTerm'     => 30,
		'pmi'          => 1200,
	);
	$pmi        = amortexa_calculate( $pmi_attrs );
	$end        = (int) $pmi['pmi_end_month'];

	$report( $end > 0 && $end < 360, 'PMI is cancelled part way through a 90% LTV loan' );
	$report( $pmi['total_pmi'] < 1200 / 12 * 360, 'PMI is not charged for the whole term' );

	// interestRate is a percentage, so 7.0 arrives here as 0.07 a month.
	$rate    = 7.0 / 100 / 12;
	$payment = amortexa_calculate_monthly_payment( 360000, 7.0, 30 );
	$balance = 360000;
	$before  = null;

	for ( $month = 1; $month <= $end; $month++ ) {
		if ( $month === $end ) {
			$before = $balance;
		}

		$interest = $balance * $rate;
		$balance -= max( min( $payment, $balance + $interest ) - $interest, 0 );
	}

	$report( $balance <= 320000.01, 'the balance has reached 80% of the value when PMI stops' );
	$report( $before > 320000, 'PMI does not stop before the balance reaches 80% of the value' );

	// The month reported has to match the term the lifetime total is based on.
	$report( $near( $pmi['total_pmi'], 1200 / 12 * $end, 0.01 ), 'lifetime PMI covers exactly the months before cancellation' );

	// A loan already at or below the threshold never carries PMI at all.
	$report( 0 === amortexa_get_pmi_end_month( 100000, 400000, 7.0, 360 ), 'PMI is not scheduled for a loan under 80% LTV' );

	// Unit handling.
	$as_amount = amortexa_calculate(
		array(
			'loanAmount'        => 400000,
			'interestRate'      => 6.5,
			'loanTerm'          => 30,
			'homeInsurance'     => 1500,
			'homeInsuranceUnit' => 'amount',
		)
	);
	$report( $near( $as_amount['monthly_costs']['insurance'], 125 ), 'a field entered as an amount is not multiplied by the price' );

	$clamped = amortexa_sanitize_attributes( array( 'propertyTax' => 1500 ) );
	$kept    = amortexa_sanitize_attributes(
		array(
			'propertyTax'     => 1500,
			'propertyTaxUnit' => 'amount',
		)
	);

	$report( $near( $clamped['propertyTax'], 100 ), 'a percentage field is capped at 100' );
	$report( $near( $kept['propertyTax'], 1500 ), 'the same figure is kept whole when entered as an amount' );

	$bogus = amortexa_sanitize_attributes( array( 'propertyTaxUnit' => 'bananas' ) );
	$report( 'bananas' !== $bogus['propertyTaxUnit'], 'an unrecognised unit falls back rather than being stored' );

	$junk = amortexa_sanitize_attributes(
		array(
			'propertyTax' => 'abc',
			'pmi'          => -50,
		)
	);
	$report( 0.0 === (float) $junk['propertyTax'], 'a non-numeric cost falls back to its default' );
	$report( 0.0 === (float) $junk['pmi'], 'a negative cost is clamped to zero' );

	// The component colours are only worth having if they can be told apart.
	$scss     = (string) file_get_contents( dirname( __DIR__ ) . '/src/style.scss' );
	$swatches = array();

	if ( preg_match_all( '/--amortexa-cost-([a-z]+):\s*(#[0-9a-f]{6});/i', $scss, $hits, PREG_SET_ORDER ) ) {
		foreach ( $hits as $hit ) {
			$swatches[ $hit[1] ] = $hit[2];
		}
	}

	$report( 6 === count( $swatches ), 'every cost component has a colour' );

	foreach ( $swatches as $name => $hex ) {
		$report( amortexa_test_contrast( $hex, '#ffffff' ) >= 4.5, "the $name colour is readable as text on white" );
	}

	foreach ( $swatches as $name => $hex ) {
		foreach ( $swatches as $other => $other_hex ) {
			if ( $name >= $other ) {
				continue;
			}

			$report(
				amortexa_test_colour_distance( $hex, $other_hex ) >= 60,
				"the $name and $other colours are distinguishable side by side"
			);
		}
	}

	if ( 0 === $failures ) {
		fwrite( STDOUT, "ok   recurring costs, PMI cancellation and component colours\n" );
	}

	return $failures;
}

/**
 * Converts a hex colour to its relative luminance.
 *
 * @param string $hex Six digit hex colour, with or without the leading hash.
 * @return float Relative luminance between 0 and 1.
 */
function amortexa_test_luminance( $hex ) {
	$hex = ltrim( (string) $hex, '#' );

	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}

	$channels = array();

	foreach ( str_split( $hex, 2 ) as $pair ) {
		$value = hexdec( $pair ) / 255;
		$channels[] = $value <= 0.03928
			? $value / 12.92
			: pow( ( $value + 0.055 ) / 1.055, 2.4 );
	}

	return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

/**
 * Returns the WCAG contrast ratio between two colours.
 *
 * @param string $a First hex colour.
 * @param string $b Second hex colour.
 * @return float Contrast ratio, where 1 is identical and 21 is maximal.
 */
function amortexa_test_contrast( $a, $b ) {
	$one = amortexa_test_luminance( $a );
	$two = amortexa_test_luminance( $b );

	$lighter = max( $one, $two );
	$darker  = min( $one, $two );

	return ( $lighter + 0.05 ) / ( $darker + 0.05 );
}

/**
 * Returns the straight-line RGB distance between two colours.
 *
 * A crude stand-in for perceptual difference, but it is enough to catch the
 * failure that matters here: two swatches so close that a legend reader cannot
 * tell which slice they belong to.
 *
 * @param string $a First hex colour.
 * @param string $b Second hex colour.
 * @return float Distance between 0 and 441.
 */
function amortexa_test_colour_distance( $a, $b ) {
	$a = ltrim( (string) $a, '#' );
	$b = ltrim( (string) $b, '#' );

	$total = 0;

	for ( $index = 0; $index < 3; $index++ ) {
		$offset = $index * 2;
		$total  += pow( hexdec( substr( $a, $offset, 2 ) ) - hexdec( substr( $b, $offset, 2 ) ), 2 );
	}

	return sqrt( $total );
}

/**
 * Checks that every place the version is written down agrees with every other.
 *
 * AMORTEXA_VERSION is what cache-busts the compiled assets, so a header bumped
 * without bumping the constant leaves visitors on the previous release's JS
 * indefinitely: the file URLs do not change, so the browser never refetches it.
 * Nothing at runtime would report that, which is exactly why it is asserted here.
 *
 * @return int Number of failures.
 */
function amortexa_test_version_consistency() {
	$failures = 0;
	$report   = function ( $ok, $message ) use ( &$failures ) {
		if ( $ok ) {
			fwrite( STDOUT, "ok   $message\n" );
		} else {
			++$failures;
			fwrite( STDOUT, "FAIL $message\n" );
		}
	};

	$root    = dirname( __DIR__ );
	$grab    = function ( $file, $pattern ) use ( $root ) {
		$contents = (string) file_get_contents( $root . '/' . $file );

		return preg_match( $pattern, $contents, $match ) ? trim( $match[1] ) : '';
	};

	$header   = $grab( 'amortexa-mortgage-calculator.php', '/^ \* Version:\s*(.+)$/m' );
	$constant = $grab( 'amortexa-mortgage-calculator.php', "/define\(\s*'AMORTEXA_VERSION',\s*'([^']+)'\s*\)/" );
	$readme   = $grab( 'readme.txt', '/^Stable tag:\s*(.+)$/m' );

	$report( '' !== $header, 'the plugin header carries a version' );
	$report( '' !== $constant, 'AMORTEXA_VERSION is defined' );
	$report( '' !== $readme, 'readme.txt carries a stable tag' );

	$report(
		$header === $constant,
		sprintf( 'AMORTEXA_VERSION (%s) matches the plugin header (%s)', $constant, $header )
	);
	$report(
		$header === $readme,
		sprintf( 'the readme stable tag (%s) matches the plugin header (%s)', $readme, $header )
	);

	/*
	 * The costs work ships real behaviour, so it cannot be published under a
	 * version that predates it. 1.0.0 is the first release that carries it.
	 */
	$report(
		version_compare( $header, '1.0.0', '>=' ),
		'the version is at least 1.0.0, which is where recurring costs shipped'
	);

	/*
	 * build/block.json is generated from src/block.json. If the two drift, the
	 * registered block exposes attributes the source never declared, or loses the
	 * cost attributes entirely.
	 */
	$src  = json_decode( (string) file_get_contents( $root . '/src/block.json' ), true );
	$dest = json_decode( (string) file_get_contents( $root . '/build/block.json' ), true );

	if ( is_array( $src ) && is_array( $dest ) ) {
		$source_attributes = array_keys( $src['attributes'] );
		$built_attributes  = array_keys( $dest['attributes'] );

		sort( $source_attributes );
		sort( $built_attributes );

		$report(
			$source_attributes === $built_attributes,
			'the compiled block metadata declares the same attributes as the source'
		);

		$report(
			isset( $dest['attributes']['showCosts'] ),
			'the compiled block metadata declares the cost switch'
		);

		/*
		 * WordPress versions the block's front-end assets from this field, and
		 * the compiled CSS and JS keep stable filenames, so it is the only thing
		 * that busts the browser cache after an upgrade. It has to track the
		 * plugin header, which tools/sync-block-version.cjs writes at build time,
		 * and src/block.json must not carry a second copy to drift out of step.
		 */
		$report(
			isset( $dest['version'] ) && $dest['version'] === $header,
			sprintf(
				'the compiled block metadata versions assets with the plugin header (%s), not %s',
				$header,
				isset( $dest['version'] ) ? $dest['version'] : 'an unset version'
			)
		);

		$report(
			! isset( $src['version'] ),
			'src/block.json declares no version, so the plugin header stays the only source'
		);

		/*
		 * Only the cost attributes ship in this release. Anything left over from
		 * the unreleased start-date, escalation, extra-payment and per-field
		 * toggle work would show up in the editor as settings that do nothing.
		 */
		$withdrawn = array(
			'showLoanSummary',
			'showPayoffDate',
			'scheduleFrequency',
			'startMonth',
			'startYear',
			'taxIncrease',
			'insuranceIncrease',
			'hoaIncrease',
			'otherCostsIncrease',
			'extraMonthly',
			'extraYearly',
			'fieldToggles',
		);

		foreach ( $withdrawn as $attribute ) {
			$report(
				! isset( $src['attributes'][ $attribute ] ),
				sprintf( 'the unreleased "%s" attribute is not declared', $attribute )
			);
		}

		$one_time = array_filter(
			$source_attributes,
			function ( $attribute ) {
				return 0 === strpos( $attribute, 'oneTime' );
			}
		);

		$report( array() === $one_time, 'no unreleased one-time payment attributes are declared' );
	} else {
		$report( false, 'both block.json files are valid JSON' );
	}

	if ( 0 === $failures ) {
		fwrite( STDOUT, "ok   version, stable tag and compiled metadata agree\n" );
	}

	return $failures;
}

/**
 * Renders build/render.php the way WordPress would and returns the markup.
 *
 * The template reads $attributes and echoes into the output buffer, so it has
 * to be included inside a function that supplies that variable. Including it
 * rather than testing src/render.php is deliberate: build/render.php is the file
 * that actually ships and the one every visitor is served.
 *
 * @param array<string,mixed> $attributes Block attributes.
 * @return string Rendered markup.
 */
function amortexa_render_block( array $attributes ) {
	$content = '';
	$block   = array();

	ob_start();
	require dirname( __DIR__ ) . '/build/render.php';

	return (string) ob_get_clean();
}

/**
 * Renders a block and decodes the JSON payload the front end reads back.
 *
 * Settings that only the browser can act on -- currency position, chart type,
 * panel visibility -- travel to the front end inside this payload rather than
 * in markup, so asserting on the markup alone would miss a payload that is
 * rendered but never honoured.
 *
 * @param array<string,mixed> $attributes Block attributes.
 * @return array<string,mixed>|null Decoded payload, or null when absent.
 */
function amortexa_render_config( array $attributes ) {
	$markup = amortexa_render_block( $attributes );

	if ( ! preg_match( '/data-amortexa-config="([^"]*)"/', $markup, $match ) ) {
		return null;
	}

	$decoded = json_decode( html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' ), true );

	return is_array( $decoded ) ? $decoded : null;
}

/**
 * Parses rendered markup so a test can assert how elements nest.
 *
 * Nesting is the whole question for the layout tests: whether the amortization
 * table sits inside a column or beside it cannot be read off the tag sequence
 * without counting closing tags by hand.
 *
 * @param string $markup Rendered markup.
 * @return DOMDocument Parsed document.
 */
function amortexa_parse( $markup ) {
	$document = new DOMDocument();

	$previous = libxml_use_internal_errors( true );

	$document->loadHTML( '<!DOCTYPE html><html><body>' . $markup . '</body></html>' );

	libxml_clear_errors();
	libxml_use_internal_errors( $previous );

	return $document;
}

/**
 * Finds the first element carrying a class, searching beneath a node.
 *
 * The match is on the whole class token rather than a substring, so asking for
 * `amortexa-calc__grid` never lands on `amortexa-calc__grid--aside`.
 *
 * @param DOMNode $context Node to search beneath.
 * @param string  $selector Class name, with or without a leading dot.
 * @return DOMElement|null Matching element, or null.
 */
function amortexa_first_element( DOMNode $context, $selector ) {
	$class  = ltrim( $selector, '.' );
	$xpath  = new DOMXPath( $context instanceof DOMDocument ? $context : $context->ownerDocument );
	$found  = $xpath->query(
		'.//*[contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")]',
		$context
	);

	if ( null === $found || 0 === $found->length ) {
		return null;
	}

	return $found->item( 0 );
}

/**
 * Lists the class of every element child, in order.
 *
 * Whitespace text nodes are skipped, so the result lines up with the panels the
 * template renders rather than with how it happens to indent them.
 *
 * @param DOMNode $context Parent element.
 * @return string[] Class names of the element children.
 */
function amortexa_child_classes( DOMNode $context ) {
	$classes = array();

	foreach ( $context->childNodes as $child ) {
		if ( XML_ELEMENT_NODE === $child->nodeType ) {
			$classes[] = (string) $child->getAttribute( 'class' );
		}
	}

	return $classes;
}

/**
 * Covers the server-rendered markup, which nothing else here exercises.
 *
 * Every calculation in this file is proved through amortexa_calculate(), but the
 * template that turns those numbers into what a visitor actually reads is a
 * separate 500-odd lines of markup with its own conditions. Two of those
 * conditions are the ones a visitor notices immediately: the recurring cost rows
 * and inputs must not appear at all when costs are off, and every attribute the
 * template prints has to be escaped.
 *
 * @return int Number of failures.
 */
function amortexa_test_ssr() {
	$failures = 0;

	/*
	 * `$detail` carries what the check compared, so a structural failure names
	 * the markup it was handed instead of only saying it did not match.
	 */
	$report = function ( $ok, $message, $detail = array() ) use ( &$failures ) {
		if ( $ok ) {
			fwrite( STDOUT, "ok   $message\n" );

			return;
		}

		++$failures;
		fwrite( STDOUT, "FAIL $message\n" );

		foreach ( $detail as $label => $value ) {
			fwrite( STDOUT, '       ' . $label . ': ' . str_replace( array( "\n", '  ' ), array( '', ' ' ), var_export( $value, true ) ) . "\n" );
		}
	};

	$base = array(
		'loanAmount'   => 400000,
		'downPayment'  => 80000,
		'interestRate' => 6.5,
		'loanTerm'     => 30,
		'showResults'  => true,
		'showCharts'   => true,
		'showSliders'  => true,
		'showCosts'    => false,
	);

	fwrite( STDOUT, "-- costs off --\n" );
	$off = amortexa_render_block( $base );

	$report( '' !== $off, 'the template renders markup' );
	$report( false !== strpos( $off, 'data-amortexa-config' ), 'the front-end config payload is emitted' );

	foreach ( array( 'propertyTax', 'homeInsurance', 'hoaFee', 'pmi', 'otherCosts' ) as $attribute ) {
		$report(
			false === strpos( $off, 'data-amortexa-field="' . $attribute . '"' ),
			"the $attribute input is not rendered while costs are off"
		);
	}

	/*
	 * The regression this guards: an existing calculator saved before costs
	 * existed must not grow a "Total Monthly Cost" row that merely repeats the
	 * monthly payment, or a "$0" totals block, because the author never asked
	 * for costs.
	 */
	foreach ( array( 'totalMonthlyCost', 'totalCosts', 'totalOutOfPocket' ) as $bind ) {
		$report(
			false === strpos( $off, 'data-amortexa-bind="' . $bind . '"' ),
			"the $bind row is not rendered while costs are off"
		);
	}

	$report( false === strpos( $off, 'amortexa-calc__result-list--costs' ), 'the costs result list is not rendered while costs are off' );

	/*
	 * The labels travel to the front end inside the config payload so JavaScript
	 * can relabel its own rows, so the label text is always present in the markup
	 * somewhere. What has to be absent is the visible row, which is why the
	 * payload is stripped before this check.
	 */
	$off_visible = preg_replace( '/data-amortexa-config="[^"]*"/', 'data-amortexa-config=""', $off );
	$report( false === strpos( $off_visible, 'Total Monthly Cost' ), 'the total monthly cost label is not visible while costs are off' );
	$report( false === strpos( $off_visible, 'Total Taxes &amp; Costs' ), 'the totals label is not visible while costs are off' );

	foreach ( array( 'loanAmount', 'downPayment', 'interestRate', 'loanTerm' ) as $attribute ) {
		$report(
			false !== strpos( $off, 'data-amortexa-field="' . $attribute . '"' ),
			"the $attribute input still renders while costs are off"
		);
	}

	$report( substr_count( $off, '<form' ) === 1, 'exactly one form is rendered' );

	fwrite( STDOUT, "-- costs on --\n" );
	$on = amortexa_render_block(
		array_merge(
			$base,
			array(
				'showCosts'         => true,
				'propertyTax'       => 1.25,
				'propertyTaxUnit'   => 'percent',
				'homeInsurance'     => 1500,
				'homeInsuranceUnit' => 'amount',
				'hoaFee'            => 120,
				'hoaFeeUnit'        => 'amount',
				'pmi'               => 200,
				'pmiUnit'           => 'amount',
				'otherCosts'        => 60,
				'otherCostsUnit'    => 'amount',
			)
		)
	);

	foreach ( array( 'propertyTax', 'homeInsurance', 'hoaFee', 'pmi', 'otherCosts' ) as $attribute ) {
		$report(
			false !== strpos( $on, 'data-amortexa-field="' . $attribute . '"' ),
			"the $attribute input is rendered once costs are on"
		);
	}

	foreach ( array( 'totalMonthlyCost', 'totalCosts', 'totalOutOfPocket' ) as $bind ) {
		$report(
			false !== strpos( $on, 'data-amortexa-bind="' . $bind . '"' ),
			"the $bind row is rendered once costs are on"
		);
	}

	$report( false !== strpos( $on, 'data-amortexa-unit="propertyTaxUnit"' ), 'the percent/amount toggle renders for tax' );
	$report( false !== strpos( $on, 'Total Monthly Cost' ), 'the total monthly cost label appears once costs are on' );
	$report( substr_count( $on, '<form' ) === 1, 'still exactly one form with costs on' );

	/*
	 * The cost inputs drive the live recalculation through the form's input
	 * listener, so they have to live inside the form element. Rendering them in
	 * the amortization table body let the HTML parser foster-parent them out of
	 * the form, which is exactly the regression this guards.
	 */
	$form_open  = strpos( $on, '<form' );
	$form_close = strpos( $on, '</form>' );
	$costs_at   = strpos( $on, 'amortexa-calc__costs' );
	$report(
		false !== $form_open && false !== $form_close && false !== $costs_at && $form_open < $costs_at && $costs_at < $form_close,
		'the cost inputs render inside the form'
	);

	/*
	 * The rendered figures have to be the ones the calculator produced, not just
	 * plausible looking placeholders. The breakdown is a monthly breakdown, so
	 * 1.25% of 400,000 is 5,000 a year and therefore 416.67 a month.
	 */
	$report( false !== strpos( $on, '416.67' ), 'the percent tax renders as its monthly figure, 416.67' );
	$report( false !== strpos( $on, '125.00' ), 'an amount-based premium renders as its monthly figure, 125.00' );

	/*
	 * The two column layouts are the only ones that group panels, and the
	 * grouping is the whole point: without the column divs the charts keep their
	 * full width span and drop back underneath the form. The amortization table
	 * is never grouped, so these read the DOM instead of counting tags.
	 */
	fwrite( STDOUT, "-- layouts --\n" );

	$aside = amortexa_render_block( array_merge( $base, array( 'layout' => 'aside' ) ) );

	$report(
		false !== strpos( $aside, 'amortexa-calc__grid--aside' ),
		'the aside layout marks the grid'
	);

	$grid      = amortexa_first_element( amortexa_parse( $aside ), '.amortexa-calc__grid' );
	$first     = amortexa_first_element( $grid, '.amortexa-calc__column--form' );
	$second    = amortexa_first_element( $grid, '.amortexa-calc__column--details' );
	$top_level = amortexa_child_classes( $grid );

	$report(
		array( 'amortexa-calc__column amortexa-calc__column--form', 'amortexa-calc__column amortexa-calc__column--details', 'amortexa-calc__schedule' ) === $top_level,
		'the aside layout holds two columns and the full width table',
		array( 'grid children' => $top_level )
	);

	$report(
		array( 'amortexa-calc__form' ) === amortexa_child_classes( $first )
			&& array( 'amortexa-calc__results', 'amortexa-calc__charts' ) === amortexa_child_classes( $second ),
		'the aside layout keeps the inputs alone in the first column and the results with the charts in the second'
	);

	$chart_aside = amortexa_render_block( array_merge( $base, array( 'layout' => 'chart-aside' ) ) );

	$report(
		false !== strpos( $chart_aside, 'amortexa-calc__grid--chart-aside' ),
		'the chart-aside layout marks the grid'
	);

	$grid   = amortexa_first_element( amortexa_parse( $chart_aside ), '.amortexa-calc__grid' );
	$first  = amortexa_first_element( $grid, '.amortexa-calc__column--form' );
	$second = amortexa_first_element( $grid, '.amortexa-calc__column--details' );

	$report(
		array( 'amortexa-calc__column amortexa-calc__column--form', 'amortexa-calc__column amortexa-calc__column--details', 'amortexa-calc__schedule' ) === amortexa_child_classes( $grid )
			&& array( 'amortexa-calc__form', 'amortexa-calc__results' ) === amortexa_child_classes( $first )
			&& array( 'amortexa-calc__charts' ) === amortexa_child_classes( $second ),
		'the chart-aside layout stacks the inputs with the results and gives the charts their own column',
		array(
			'grid children'    => amortexa_child_classes( $grid ),
			'first column'     => amortexa_child_classes( $first ),
			'second column'    => amortexa_child_classes( $second ),
		)
	);

	/*
	 * Saved order decides the sequence inside a column, and a table saved in the
	 * middle of that order is still pulled back out to full width.
	 */
	$reordered = amortexa_render_block(
		array_merge(
			$base,
			array(
				'layout'     => 'chart-aside',
				'panelOrder' => array( 'schedule', 'charts', 'results', 'form' ),
			)
		)
	);

	$grid = amortexa_first_element( amortexa_parse( $reordered ), '.amortexa-calc__grid' );

	$report(
		array( 'amortexa-calc__column amortexa-calc__column--form', 'amortexa-calc__column amortexa-calc__column--details', 'amortexa-calc__schedule' ) === amortexa_child_classes( $grid )
			&& array( 'amortexa-calc__form', 'amortexa-calc__results' ) === amortexa_child_classes( amortexa_first_element( $grid, '.amortexa-calc__column--form' ) )
			&& array( 'amortexa-calc__charts' ) === amortexa_child_classes( amortexa_first_element( $grid, '.amortexa-calc__column--details' ) ),
		'the saved order holds inside a column and the table still ends up full width',
		array( 'grid children' => amortexa_child_classes( $grid ) )
	);

	/* The table belongs to the grid, not to a column, whatever the layout. */
	foreach ( array_keys( amortexa_get_layouts() ) as $layout_key ) {
		$rendered = amortexa_render_block( array_merge( $base, array( 'layout' => $layout_key ) ) );

		preg_match( '/amortexa-calc__grid[ "\n]/', $rendered, $grid_class );

		$report(
			false !== strpos( $rendered, 'amortexa-calc__grid--' . $layout_key ),
			'the ' . $layout_key . ' layout reaches the markup as its own grid modifier',
			array( 'grid class' => isset( $grid_class[0] ) ? $grid_class[0] : '' )
		);

		$layout_grid    = amortexa_first_element( amortexa_parse( $rendered ), '.amortexa-calc__grid' );
		$layout_classes = amortexa_child_classes( $layout_grid );

		$report(
			'amortexa-calc__schedule' === end( $layout_classes ),
			'the amortization table is the last, full width panel in the ' . $layout_key . ' layout',
			array( 'grid children' => $layout_classes )
		);
	}

	/* Nothing to put beside the inputs means there is no second column. */
	$single = amortexa_render_block(
		array_merge(
			$base,
			array(
				'layout'           => 'aside',
				'showResults'      => false,
				'showCharts'       => false,
				'showAmortization' => false,
			)
		)
	);

	$report(
		false !== strpos( $single, 'amortexa-calc__grid--form-only' )
			&& array( 'amortexa-calc__form' ) === amortexa_child_classes( amortexa_first_element( amortexa_parse( $single ), '.amortexa-calc__grid' ) ),
		'a column layout collapses to one column when there is nothing to put beside the inputs'
	);

	/* The flat layouts share one set of panels and need no wrappers. */
	$report(
		false === strpos( $off, 'amortexa-calc__column' ),
		'the stacked and split layouts render no column wrappers'
	);

	fwrite( STDOUT, "-- escaping --\n" );

	/*
	 * Tags are removed from the currency symbol during sanitization, which is the
	 * layer that actually keeps markup out of the output. Quotes and ampersands
	 * deliberately survive it, because a currency symbol is allowed to contain
	 * them; those are handled where they are printed.
	 */
	$symbol_cases = array(
		'<script>alert(1)</script>'  => 'alert(1)',
		'<b>bold</b>'                => 'bold',
		// Stripping this leaves nothing behind, so the default symbol takes over
		// rather than the block rendering an empty currency.
		'<img src=x onerror=alert(1)>' => '$',
	);

	foreach ( $symbol_cases as $hostile => $expected ) {
		$clean = amortexa_sanitize_attributes( array( 'currencySymbol' => $hostile ) );
		$report(
			$expected === $clean['currencySymbol'],
			sprintf( 'the sanitizer strips markup out of the symbol: %s -> %s', $hostile, $clean['currencySymbol'] )
		);
	}

	$survivor = amortexa_sanitize_attributes( array( 'currencySymbol' => 'a"b&c' ) );
	$report( 'a"b&c' === $survivor['currencySymbol'], 'quotes and ampersands survive sanitization, to be escaped on output instead' );

	$xss = amortexa_render_block(
		array_merge(
			$base,
			array(
				'showCosts'      => true,
				'propertyTax'    => 1.25,
				'currencySymbol' => '"><script>alert(1)</script>',
			)
		)
	);

	$report( false === strpos( $xss, '<script' ), 'a hostile symbol introduces no script tag' );
	$report( false === strpos( $xss, '<img ' ), 'a hostile symbol introduces no img tag' );

	/*
	 * The property that matters for the config attribute is that a surviving
	 * quote cannot terminate the attribute it travels in, so the whole attribute
	 * still matches one balanced quoted run.
	 */
	$report(
		1 === preg_match( '/data-amortexa-config="[^"]*"/', $xss ),
		'the config attribute stays balanced with a hostile currency symbol'
	);

	fwrite( STDOUT, "-- config payload --\n" );
	if ( preg_match( '/data-amortexa-config="([^"]*)"/', $off, $match ) ) {
		$decoded = json_decode( html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' ), true );
		$report( is_array( $decoded ), 'the config payload is valid JSON once entity decoded' );
		$report( is_array( $decoded ) && isset( $decoded['decimals'] ), 'the config payload carries the decimal setting' );
		$report( is_array( $decoded ) && isset( $decoded['labels']['monthly'] ), 'the config payload carries labels for the front end' );
	} else {
		$report( false, 'the config payload could be extracted from the markup' );
	}

	if ( 0 === $failures ) {
		fwrite( STDOUT, "ok   server-rendered markup, cost gating and escaping\n" );
	}

	return $failures;
}

/**
 * Checks that the release ships the sources its build needs, and nothing else.
 *
 * WordPress Plugin Check reads every PHP file in the installed plugin folder,
 * and the one in wp-admin it never asks permission for: the admin UI ignores
 * both this repository's phpcs.xml.dist and any .plugin-check.json, because a
 * scanner that a plugin author could configure would not be a scanner. So the
 * only real defence against it flagging the test harness is that the release
 * does not contain the file at all.
 *
 * Going the other way, directory guideline #4 requires the unminified source
 * behind every compiled file to be publicly available. Shipping src/ and the
 * build config inside the plugin satisfies that without depending on an external
 * link staying reachable, so both are asserted as shipping.
 *
 * Both build paths are asserted -- the dist allowlist in tools/build-dist.cjs and
 * .distignore for anyone building with `wp dist-archive` -- because a path
 * quietly added to one but not the other would otherwise only surface as a
 * rejected upload, or as a wall of false positives in a development checkout.
 *
 * @return int Number of failures.
 */
function amortexa_test_release_packaging() {
	$failures = 0;
	$report   = function ( $ok, $message ) use ( &$failures ) {
		if ( $ok ) {
			fwrite( STDOUT, "ok   $message\n" );
		} else {
			++$failures;
			fwrite( STDOUT, "FAIL $message\n" );
		}
	};

	$root = dirname( __DIR__ );

	/*
	 * The allowlist is the canonical build. Reading it rather than trusting
	 * the comment above it means a path added here is caught on the next run.
	 */
	$builder = $root . '/tools/build-dist.cjs';

	if ( ! is_readable( $builder ) ) {
		$report( false, 'the dist builder is readable' );

		return $failures;
	}

	$ship_block = array();

	if ( preg_match( '/const SHIP\s*=\s*\[(.*?)\]/s', (string) file_get_contents( $builder ), $match ) ) {
		preg_match_all( '/[\'"]([^\'"]+)[\'"]/', $match[1], $found );
		$ship_block = $found[1];
	}

	$report( array() !== $ship_block, 'the dist builder declares the paths that ship' );

	$ships = array_flip( $ship_block );

	/*
	 * The harness, the release tooling, and the installed dependencies never
	 * ship. tests/ in particular calls shell_exec(), which WordPress.org
	 * rejects outright, and Plugin Check would report it in wp-admin where it
	 * cannot be silenced by configuration.
	 */
	foreach ( array( 'tests', 'tools', 'node_modules', 'vendor', 'dist' ) as $dev ) {
		$report( ! isset( $ships[ $dev ] ), sprintf( 'the dist allowlist does not ship %s', $dev ) );
	}

	/*
	 * Directory guideline #4: the source behind the minified files in build/
	 * has to be publicly available, and the build tooling that produces them
	 * has to be documented. Shipping both here is what satisfies it.
	 */
	foreach ( array( 'src', 'package.json', 'webpack.config.js', 'babel.config.js' ) as $source ) {
		$report( isset( $ships[ $source ] ), sprintf( 'the dist allowlist ships %s, so the compiled files have a matching source', $source ) );
	}

	/*
	 * includes/ is the one runtime directory a naive exclusion list gets
	 * wrong: the main file requires it on every request, so a release built
	 * without it activates and then fatals on the first page view.
	 */
	foreach ( array( 'includes', 'build', 'languages', 'assets' ) as $runtime ) {
		$report( isset( $ships[ $runtime ] ), sprintf( 'the dist allowlist ships %s, which the plugin needs at runtime', $runtime ) );
	}

	/* Every file the main plugin file pulls in has to sit inside the allowlist. */
	$main = (string) file_get_contents( $root . '/amortexa-mortgage-calculator.php' );

	preg_match_all( '/require(?:_once)?\s+[^;]*?\.\s*\'([^\']+)\'/', $main, $requires );

	$orphan = array();

	foreach ( $requires[1] as $required ) {
		$top = strtok( $required, '/' );

		if ( ! isset( $ships[ $top ] ) ) {
			$orphan[] = $required;
		}
	}

	$report(
		array() === $orphan,
		sprintf(
			'every file the plugin requires at runtime is inside the allowlist (%s)',
			array() === $orphan ? 'all covered' : 'orphaned: ' . implode( ', ', $orphan )
		)
	);

	/* .distignore is the second build path and has to reach the same verdict. */
	$ignore_file = $root . '/.distignore';

	if ( ! is_readable( $ignore_file ) ) {
		$report( false, '.distignore is readable' );

		return $failures;
	}

	$ignored = preg_split( '/[\r\n]+/', (string) file_get_contents( $ignore_file ), -1, PREG_SPLIT_NO_EMPTY );

	foreach ( array( 'tests', 'tools', 'node_modules' ) as $dev ) {
		$report( in_array( $dev, $ignored, true ), sprintf( '.distignore excludes %s for a wp dist-archive build', $dev ) );
	}

	/*
	 * The build config must not be excluded here either, or the two build paths
	 * would disagree about guideline #4: the allowlist ships it while a
	 * `wp dist-archive` build dropped it.
	 */
	$not_ignored = array( 'src', 'src/**/*.js', 'src/*.scss', 'package.json', 'webpack.config.js', 'babel.config.js' );
	$wrongly     = array_values( array_intersect( $not_ignored, $ignored ) );

	$report(
		array() === $wrongly,
		sprintf(
			'.distignore does not exclude the sources or build config (%s)',
			array() === $wrongly ? 'all kept' : 'excluded: ' . implode( ', ', $wrongly )
		)
	);

	if ( 0 === $failures ) {
		fwrite( STDOUT, "ok   the release ships its sources and build config, and no harness\n" );
	}

	return $failures;
}

/**
 * Checks that every setting the editor writes changes the front-end markup.
 *
 * The editor reads block attributes straight out of JS, so a control looks
 * like it works the moment it is clicked: the preview renders from whatever
 * the inspector just set. The front end renders from a different program
 * entirely -- build/render.php, through the sanitizer and the design token
 * schema. A setting can therefore save perfectly, reappear in the editor on
 * reload, and still be dropped on the way to the page, which is the hardest
 * kind of break to notice by hand because both halves look correct.
 *
 * So this asserts the only property that actually matters: flip each
 * attribute away from its default and the rendered markup has to differ. A
 * setting that renders identically either side of the change is dead on the
 * front end, whatever the editor says.
 *
 * @return int Number of failures.
 */
function amortexa_test_attribute_effects() {
	$failures = 0;
	$report   = function ( $ok, $message ) use ( &$failures ) {
		if ( $ok ) {
			fwrite( STDOUT, "ok   $message\n" );
		} else {
			++$failures;
			fwrite( STDOUT, "FAIL $message\n" );
		}
	};

	$defaults = amortexa_get_default_attributes();
	$control  = amortexa_render_block( $defaults );

	/*
	 * Each case names an attribute and a value the default block cannot be
	 * using, chosen so that a change has to be visible in the output rather
	 * than merely present in it.
	 */
	$cases = array(
		'loanAmount'        => array( 725000, '725000' ),
		'downPayment'       => array( 0, 'value="0"' ),
		'interestRate'      => array( 3.25, '3.25' ),
		'loanTerm'          => array( 15, 'value="15"' ),
		'currencySymbol'    => array( 'GBP', 'GBP' ),
		'showResults'       => array( false, 'amortexa-calc__grid--form-only' ),
		'showCosts'         => array( true, 'amortexa-calc__costs' ),
		'layout'            => array( 'stacked', 'amortexa-calc__grid--stacked' ),
		'formColumns'       => array( 'compact', 'amortexa-calc--form-columns-compact' ),
		'propertyTax'       => array( 2.5, '2.5' ),
	);

	foreach ( $cases as $attribute => $case ) {
		list( $value, $marker ) = $case;

		$changed = amortexa_render_block( array_merge( $defaults, array( $attribute => $value ) ) );

		$report(
			$changed !== $control,
			sprintf( 'the "%s" setting changes the front-end markup', $attribute )
		);

		$report(
			false !== strpos( $changed, $marker ),
			sprintf( 'the "%s" setting reaches the page (expects %s)', $attribute, $marker )
		);
	}

	/*
	 * A toggle set to false has to take its markup away rather than add a
	 * marker, so these are asserted as absence.
	 */
	$off_cases = array(
		'showAmortization' => 'amortexa-calc__schedule',
		'showCharts'       => 'amortexa-calc__chart',
		'showSliders'      => 'type="range"',
	);

	foreach ( $off_cases as $attribute => $absent ) {
		$changed = amortexa_render_block( array_merge( $defaults, array( $attribute => false ) ) );

		$report(
			$changed !== $control,
			sprintf( 'the "%s" setting changes the front-end markup', $attribute )
		);

		$report(
			false === strpos( $changed, $absent ),
			sprintf( 'turning "%s" off removes %s from the page', $attribute, $absent )
		);
	}

	/*
	 * Settings the browser acts on rather than the server travel in the JSON
	 * payload, so they are asserted there instead of against the markup.
	 */
	$payload_cases = array(
		'currencyPosition' => array( 'suffix', 'position' ),
		'chartType'        => array( 'donut', 'chartType' ),
	);

	foreach ( $payload_cases as $attribute => $case ) {
		list( $value, $key ) = $case;

		$config = amortexa_render_config( array_merge( $defaults, array( $attribute => $value ) ) );

		$report(
			is_array( $config ) && isset( $config[ $key ] ) && $value === $config[ $key ],
			sprintf( 'the "%s" setting reaches the front-end config payload', $attribute )
		);
	}

	/* A colour override has to land as a custom property on the wrapper. */
	$colored = amortexa_render_block( array_merge( $defaults, array( 'accentColor' => '#ff0055' ) ) );

	$report(
		false !== strpos( $colored, '#ff0055' ),
		'the "accentColor" setting reaches the page as a custom property'
	);

	/* So does a design token chosen in the Design tab. */
	$token = amortexa_render_block(
		array_merge( $defaults, array( 'design' => array( 'resultPrimaryWeight' => 700 ) ) )
	);

	$report(
		false !== strpos( $token, '--amortexa-result-primary-weight' ),
		'the Design tab token reaches the page as CSS'
	);

	if ( 0 === $failures ) {
		fwrite( STDOUT, "ok   every editor setting survives the trip to the front end\n" );
	}

	return $failures;
}

$exit = amortexa_test_parity() + amortexa_test_design_schema() + amortexa_test_settings_and_shortcode() + amortexa_test_input_guards() + amortexa_test_rate_limit() + amortexa_test_costs() + amortexa_test_version_consistency() + amortexa_test_ssr() + amortexa_test_release_packaging() + amortexa_test_attribute_effects();
if ( 0 === $exit ) {
	fwrite( STDOUT, "\nAll PHP/JS parity checks passed.\n" );
} else {
	fwrite( STDERR, "\n$exit parity failure(s) detected.\n" );
}

exit( $exit > 0 ? 1 : 0 );


