<?php
/**
 * REST API endpoint for server-side mortgage calculations.
 *
 * @package MortgageCalculatorBlock
 */

// Abort if this file is called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the mtgb/v1/calculate route.
 *
 * The endpoint mirrors the PHP calculation used by render.php, enabling
 * headless clients and third-party integrations to reuse the same logic.
 */
class mtgb_REST_API {

	/**
	 * REST namespace for all plugin routes.
	 */
	const NAMESPACE_V1 = 'mtgb/v1';

	/**
	 * Registers the hooks this component responds to.
	 */
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers the calculation route.
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/calculate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_calculate' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => array(
					'amount'        => array(
						'type'              => 'number',
						'minimum'           => 0,
						'maximum'           => 999999999999,
						'required'          => true,
						'sanitize_callback' => 'mtgb_clamp_float',
					),
					'down_payment'  => array(
						'type'              => 'number',
						'minimum'           => 0,
						'maximum'           => 999999999999,
						'required'          => false,
						'default'           => 0,
						'sanitize_callback' => 'mtgb_clamp_float',
					),
					'interest_rate' => array(
						'type'              => 'number',
						'minimum'           => 0,
						'maximum'           => 100,
						'required'          => false,
						'default'           => 0,
						'sanitize_callback' => 'mtgb_clamp_float',
					),
					'term_years'    => array(
						'type'              => 'integer',
						'minimum'           => 1,
						'maximum'           => 60,
						'required'          => false,
						'default'           => 30,
						'sanitize_callback' => 'absint',
					),
					'with_schedule' => array(
						'type'    => 'boolean',
						'required' => false,
						'default' => false,
					),
				),
			)
		);
	}

	/**
	 * Permission callback for the calculation endpoint.
	 *
	 * The endpoint is intentionally open: it is a stateless, read-only helper
	 * that exposes only public mortgage arithmetic and returns no private data,
	 * so it does not require authentication, nonces, or the REST nonce. It is
	 * bounded by Web-accessible rate limiting at the server/proxy layer when
	 * installs require it.
	 *
	 * The callback is kept for transparency and as an override point via the
	 * `mtgb/v1/calculate` route registration; a nonce check is no longer applied
	 * because it provided no real protection (any unauthenticated visitor can
	 * already compute the same result with a calculator).
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Current request.
	 * @return true Always allowed.
	 */
	public function check_permissions( $request ) {
		/**
		 * Filters whether the calculation endpoint requires authentication.
		 *
		 * Returning a WP_Error or false here blocks unauthenticated access.
		 *
		 * @param bool                    $allowed Whether the request is allowed.
		 * @param WP_REST_Request<string> $request Current request.
		 */
		$allowed = apply_filters( 'mtgb_rest_calculate_allowed', true, $request );

		if ( true === $allowed ) {
			return true;
		}

		return rest_ensure_response( new WP_Error(
			'mtgb_rest_forbidden',
			esc_html__( 'Calculation requests are not permitted.', mtgb_TEXT_DOMAIN ),
			array( 'status' => rest_authorization_required_code() )
		) );
	}

	/**
	 * Handles a calculation request.
	 *
	 * Reuses mtgb_calculate() so the `mtgb_calculation_result` filter applies to
	 * REST responses exactly as it does to rendered output.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Current request.
	 * @return WP_REST_Response Calculation result.
	 */
	public function handle_calculate( $request ) {
		$attributes = array(
			'loanAmount'       => (float) $request['amount'],
			'downPayment'      => (float) $request['down_payment'],
			'interestRate'     => (float) $request['interest_rate'],
			'loanTerm'         => absint( $request['term_years'] ),
			'showAmortization' => ! empty( $request['with_schedule'] ),
		);

		return rest_ensure_response( mtgb_calculate( $attributes ) );
	}
}
