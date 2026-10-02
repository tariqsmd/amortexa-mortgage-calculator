<?php
/**
 * REST API endpoint for server-side mortgage calculations.
 *
 * @package Amortexa
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the amortexa-mortgage-calculator/v1/calculate route.
 *
 * The endpoint mirrors the PHP calculation used by the block render template,
 * which lets headless clients and third-party integrations reuse the same logic.
 */
class Amortexa_REST {

	/**
	 * REST namespace for all plugin routes.
	 */
	const NAMESPACE_V1 = 'amortexa-mortgage-calculator/v1';

	/**
	 * Registers the hooks this component responds to.
	 */
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_post_dispatch', array( $this, 'add_rate_limit_headers' ), 10, 1 );
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
	 * `amortexa_clamp_float()` cannot be registered directly as a
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
			return amortexa_clamp_float( $value, $minimum, $maximum );
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
	 * Open does not mean unmetered. Each caller gets a fixed request budget per
	 * window so the endpoint cannot be used to generate unbounded traffic against
	 * the site, while legitimate integrations stay well inside the allowance.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Current request.
	 * @return true|WP_Error True when allowed, an error otherwise.
	 */
	public function check_permissions( $request ) {
		/**
		 * Filters whether the calculation endpoint requires authentication.
		 *
		 * @param bool                    $allowed Whether the request is allowed.
		 * @param WP_REST_Request<string> $request Current request.
		 */
		$allowed = apply_filters( 'amortexa_rest_calculate_allowed', true, $request );

		if ( true !== $allowed ) {
			return new WP_Error(
				'amortexa_rest_forbidden',
				esc_html__( 'Calculation requests are not permitted.', AMORTEXA_TEXT_DOMAIN ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		list( $max, $window ) = $this->rate_limit_policy();

		/*
		 * This only reads the current window. It must not record a hit, because
		 * the REST server calls a permission callback more than once per request:
		 * rest_send_allow_header() invokes it a second time to decide which
		 * methods to advertise in the Allow header. Counting here would charge
		 * every caller twice and halve the effective budget. The hit is recorded
		 * once, in handle_calculate(), after permission has passed.
		 */
		$current = amortexa_rate_limit_peek( amortexa_rate_limit_client_key() );

		if ( $max < 1 || $current['count'] < $max ) {
			return true;
		}

		/*
		 * Must be a WP_Error: the REST server only treats WP_Error, false and
		 * null as a refusal from a permission callback. Anything else, including
		 * a WP_REST_Response, is truthy and the request is let through, so the
		 * limit would never apply. The status travels in the error data, and
		 * add_rate_limit_headers() turns it into real response headers.
		 */
		return new WP_Error(
			'amortexa_rest_rate_limited',
			esc_html__( 'Too many calculation requests. Please retry shortly.', AMORTEXA_TEXT_DOMAIN ),
			array(
				'status'      => 429,
				'retry_after' => max( 1, $current['retry_after'] ),
				'limit'       => $max,
			)
		);
	}

	/**
	 * Resolves the configured rate limit policy.
	 *
	 * @return array{0:int,1:int} Allowed requests per window, and window seconds.
	 */
	private function rate_limit_policy() {
		/**
		 * Filters the number of calculation requests allowed per client per window.
		 *
		 * Return 0 or less to disable rate limiting entirely.
		 *
		 * @param int $max Requests permitted per window.
		 */
		$max = (int) apply_filters( 'amortexa_rest_calculate_rate_limit', AMORTEXA_RATE_LIMIT_MAX );

		/**
		 * Filters the length of the calculation rate limit window, in seconds.
		 *
		 * @param int $window Window length in seconds.
		 */
		$window = (int) apply_filters( 'amortexa_rest_calculate_rate_window', AMORTEXA_RATE_LIMIT_WINDOW );

		return array( $max, $window );
	}

	/**
	 * Adds rate limit headers to a refused calculation response.
	 *
	 * A permission callback cannot set headers directly, so the values are
	 * carried on the error data and copied onto the response here.
	 *
	 * @param WP_REST_Response<mixed> $response Dispatched response.
	 * @return WP_REST_Response<mixed> The same response, with headers applied.
	 */
	public function add_rate_limit_headers( $response ) {
		if ( ! ( $response instanceof WP_REST_Response ) ) {
			return $response;
		}

		$data = $response->get_data();

		if ( ! is_array( $data ) || ! isset( $data['code'] ) || 'amortexa_rest_rate_limited' !== $data['code'] ) {
			return $response;
		}

		$details = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : array();
		$retry   = isset( $details['retry_after'] ) ? (int) $details['retry_after'] : 0;

		if ( $retry > 0 ) {
			$response->header( 'Retry-After', (string) $retry );
		}

		if ( isset( $details['limit'] ) ) {
			$response->header( 'X-RateLimit-Limit', (string) (int) $details['limit'] );
		}

		$response->header( 'X-RateLimit-Remaining', '0' );

		if ( $retry > 0 ) {
			$response->header( 'X-RateLimit-Reset', (string) $retry );
		}

		return $response;
	}

	/**
	 * Handles a calculation request.
	 *
	 * Reuses amortexa_calculate() so the `amortexa_calculation_result` filter
	 * applies to REST responses exactly as it does to rendered output.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Current request.
	 * @return WP_REST_Response Calculation result.
	 */
	public function handle_calculate( $request ) {
		/*
		 * Recorded here rather than in check_permissions(), which the REST server
		 * calls more than once per request, so one served request costs exactly
		 * one unit of the budget.
		 */
		list( $max, $window ) = $this->rate_limit_policy();
		amortexa_rate_limit_hit( amortexa_rate_limit_client_key(), $max, $window );

		$attributes = array(
			'loanAmount'       => (float) $request['amount'],
			'downPayment'      => (float) $request['down_payment'],
			'interestRate'     => (float) $request['interest_rate'],
			'loanTerm'         => absint( $request['term_years'] ),
			'showAmortization' => ! empty( $request['with_schedule'] ),
		);

		return rest_ensure_response( amortexa_calculate( $attributes ) );
	}
}
