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

require_once dirname( __DIR__ ) . '/includes/helpers.php';

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

$exit = calcforge_test_parity();
if ( 0 === $exit ) {
	fwrite( STDOUT, "\nAll PHP/JS parity checks passed.\n" );
} else {
	fwrite( STDERR, "\n$exit parity failure(s) detected.\n" );
}

exit( $exit > 0 ? 1 : 0 );
