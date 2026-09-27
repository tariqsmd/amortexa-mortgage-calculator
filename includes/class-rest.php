<?php
/**
 * REST API endpoint for server-side mortgage calculations.
 *
 * @package CalcForge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the calcforge/v1/calculate route.
 *
 * The endpoint mirrors the PHP calculation used by the block render template,
 * which lets headless clients and third-party integrations reuse the same logic.
 */
class CalcForge_REST {

	/**
	 * REST namespace for all plugin routes.
	 */
	const NAMESPACE_V1 = 'calcforge/v1';

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
						'sanitize_callback' => self::clamp_to( 0, 999999999999 ),
					),
					'down_payment'  => array(
						'type'              => 'number',
						'minimum'           => 0,
						'maximum'           => 999999999999,
						'required'          => false,
						'default'           => 0,
						'sanitize_callback' => self::clamp_to( 0, 999999999999 ),
					),
					'interest_rate' => array(
						'type'              => 'number',
						'minimum'           => 0,
						'maximum'           => 100,
						'required'          => false,
						'default'           => 0,
						'sanitize_callback' => self::clamp_to( 0, 100 ),
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
	 * Builds a REST sanitize callback that clamps a value between two bounds.
	 *
	 * `calcforge_clamp_float()` cannot be registered directly as a
	 * `sanitize_callback`: WordPress invokes those as
	 * `callback( $value, $request, $param )`, so the request object would land in
	 * the `$minimum` argument and the bounds would be garbage. Wrapping it in a
	 * closure pins the bounds and ignores the extra arguments.
	 *
	 * @param float $minimum Lower bound.
	 * @param float $maximum Upper bound.
	 * @return callable Sanitize callback.
	 */
	public static function clamp_to( $minimum, $maximum ) {
		return static function ( $value ) use ( $minimum, $maximum ) {
			return calcforge_clamp_float( $value, $minimum, $maximum );
		};
	}

	/**
	 * Permission callback for the calculation endpoint.
	 *
	 * The endpoint is intentionally open: it is a stateless, read-only helper
	 * that exposes only public mortgage arithmetic and returns no private data,
	 * so it does not require authentication or a nonce. The callback is kept as
	 * an override point: returning a WP_Error here blocks unauthenticated access.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Current request.
	 * @return true|WP_REST_Response True when allowed, an error response otherwise.
	 */
	public function check_permissions( $request ) {
		/**
		 * Filters whether the calculation endpoint requires authentication.
		 *
		 * @param bool                    $allowed Whether the request is allowed.
		 * @param WP_REST_Request<string> $request Current request.
		 */
		$allowed = apply_filters( 'calcforge_rest_calculate_allowed', true, $request );

		if ( true === $allowed ) {
			return true;
		}

		return rest_ensure_response(
			new WP_Error(
				'calcforge_rest_forbidden',
				esc_html__( 'Calculation requests are not permitted.', CALCFORGE_TEXT_DOMAIN ),
				array( 'status' => rest_authorization_required_code() )
			)
		);
	}

	/**
	 * Handles a calculation request.
	 *
	 * Reuses calcforge_calculate() so the `calcforge_calculation_result` filter
	 * applies to REST responses exactly as it does to rendered output.
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

		return rest_ensure_response( calcforge_calculate( $attributes ) );
	}
}
