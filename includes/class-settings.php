<?php
/**
 * Global settings screen.
 *
 * @package CalcForge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers Settings -> CalcForge, where administrators choose the defaults
 * every newly inserted calculator block starts from.
 *
 * Built on register_setting()/add_settings_section()/add_settings_field() so
 * nonce verification and capability checks are handled by core rather than by
 * custom form code. The field table below is the single definition of the
 * screen: registration, markup, and input attributes all read from it, so a new
 * option cannot be added in one place and forgotten in another.
 */
class CalcForge_Settings {

	/**
	 * Option group used by register_setting() and settings_fields().
	 */
	const OPTION_GROUP = 'calcforge_settings_group';

	/**
	 * Name of the settings option.
	 */
	const OPTION_NAME = 'calcforge_settings';

	/**
	 * Settings page slug.
	 */
	const PAGE_SLUG = 'calcforge-settings';

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
			esc_html__( 'CalcForge', CALCFORGE_TEXT_DOMAIN ),
			esc_html__( 'CalcForge', CALCFORGE_TEXT_DOMAIN ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Describes every field on the screen.
	 *
	 * @return array<string,array<string,mixed>> Field definitions keyed by option name.
	 */
	private function get_fields() {
		return array(
			'currency_symbol'       => array(
				'section'     => 'calcforge_currency',
				'type'        => 'text',
				'label'       => __( 'Currency symbol', CALCFORGE_TEXT_DOMAIN ),
				'description' => __( 'Shown next to every calculated amount. Up to 8 characters.', CALCFORGE_TEXT_DOMAIN ),
				'input'       => array(
					'maxlength' => 8,
					'class'     => 'small-text',
				),
			),
			'currency_position'     => array(
				'section'     => 'calcforge_currency',
				'type'        => 'select',
				'label'       => __( 'Symbol position', CALCFORGE_TEXT_DOMAIN ),
				'description' => __( 'Which side of the amount the symbol sits on. Each block can change it later.', CALCFORGE_TEXT_DOMAIN ),
				'options'     => 'calcforge_get_currency_positions',
			),
			'decimal_precision'     => array(
				'section'     => 'calcforge_currency',
				'type'        => 'number',
				'label'       => __( 'Decimal places', CALCFORGE_TEXT_DOMAIN ),
				'description' => __( 'Digits shown after the decimal separator, from 0 to 4.', CALCFORGE_TEXT_DOMAIN ),
				'input'       => array(
					'min'  => 0,
					'max'  => 4,
					'step' => 1,
					'class' => 'small-text',
				),
			),
			'default_loan_amount'   => array(
				'section'     => 'calcforge_loan',
				'type'        => 'number',
				'label'       => __( 'Loan amount', CALCFORGE_TEXT_DOMAIN ),
				'description' => __( 'Pre-filled amount for a newly inserted calculator.', CALCFORGE_TEXT_DOMAIN ),
				'input'       => array(
					'min'  => 0,
					'step' => 'any',
					'class' => 'regular-text',
				),
			),
			'default_down_payment'  => array(
				'section'     => 'calcforge_loan',
				'type'        => 'number',
				'label'       => __( 'Down payment', CALCFORGE_TEXT_DOMAIN ),
				'description' => __( 'Pre-filled down payment. Interest is charged on the financed principal only.', CALCFORGE_TEXT_DOMAIN ),
				'input'       => array(
					'min'  => 0,
					'step' => 'any',
					'class' => 'regular-text',
				),
			),
			'default_loan_term'     => array(
				'section'     => 'calcforge_loan',
				'type'        => 'number',
				'label'       => __( 'Loan term', CALCFORGE_TEXT_DOMAIN ),
				'suffix'      => __( 'years', CALCFORGE_TEXT_DOMAIN ),
				'description' => __( 'Term in years, from 1 to 60.', CALCFORGE_TEXT_DOMAIN ),
				'input'       => array(
					'min'  => 1,
					'max'  => 60,
					'step' => 1,
					'class' => 'small-text',
				),
			),
			'default_interest_rate' => array(
				'section'     => 'calcforge_loan',
				'type'        => 'number',
				'label'       => __( 'Interest rate', CALCFORGE_TEXT_DOMAIN ),
				'suffix'      => __( '% per year', CALCFORGE_TEXT_DOMAIN ),
				'description' => __( 'Annual rate used for new calculators, from 0 to 100.', CALCFORGE_TEXT_DOMAIN ),
				'input'       => array(
					'min'  => 0,
					'max'  => 100,
					'step' => '0.01',
					'class' => 'small-text',
				),
			),
			'enable_amortization'   => array(
				'section'     => 'calcforge_loan',
				'type'        => 'checkbox',
				'label'       => __( 'Include the amortization table', CALCFORGE_TEXT_DOMAIN ),
				'description' => __( 'Adds the year-by-year schedule to new calculators.', CALCFORGE_TEXT_DOMAIN ),
			),
			'default_theme'         => array(
				'section'     => 'calcforge_appearance',
				'type'        => 'select',
				'label'       => __( 'Skin', CALCFORGE_TEXT_DOMAIN ),
				'description' => __( 'Colour scheme applied to new calculators. Each block can change it later.', CALCFORGE_TEXT_DOMAIN ),
				'options'     => 'calcforge_get_skins',
			),
			'default_chart_type'    => array(
				'section'     => 'calcforge_appearance',
				'type'        => 'select',
				'label'       => __( 'Charts', CALCFORGE_TEXT_DOMAIN ),
				'description' => __( 'Which charts new calculators start with.', CALCFORGE_TEXT_DOMAIN ),
				'options'     => 'calcforge_get_chart_types',
			),
			'default_layout'        => array(
				'section'     => 'calcforge_appearance',
				'type'        => 'select',
				'label'       => __( 'Layout', CALCFORGE_TEXT_DOMAIN ),
				'description' => __( 'Split places the inputs beside the results on wide screens and stacks them on narrow ones.', CALCFORGE_TEXT_DOMAIN ),
				'options'     => 'calcforge_get_layouts',
			),
		);
	}

	/**
	 * Describes the sections on the screen.
	 *
	 * @return array<string,array<string,string>> Section id => title and description.
	 */
	private function get_sections() {
		return array(
			'calcforge_currency'    => array(
				'title'       => __( 'Currency and formatting', CALCFORGE_TEXT_DOMAIN ),
				'description' => __( 'How amounts are written across every calculator on the site.', CALCFORGE_TEXT_DOMAIN ),
			),
			'calcforge_loan'        => array(
				'title'       => __( 'Loan defaults', CALCFORGE_TEXT_DOMAIN ),
				'description' => __( 'The figures a brand new calculator block starts with.', CALCFORGE_TEXT_DOMAIN ),
			),
			'calcforge_appearance'  => array(
				'title'       => __( 'Appearance', CALCFORGE_TEXT_DOMAIN ),
				'description' => __( 'Default skin and chart selection for new calculators.', CALCFORGE_TEXT_DOMAIN ),
			),
		);
	}

	/**
	 * Registers the option, sections, and fields with the Settings API.
	 */
	public function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'object',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => calcforge_get_default_settings(),
			)
		);

		foreach ( $this->get_sections() as $id => $section ) {
			add_settings_section(
				$id,
				esc_html( $section['title'] ),
				array( $this, 'render_section' ),
				self::PAGE_SLUG,
				array(
					'section' => $id,
				)
			);
		}

		foreach ( $this->get_fields() as $key => $field ) {
			add_settings_field(
				$key,
				esc_html( $field['label'] ),
				array( $this, 'render_field' ),
				self::PAGE_SLUG,
				$field['section'],
				array(
					'key'       => $key,
					'label_for' => $this->get_field_id( $key ),
				)
			);
		}
	}

	/**
	 * Renders a section description.
	 *
	 * @param array<string,string> $args Section arguments.
	 */
	public function render_section( $args ) {
		$sections = $this->get_sections();
		$id       = $args['section'] ?? '';

		if ( ! isset( $sections[ $id ]['description'] ) ) {
			return;
		}

		printf(
			'<p class="description calcforge-settings__section-description">%s</p>',
			esc_html( $sections[ $id ]['description'] )
		);
	}

	/**
	 * Renders one settings field.
	 *
	 * @param array<string,string> $args Field arguments, including the option key.
	 */
	public function render_field( $args ) {
		$key   = $args['key'] ?? '';
		$field = $this->get_fields()[ $key ] ?? null;

		if ( null === $field ) {
			return;
		}

		$settings = calcforge_get_settings();
		$name     = self::OPTION_NAME . '[' . $key . ']';
		$id       = $this->get_field_id( $key );
		$value    = $settings[ $key ] ?? '';

		switch ( $field['type'] ) {
			case 'checkbox':
				printf(
					'<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s /> %4$s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( ! empty( $value ), true, false ),
					esc_html( $field['label'] )
				);
				break;

			case 'select':
				printf( '<select id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $name ) );
				foreach ( call_user_func( $field['options'] ) as $option_value => $option_label ) {
					printf(
						'<option value="%1$s" %2$s>%3$s</option>',
						esc_attr( $option_value ),
						selected( (string) $value, (string) $option_value, false ),
						esc_html( $option_label )
					);
				}
				echo '</select>';
				break;

			case 'number':
				printf( '<input type="number" id="%1$s" name="%2$s" value="%3$s" %4$s />', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ), $this->build_input_attributes( $field ) );
				if ( ! empty( $field['suffix'] ) ) {
					printf( ' <span class="calcforge-settings__suffix">%s</span>', esc_html( $field['suffix'] ) );
				}
				break;

			default:
				printf( '<input type="text" id="%1$s" name="%2$s" value="%3$s" %4$s />', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ), $this->build_input_attributes( $field ) );
				break;
		}

		if ( ! empty( $field['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $field['description'] ) );
		}
	}

	/**
	 * Returns the DOM id used for a field's input and label.
	 *
	 * @param string $key Option key.
	 * @return string Element id.
	 */
	private function get_field_id( $key ) {
		return 'calcforge-' . str_replace( '_', '-', $key );
	}

	/**
	 * Builds the HTML attribute string for an input from its field definition.
	 *
	 * @param array<string,mixed> $field Field definition.
	 * @return string Escaped attribute string.
	 */
	private function build_input_attributes( $field ) {
		$attributes = '';

		foreach ( (array) ( $field['input'] ?? array() ) as $key => $value ) {
			$attributes .= sprintf(
				' %s="%s"',
				esc_attr( $key ),
				esc_attr( (string) $value )
			);
		}

		return $attributes;
	}

	/**
	 * Enqueues the admin stylesheet on this screen only.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'calcforge-admin-style',
			CALCFORGE_PLUGIN_URL . 'assets/admin/calcforge-admin.css',
			array(),
			CALCFORGE_VERSION
		);
	}

	/**
	 * Renders the settings page shell.
	 *
	 * The form sits in a wide main column with a sticky help sidebar beside it,
	 * so the shortcode reference stays reachable while scrolling a long form
	 * without turning the Settings API tables into a second, hand-written form.
	 */
	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		?>
		<div class="wrap calcforge-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<p class="calcforge-settings__intro">
				<?php esc_html_e( 'These values are the site-wide defaults. Every new Mortgage Calculator block starts with them, and each block can then be adjusted on its own without affecting the others.', CALCFORGE_TEXT_DOMAIN ); ?>
			</p>

			<div class="calcforge-settings__layout">
				<div class="calcforge-settings__main">
					<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
						<?php
						settings_fields( self::OPTION_GROUP );
						do_settings_sections( self::PAGE_SLUG );
						submit_button();
						?>
					</form>
				</div>

				<?php $this->render_sidebar(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the help and shortcode reference sidebar.
	 */
	private function render_sidebar() {
		?>
		<aside class="calcforge-settings__sidebar">
			<div class="calcforge-settings__card calcforge-settings__card--shortcode">
				<h2 class="calcforge-settings__card-title">
					<?php esc_html_e( 'Shortcode', CALCFORGE_TEXT_DOMAIN ); ?>
				</h2>
				<p class="calcforge-settings__card-text">
					<?php esc_html_e( 'Paste the calculator into any post, page, or widget area:', CALCFORGE_TEXT_DOMAIN ); ?>
				</p>
				<p class="calcforge-settings__card-text">
					<code class="calcforge-settings__code">[calcforge]</code>
				</p>
				<p class="calcforge-settings__card-text">
					<?php esc_html_e( 'Add attributes to override the defaults, then add it as a block to keep changing it visually:', CALCFORGE_TEXT_DOMAIN ); ?>
				</p>
				<p class="calcforge-settings__card-text">
					<code class="calcforge-settings__code">[calcforge loanamount="350000" interestrate="4.75" loanterm="30" charttype="bar"]</code>
				</p>
				<p class="calcforge-settings__card-text">
					<?php esc_html_e( 'Attribute names are lowercase. Wrap the value in quotes. Attributes accept:', CALCFORGE_TEXT_DOMAIN ); ?>
				</p>
				<dl class="calcforge-settings__attrs">
					<?php foreach ( CalcForge_Shortcode::get_documented_attributes() as $attr => $spec ) : ?>
						<dt><code class="calcforge-settings__code"><?php echo esc_html( $attr ); ?></code></dt>
						<dd><?php echo esc_html( $spec['description'] ); ?></dd>
					<?php endforeach; ?>
				</dl>
			</div>

			<div class="calcforge-settings__card calcforge-settings__card--help">
				<h2 class="calcforge-settings__card-title">
					<?php esc_html_e( 'Help', CALCFORGE_TEXT_DOMAIN ); ?>
				</h2>
				<p class="calcforge-settings__card-text">
					<?php esc_html_e( 'Insert the block from the block inserter and search for Mortgage Calculator. Each block keeps its own values, so changing the defaults here only affects calculators inserted from now on.', CALCFORGE_TEXT_DOMAIN ); ?>
				</p>
				<p class="calcforge-settings__card-text">
					<?php esc_html_e( 'Interest is charged on the financed principal only, which is the loan amount minus the down payment. Charts and the amortization table can be switched off per block from the block sidebar.', CALCFORGE_TEXT_DOMAIN ); ?>
				</p>
			</div>
		</aside>
		<?php
	}

	/**
	 * Sanitizes submitted settings before persistence.
	 *
	 * Nonce verification is performed by options.php as part of the Settings API
	 * request lifecycle, so this callback receives already-validated input.
	 *
	 * @param mixed $input Raw settings array from the form.
	 * @return array<string,mixed> Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = calcforge_sanitize_settings( $input );

		/**
		 * Fires after the admin settings have been sanitized and saved.
		 *
		 * @param array<string,mixed> $sanitized The settings about to be persisted.
		 */
		do_action( 'calcforge_settings_saved', $sanitized );

		return $sanitized;
	}
}
