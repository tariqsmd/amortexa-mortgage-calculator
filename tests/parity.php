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
 * @package CalcForge
 */

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

// The plugin's helpers guard on ABSPATH; satisfy it without booting WP.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

// The plugin's own text domain constant, normally set by calcforge.php.
if ( ! defined( 'CALCFORGE_TEXT_DOMAIN' ) ) {
	define( 'CALCFORGE_TEXT_DOMAIN', 'calcforge' );
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
	$GLOBALS['calcforge_test_filters'] = array();

	/**
	 * Emulates WP add_filter() for single callback slots.
	 *
	 * @param string   $hook_name Filter name.
	 * @param callable $callback  Callback to run.
	 * @return bool Always true.
	 */
	function add_filter( $hook_name, $callback ) {
		$GLOBALS['calcforge_test_filters'][ $hook_name ] = $callback;
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
		unset( $GLOBALS['calcforge_test_filters'][ $hook_name ] );
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
		if ( ! isset( $GLOBALS['calcforge_test_filters'][ $hook_name ] ) ) {
			return $value;
		}

		return call_user_func_array(
			$GLOBALS['calcforge_test_filters'][ $hook_name ],
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
$GLOBALS['calcforge_test_transients'] = array();

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * Emulates WP get_transient() against the in-memory store.
	 *
	 * @param string $key Transient key.
	 * @return mixed Stored value, or false when absent or expired.
	 */
	function get_transient( $key ) {
		if ( ! isset( $GLOBALS['calcforge_test_transients'][ $key ] ) ) {
			return false;
		}

		$entry = $GLOBALS['calcforge_test_transients'][ $key ];

		if ( $entry['expires'] > 0 && $entry['expires'] <= time() ) {
			unset( $GLOBALS['calcforge_test_transients'][ $key ] );
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
		$GLOBALS['calcforge_test_transients'][ $key ] = array(
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
function calcforge_test_parity() {
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

		$php_monthly[] = calcforge_calculate_monthly_payment( $principal, $rate, $years );
		$php_schedules[] = calcforge_calculate_amortization_schedule( $principal, $rate, $years );
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
function calcforge_test_design_sample( $token ) {
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
			$lists = calcforge_get_design_option_lists();
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
 *    shipped when the editor wrote --calcforge-accent-alt and --calcforge-label
 *    while the stylesheet only knew --calcforge-accent-2 and
 *    --calcforge-label-color.
 *
 * @return int Number of failures encountered.
 */
function calcforge_test_design_schema() {
	$failures = 0;
	$root     = dirname( __DIR__ );

	$editor_data = calcforge_get_editor_data();

	/*
	 * The schema goes to the JS driver through a file, because a shell_exec() on
	 * Windows runs through cmd.exe which cannot set an inline variable, and the
	 * payload is larger than the command line limit.
	 */
	$payload = tempnam( sys_get_temp_dir(), 'calcforge-design-' );
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

			$sample = calcforge_test_design_sample( $php_token );
			$php_css = calcforge_design_css_value( $php_token, $sample );
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
	$attributes = calcforge_sanitize_attributes(
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

	foreach ( calcforge_get_design_css( $attributes ) as $declaration ) {
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
	$resolved = calcforge_get_design_values( $attributes );

	if ( '#be123c' !== ( $resolved['labelColor'] ?? '' ) ) {
		$failures++;
		fwrite( STDOUT, "FAIL legacy colour attribute not honoured: " . wp_json_encode_compat( $resolved ) . "\n" );
	} else {
		fwrite( STDOUT, "ok   legacy colour attributes still resolve\n" );
	}

	$overridden = calcforge_sanitize_attributes(
		array(
			'design'     => array( 'labelColor' => '#0e7490' ),
			'labelColor' => '#be123c',
		)
	);

	$resolved_override = calcforge_get_design_values( $overridden );

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
	$after_reset = calcforge_get_design_values(
		calcforge_sanitize_attributes( array( 'labelColor' => '#be123c' ) )
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
		$php_height = calcforge_get_design_chart_metrics(
			calcforge_sanitize_attributes( $chart_case )
		)['height'];

		$js_height = $js['chartHeights'][ $index ] ?? null;

		if ( (int) $php_height !== (int) $js_height ) {
			$failures++;
			fwrite( STDOUT, sprintf( "FAIL chart height case %d: PHP=%s JS=%s\n", $index, $php_height, var_export( $js_height, true ) ) );
		}
	}

	fwrite( STDOUT, sprintf( "ok   chart height: %d cases agree between the render and the preview\n", count( $chart_cases ) ) );

	$failures += calcforge_test_design_wiring( $root );

	return $failures;
}

/**
 * Checks that the stylesheet and the token schema agree in both directions.
 *
 * @param string $root Repository root.
 * @return int Number of failures.
 */
function calcforge_test_design_wiring( $root ) {
	$failures = 0;
	$scss     = (string) file_get_contents( $root . '/src/style.scss' );

	// Properties the stylesheet reads.
	preg_match_all( '/var\(\s*(--calcforge-[a-z0-9-]+)/', $scss, $consumed );
	$consumed = array_values( array_unique( $consumed[1] ) );

	// Properties the stylesheet gives a default to.
	preg_match_all( '/(--calcforge-[a-z0-9-]+)\s*:/', $scss, $declared );
	$declared = array_values( array_unique( $declared[1] ) );

	/*
	 * Palette variables are set by the 24 skins and are not inspector tokens, so
	 * they are legitimately consumed without appearing in the schema.
	 */
	$palette = array(
		'--calcforge-accent',
		'--calcforge-accent-2',
		'--calcforge-accent-soft',
		'--calcforge-bg',
		'--calcforge-border',
		'--calcforge-field-bg',
		'--calcforge-field-border',
		'--calcforge-field-text',
		'--calcforge-label-color',
		'--calcforge-surface',
		'--calcforge-text',
		'--calcforge-text-muted',
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

	foreach ( calcforge_get_design_token_map() as $key => $token ) {
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
			count( calcforge_get_design_token_map() ),
			count( $consumed )
		)
	);

	$failures += calcforge_test_design_defaults( $scss, $declared );

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
function calcforge_test_design_defaults( $scss, $declared ) {
	$failures = 0;

	preg_match_all( '/(--calcforge-[a-z0-9-]+)\s*:\s*([^;]+);/', $scss, $matches, PREG_SET_ORDER );

	$defaults = array();

	foreach ( $matches as $hit ) {
		$var = $hit[1];

		// The first declaration is the base default; media queries redefine it.
		if ( ! isset( $defaults[ $var ] ) ) {
			$defaults[ $var ] = trim( $hit[2] );
		}
	}

	foreach ( calcforge_get_design_token_map() as $key => $token ) {
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
function calcforge_test_settings_and_shortcode() {
	$failures = 0;
	$valid    = calcforge_get_currency_positions();

	// Every position the site offers must survive sanitization.
	foreach ( array_keys( $valid ) as $position ) {
		$clean = calcforge_sanitize_settings( array( 'currency_position' => $position ) );

		if ( $position !== $clean['currency_position'] ) {
			++$failures;
			fwrite( STDOUT, sprintf( "FAIL currency_position '%s' was rewritten to '%s'\n", $position, $clean['currency_position'] ) );
		}
	}

	// An unknown or missing value falls back to the default, never to raw input.
	foreach ( array( 'nonsense', '', array( 'prefix' ) ) as $bad ) {
		$clean = calcforge_sanitize_settings( array( 'currency_position' => $bad ) );

		if ( 'prefix' !== $clean['currency_position'] ) {
			++$failures;
			fwrite( STDOUT, sprintf( "FAIL currency_position rejected '%s' but produced '%s'\n", wp_json_encode_compat( $bad ), $clean['currency_position'] ) );
		}
	}

	// The setting has to be reachable from the settings screen and seed blocks.
	$map = calcforge_get_settings_attribute_map();

	if ( 'currencyPosition' !== ( $map['currency_position'] ?? null ) ) {
		++$failures;
		fwrite( STDOUT, "FAIL currency_position is not mapped onto the currencyPosition block attribute\n" );
	}

	$defaults = calcforge_get_default_attributes();

	if ( 'prefix' !== $defaults['currencyPosition'] ) {
		++$failures;
		fwrite( STDOUT, sprintf( "FAIL default currencyPosition is '%s', expected the site setting\n", $defaults['currencyPosition'] ) );
	}

	fwrite( STDOUT, "ok   global currency position setting round-trips and seeds new blocks\n" );

	require_once dirname( __DIR__ ) . '/includes/class-shortcode.php';

	$shortcode = new CalcForge_Shortcode();
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
	$resolved = calcforge_resolve_panel_order( array( 'form', 'not-a-panel', 'results' ), calcforge_get_panel_keys() );

	if ( in_array( 'not-a-panel', $resolved, true ) ) {
		++$failures;
		fwrite( STDOUT, "FAIL an unknown panel key survived panelOrder resolution\n" );
	}

	// formcolumns is a straight enum, so an unknown value falls back to wide.
	$columns = calcforge_sanitize_attributes( array( 'formColumns' => 'nonsense' ) );

	if ( 'wide' !== $columns['formColumns'] ) {
		++$failures;
		fwrite( STDOUT, sprintf( "FAIL formColumns rejected an unknown value but produced '%s'\n", $columns['formColumns'] ) );
	}

	fwrite( STDOUT, "ok   shortcode exposes form layout and panel order with safe list parsing\n" );

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
function calcforge_test_rate_limit() {
	$failures = 0;
	$report   = function ( $ok, $message ) use ( &$failures ) {
		if ( $ok ) {
			fwrite( STDOUT, "ok   $message\n" );
		} else {
			++$failures;
			fwrite( STDOUT, "FAIL $message\n" );
		}
	};

	$GLOBALS['calcforge_test_transients'] = array();

	// The default budget must permit exactly N requests, then refuse.
	$limit  = 5;
	$window = 60;
	$bucket = 'visitor-a';
	$key    = 'calcforge_rl_' . md5( $bucket );

	for ( $i = 1; $i <= $limit; $i++ ) {
		$outcome = calcforge_rate_limit_hit( $bucket, $limit, $window );
		$report(
			! $outcome['exceeded'] && $outcome['count'] === $i,
			"request $i of $limit is allowed"
		);
	}

	$blocked = calcforge_rate_limit_hit( $bucket, $limit, $window );
	$report( $blocked['exceeded'], 'the request past the budget is refused' );
	$report( $blocked['retry_after'] > 0 && $blocked['retry_after'] <= $window, 'a refusal reports a usable Retry-After' );

	// Still refused on the following attempt, and it must not reset the window.
	$again = calcforge_rate_limit_hit( $bucket, $limit, $window );
	$report( $again['exceeded'] && $again['count'] === $limit + 2, 'continued traffic stays refused' );

	// A different caller must have its own budget.
	$other = calcforge_rate_limit_hit( 'visitor-b', $limit, $window );
	$report( ! $other['exceeded'] && $other['count'] === 1, 'a second visitor has an independent budget' );

	// The window is fixed, so it must not be pushed forward by continued hits.
	$before = get_transient( $key );
	calcforge_rate_limit_hit( $bucket, $limit, $window );
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
		isset( $GLOBALS['calcforge_test_transients'][ $key ]['expires'] )
			&& $GLOBALS['calcforge_test_transients'][ $key ]['expires'] > time(),
		'a live window always carries a finite expiry'
	);

	// Age the window out, then confirm the caller is served again.
	$GLOBALS['calcforge_test_transients'][ $key ]['expires'] = time() - 1;
	$reset = calcforge_rate_limit_hit( $bucket, $limit, $window );
	$report( ! $reset['exceeded'] && $reset['count'] === 1, 'the budget resets after the window lapses' );

	// A non-positive limit disables limiting outright.
	$off = calcforge_rate_limit_hit( 'visitor-c', 0, $window );
	$report( ! $off['exceeded'] && 0 === $off['limit'], 'a limit below one disables throttling' );

	// Client key: REMOTE_ADDR by default, and never the spoofable forwarded header.
	unset( $_SERVER['REMOTE_ADDR'] );
	$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.9';
	$spoofed = calcforge_rate_limit_client_key();
	$report( 0 === strpos( $spoofed, 'unknown:' ), 'forwarded headers cannot be used as a bucket key' );

	$_SERVER['REMOTE_ADDR'] = '198.51.100.7';
	$report( '198.51.100.7' === calcforge_rate_limit_client_key(), 'the remote address is used when present' );

	// An over-long key must not be collapsed into a shared bucket.
	add_filter(
		'calcforge_rate_limit_client_key',
		function () {
			return str_repeat( 'x', 46 );
		}
	);
	$long = calcforge_rate_limit_client_key();
	$report( 0 === strpos( $long, 'unknown:' ), 'an unusable client key falls back to a per-request bucket' );
	$report( $long !== calcforge_rate_limit_client_key(), 'each fallback bucket is unique' );
	remove_all_filters( 'calcforge_rate_limit_client_key' );

	unset( $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR'] );
	$GLOBALS['calcforge_test_transients'] = array();

	/*
	 * Enforcement has to be checked by calling the permission callback, not by
	 * reading the source. The REST server only treats WP_Error, false and null as
	 * a refusal; a WP_REST_Response return is truthy and the request is let
	 * through, so a limiter that looks right can silently never apply.
	 */
	$rest = new CalcForge_REST();
	$_SERVER['REMOTE_ADDR'] = '198.51.100.20';

	// Use a small budget so the refusal is reachable without 30 round trips.
	add_filter(
		'calcforge_rest_calculate_rate_limit',
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
	$report( 0 === calcforge_rate_limit_peek( '198.51.100.20' )['count'], 'the permission callback records no hits of its own' );

	// Serve requests the way the handler does, recording one hit each.
	for ( $i = 0; $i < 3; $i++ ) {
		calcforge_rate_limit_hit( '198.51.100.20', 3, 60 );
	}
	$report( 3 === calcforge_rate_limit_peek( '198.51.100.20' )['count'], 'a served request costs exactly one unit' );

	$refusal = $rest->check_permissions( null );
	$report( $refusal instanceof WP_Error, 'an exhausted budget is refused with a WP_Error' );
	$report(
		$refusal instanceof WP_Error && 'calcforge_rest_rate_limited' === $refusal->get_error_code(),
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
	$GLOBALS['calcforge_test_transients'] = array();
	for ( $i = 0; $i < 2; $i++ ) {
		calcforge_rate_limit_hit( '198.51.100.20', 3, 60 );
		$report( true === $rest->check_permissions( null ), 'the final request inside the budget is still served' );
	}
	calcforge_rate_limit_hit( '198.51.100.20', 3, 60 );
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
		'calcforge_rest_calculate_allowed',
		function () {
			return false;
		}
	);
	$denied = $rest->check_permissions( null );
	$report(
		$denied instanceof WP_Error && 'calcforge_rest_forbidden' === $denied->get_error_code(),
		'the authentication filter refuses ahead of the limiter'
	);
	remove_all_filters( 'calcforge_rest_calculate_allowed' );

	// Once the window lapses the caller is served again.
	$GLOBALS['calcforge_test_transients'][ 'calcforge_rl_' . md5( '198.51.100.20' ) ]['expires'] = time() - 1;
	$report( true === $rest->check_permissions( null ), 'the caller is served again once the window lapses' );

	// Lifting the budget serves the same caller without waiting.
	calcforge_rate_limit_hit( '198.51.100.20', 3, 60 );
	calcforge_rate_limit_hit( '198.51.100.20', 3, 60 );
	calcforge_rate_limit_hit( '198.51.100.20', 3, 60 );
	calcforge_rate_limit_hit( '198.51.100.20', 3, 60 );
	$report( $rest->check_permissions( null ) instanceof WP_Error, 'the budget is exhausted again' );
	remove_all_filters( 'calcforge_rest_calculate_rate_limit' );
	add_filter(
		'calcforge_rest_calculate_rate_limit',
		function () {
			return 0;
		}
	);
	$report( true === $rest->check_permissions( null ), 'a limit of zero disables throttling' );
	remove_all_filters( 'calcforge_rest_calculate_rate_limit' );

	unset( $_SERVER['REMOTE_ADDR'] );
	$GLOBALS['calcforge_test_transients'] = array();

	if ( 0 === $failures ) {
		fwrite( STDOUT, "ok   the REST endpoint enforces a per-client request budget\n" );
	}

	return $failures;
}

$exit = calcforge_test_parity() + calcforge_test_design_schema() + calcforge_test_settings_and_shortcode() + calcforge_test_rate_limit();
if ( 0 === $exit ) {
	fwrite( STDOUT, "\nAll PHP/JS parity checks passed.\n" );
} else {
	fwrite( STDERR, "\n$exit parity failure(s) detected.\n" );
}

exit( $exit > 0 ? 1 : 0 );
