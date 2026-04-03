<?php
/**
 * Admin settings page built on the Settings API.
 *
 * @package MortgageCalculatorBlock
 */

// Abort if this file is called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers a lightweight settings screen under Settings → Mortgage Calculator.
 *
 * Uses register_setting()/add_settings_section()/add_settings_field() so the
 * nonce verification (options.php) and capability checks required by the
 * Settings API are handled by core rather than by custom form code.
 */
class MCB_Settings {

	/**
	 * Option group used by register_setting() and settings_fields().
	 */
	const OPTION_GROUP = 'mcb_settings_group';

	/**
	 * Settings page slug.
	 */
	const PAGE_SLUG = 'mcb-settings';

	/**
	 * Capability required to manage settings.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Registers the hooks this component responds to.
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Adds the settings page under the Settings menu.
	 */
	public function add_page() {
		add_options_page(
			esc_html__( 'Mortgage Calculator', MCB_TEXT_DOMAIN ),
			esc_html__( 'Mortgage Calculator', MCB_TEXT_DOMAIN ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Registers the setting, section, and fields via the Settings API.
	 */
	public function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			'mcb_settings',
			array(
				'type'              => 'object',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => mcb_get_default_settings(),
			)
		);

		add_settings_section(
			'mcb_section_defaults',
			esc_html__( 'Defaults', MCB_TEXT_DOMAIN ),
			'__return_false',
			self::PAGE_SLUG
		);

		add_settings_field(
			'currency_symbol',
			esc_html__( 'Currency symbol', MCB_TEXT_DOMAIN ),
			array( $this, 'render_currency_symbol_field' ),
			self::PAGE_SLUG,
			'mcb_section_defaults',
			array( 'label_for' => 'mcb-currency-symbol' )
		);

		add_settings_field(
			'default_interest_rate',
			esc_html__( 'Default interest rate (%)', MCB_TEXT_DOMAIN ),
			array( $this, 'render_interest_rate_field' ),
			self::PAGE_SLUG,
			'mcb_section_defaults',
			array( 'label_for' => 'mcb-interest-rate' )
		);

		add_settings_field(
			'decimal_precision',
			esc_html__( 'Decimal precision', MCB_TEXT_DOMAIN ),
			array( $this, 'render_precision_field' ),
			self::PAGE_SLUG,
			'mcb_section_defaults',
			array( 'label_for' => 'mcb-decimal-precision' )
		);

		add_settings_field(
			'enable_amortization',
			esc_html__( 'Show amortization table by default', MCB_TEXT_DOMAIN ),
			array( $this, 'render_amortization_field' ),
			self::PAGE_SLUG,
			'mcb_section_defaults'
		);
	}

	/**
	 * Enqueues the admin stylesheet on the plugin settings screen only.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'mcb-admin-style',
			MCB_PLUGIN_URL . 'assets/admin/mcb-admin.css',
			array(),
			MCB_VERSION
		);
	}

	/**
	 * Renders the settings page markup. All output is escaped.
	 */
	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders the currency symbol text field.
	 */
	public function render_currency_symbol_field() {
		$settings = mcb_get_settings();
		?>
		<input
			type="text"
			id="mcb-currency-symbol"
			name="mcb_settings[currency_symbol]"
			value="<?php echo esc_attr( $settings['currency_symbol'] ); ?>"
			maxlength="8"
			class="small-text"
		/>
		<p class="description"><?php esc_html_e( 'Symbol displayed next to calculated amounts.', MCB_TEXT_DOMAIN ); ?></p>
		<?php
	}

	/**
	 * Renders the default interest rate field.
	 */
	public function render_interest_rate_field() {
		$settings = mcb_get_settings();
		?>
		<input
			type="number"
			id="mcb-interest-rate"
			name="mcb_settings[default_interest_rate]"
			value="<?php echo esc_attr( (string) $settings['default_interest_rate'] ); ?>"
			min="0"
			max="100"
			step="0.01"
			class="small-text"
		/>
		<p class="description"><?php esc_html_e( 'Annual interest rate used when no explicit value is set.', MCB_TEXT_DOMAIN ); ?></p>
		<?php
	}

	/**
	 * Renders the decimal precision field.
	 */
	public function render_precision_field() {
		$settings = mcb_get_settings();
		?>
		<input
			type="number"
			id="mcb-decimal-precision"
			name="mcb_settings[decimal_precision]"
			value="<?php echo esc_attr( (string) $settings['decimal_precision'] ); ?>"
			min="0"
			max="4"
			step="1"
			class="small-text"
		/>
		<p class="description"><?php esc_html_e( 'Number of decimal digits shown for amounts (0–4).', MCB_TEXT_DOMAIN ); ?></p>
		<?php
	}

	/**
	 * Renders the amortization default checkbox.
	 */
	public function render_amortization_field() {
		$settings = mcb_get_settings();
		?>
		<label>
			<input
				type="checkbox"
				name="mcb_settings[enable_amortization]"
				value="1"
				<?php checked( $settings['enable_amortization'] ); ?>
			/>
			<?php esc_html_e( 'When enabled, new blocks include the annual amortization schedule.', MCB_TEXT_DOMAIN ); ?>
		</label>
		<?php
	}

	/**
	 * Sanitizes submitted settings before persistence.
	 *
	 * Nonce verification is performed by options.php as part of the Settings
	 * API request lifecycle; this callback receives already-validated input.
	 * Fires the `mcb_settings_saved` action once values are normalized.
	 *
	 * @param mixed $input Raw settings array from the form.
	 * @return array<string,mixed> Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = mcb_sanitize_settings( $input );

		/**
		 * Fires after the admin settings have been sanitized and saved.
		 *
		 * @param array<string,mixed> $sanitized The settings about to be persisted.
		 */
		do_action( 'mcb_settings_saved', $sanitized );

		return $sanitized;
	}
}
