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
 * Registers the mcb/v1/calculate route.
 *
 * The endpoint mirrors the PHP calculation used by render.php, enabling
 * headless clients and third-party integrations to reuse the same logic.
 */
class MCB_REST_API {

	/**
	 * REST namespace for all plugin routes.
	 */
	const NAMESPACE_V1 = 'mcb/v1';

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
						'sanitize_callback' => 'mcb_clamp_float',
					),
					'down_payment'  => array(
						'type'              => 'number',
						'minimum'           => 0,
						'maximum'           => 999999999999,
						'required'          => false,
						'default'           => 0,
						'sanitize_callback' => 'mcb_clamp_float',
					),
					'interest_rate' => array(
						'type'              => 'number',
						'minimum'           => 0,
						'maximum'           => 100,
						'required'          => false,
						'default'           => 0,
						'sanitize_callback' => 'mcb_clamp_float',
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
	 * Permission callback verifying the standard WordPress REST nonce.
	 *
	 * Authenticated editor users are also accepted so the block editor can
	 * call the endpoint during previewing.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Current request.
	 * @return true|WP_Error True when allowed, error object otherwise.
	 */
	public function check_permissions( $request ) {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );

		if ( '' !== $nonce && wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return true;
		}

		if ( current_user_can( 'edit_posts' ) ) {
			return true;
		}

		return new WP_Error(
			'mcb_rest_forbidden',
			esc_html__( 'Invalid or missing nonce.', MCB_TEXT_DOMAIN ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Handles a calculation request.
	 *
	 * Reuses mcb_calculate() so the `mcb_calculation_result` filter applies to
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

		return rest_ensure_response( mcb_calculate( $attributes ) );
	}
}
