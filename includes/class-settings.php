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
	 * Returns the settings screen tabs.
	 *
	 * Every settings section gets a tab, followed by the reference-only tabs.
	 * Reference tabs own no options, so they are marked with an empty `section`
	 * and are rendered outside the settings form, away from the Save button.
	 *
	 * @return array<string,array<string,mixed>> Tab definitions keyed by tab id.
	 */
	private function get_tabs() {
		$tabs = array();

		foreach ( $this->get_sections() as $id => $section ) {
			$tabs[ $id ] = array(
				'title'   => $section['title'],
				'icon'    => str_replace( 'calcforge_', '', $id ),
				'section' => $id,
			);
		}

		return array_merge( $tabs, $this->get_reference_tabs() );
	}

	/**
	 * Returns the reference-only tabs, keyed by tab id.
	 *
	 * Each entry names the method that renders its panel, so adding another
	 * reference tab means adding one entry and one render method.
	 *
	 * @return array<string,array<string,mixed>> Reference tab definitions.
	 */
	private function get_reference_tabs() {
		return array(
			'calcforge_shortcode' => array(
				'title'        => __( 'Shortcode', CALCFORGE_TEXT_DOMAIN ),
				'description'  => __( 'Add the calculator to any post, page, or widget area without using the block editor.', CALCFORGE_TEXT_DOMAIN ),
				'icon'         => 'shortcode',
				'section'      => '',
				'render'       => 'render_shortcode_reference',
			),
			'calcforge_api'       => array(
				'title'        => __( 'Developer API', CALCFORGE_TEXT_DOMAIN ),
				'description'  => __( 'Run the same mortgage arithmetic from your own theme, plugin, or headless front end.', CALCFORGE_TEXT_DOMAIN ),
				'icon'         => 'api',
				'section'      => '',
				'render'       => 'render_api_reference',
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
					'<label class="calcforge-switch" for="%1$s">
						<input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s />
						<span class="calcforge-switch__slider" aria-hidden="true"></span>
						<span class="calcforge-switch__label">%4$s</span>
					</label>',
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
				echo '<div class="calcforge-settings__input-group">';
				printf( '<input type="number" id="%1$s" name="%2$s" value="%3$s" %4$s />', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ), $this->build_input_attributes( $field ) );
				if ( ! empty( $field['suffix'] ) ) {
					printf( '<span class="calcforge-settings__suffix-badge">%s</span>', esc_html( $field['suffix'] ) );
				}
				echo '</div>';
				break;

			default:
				printf( '<input type="text" id="%1$s" name="%2$s" value="%3$s" %4$s />', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ), $this->build_input_attributes( $field ) );
				break;
		}

		if ( ! empty( $field['description'] ) ) {
			printf( '<p class="description calcforge-settings__field-desc">%s</p>', esc_html( $field['description'] ) );
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
	 * Enqueues the admin stylesheet and script on this screen only.
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

		wp_enqueue_script(
			'calcforge-admin-script',
			CALCFORGE_PLUGIN_URL . 'assets/admin/calcforge-admin.js',
			array(),
			CALCFORGE_VERSION,
			true
		);
	}

	/**
	 * Renders an inline SVG icon for a settings section.
	 *
	 * @param string $section_id Section identifier.
	 */
	private function render_section_icon( $section_id ) {
		switch ( $section_id ) {
			case 'calcforge_currency':
				?>
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<circle cx="12" cy="12" r="9"></circle>
					<path d="M14.5 9a2.5 2.5 0 0 0-5 0v6a2.5 2.5 0 0 0 5 0"></path>
					<path d="M8 12h8"></path>
				</svg>
				<?php
				break;

			case 'calcforge_loan':
				?>
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<path d="M4 3h16a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
					<line x1="8" y1="9" x2="16" y2="9"></line>
					<line x1="8" y1="13" x2="12" y2="13"></line>
					<line x1="8" y1="17" x2="10" y2="17"></line>
					<line x1="15" y1="13" x2="15" y2="17"></line>
					<line x1="17" y1="15" x2="13" y2="15"></line>
				</svg>
				<?php
				break;

			case 'calcforge_appearance':
				?>
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<circle cx="13.5" cy="6.5" r=".5" fill="currentColor"></circle>
					<circle cx="17.5" cy="10.5" r=".5" fill="currentColor"></circle>
					<circle cx="8.5" cy="7.5" r=".5" fill="currentColor"></circle>
					<circle cx="6.5" cy="12.5" r=".5" fill="currentColor"></circle>
					<path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.563-2.512 5.563-5.563C21.437 6.082 17.207 2 12 2z"></path>
				</svg>
				<?php
				break;

			case 'calcforge_shortcode':
				?>
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<polyline points="16 18 22 12 16 6"></polyline>
					<polyline points="8 6 2 12 8 18"></polyline>
				</svg>
				<?php
				break;

			case 'calcforge_api':
				?>
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>
					<rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>
					<line x1="6" y1="6" x2="6.01" y2="6"></line>
					<line x1="6" y1="18" x2="6.01" y2="18"></line>
				</svg>
				<?php
				break;
		}
	}

	/**
	 * Renders the settings page shell.
	 *
	 * Uses a modern card-based layout with a dedicated sidebar for shortcode
	 * documentation and tips.
	 */
	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		?>
		<div class="wrap calcforge-settings">
			<div class="calcforge-settings__header">
				<div class="calcforge-settings__header-brand">
					<div class="calcforge-settings__logo-badge">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<rect x="4" y="2" width="16" height="20" rx="3"></rect>
							<line x1="8" y1="6" x2="16" y2="6"></line>
							<line x1="16" y1="14" x2="16" y2="18"></line>
							<path d="M16 10h.01"></path>
							<path d="M12 10h.01"></path>
							<path d="M8 10h.01"></path>
							<path d="M12 14h.01"></path>
							<path d="M8 14h.01"></path>
							<path d="M12 18h.01"></path>
							<path d="M8 18h.01"></path>
						</svg>
					</div>
					<div>
						<div class="calcforge-settings__title-row">
							<h1 class="calcforge-settings__title"><?php echo esc_html( get_admin_page_title() ); ?></h1>
							<span class="calcforge-settings__version-badge"><?php echo esc_html( 'v' . CALCFORGE_VERSION ); ?></span>
						</div>
						<p class="calcforge-settings__intro">
							<?php esc_html_e( 'These values are the site-wide defaults. Every new Mortgage Calculator block starts with them, and each block can then be adjusted on its own without affecting the others.', CALCFORGE_TEXT_DOMAIN ); ?>
						</p>
					</div>
				</div>
				<div class="calcforge-settings__status-pills">
					<span class="calcforge-pill calcforge-pill--success">
						<span class="calcforge-pill__dot"></span>
						<?php esc_html_e( 'Block Active', CALCFORGE_TEXT_DOMAIN ); ?>
					</span>
					<span class="calcforge-pill calcforge-pill--info">
						<span class="calcforge-pill__dot"></span>
						<?php esc_html_e( 'REST API Ready', CALCFORGE_TEXT_DOMAIN ); ?>
					</span>
				</div>
			</div>

			<div class="calcforge-settings__layout">
				<div class="calcforge-settings__main">
					<?php
					$tabs    = $this->get_tabs();
					$sections = $this->get_sections();
					?>

					<!-- Tab navigation -->
					<div class="calcforge-settings__tabs" role="tablist">
						<?php foreach ( $tabs as $tab_id => $tab ) : ?>
							<button
								type="button"
								class="calcforge-settings__tab-btn"
								role="tab"
								data-tab="<?php echo esc_attr( $tab_id ); ?>"
								aria-controls="<?php echo esc_attr( $tab_id ); ?>"
								aria-selected="false"
							>
								<span class="calcforge-settings__tab-icon calcforge-settings__tab-icon--<?php echo esc_attr( $tab['icon'] ); ?>">
									<?php $this->render_section_icon( $tab_id ); ?>
								</span>
								<?php echo esc_html( $tab['title'] ); ?>
							</button>
						<?php endforeach; ?>
					</div>

					<!-- Tab panels -->
					<div class="calcforge-settings__tab-panels">
						<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
							<?php settings_fields( self::OPTION_GROUP ); ?>

							<?php foreach ( $sections as $section_id => $section ) : ?>
								<div
									class="calcforge-settings__tab-panel"
									id="<?php echo esc_attr( $section_id ); ?>"
									role="tabpanel"
									aria-hidden="true"
								>
									<div class="calcforge-settings__panel-header">
										<div class="calcforge-settings__panel-icon calcforge-settings__panel-icon--<?php echo esc_attr( $section_id ); ?>">
											<?php $this->render_section_icon( $section_id ); ?>
										</div>
										<div class="calcforge-settings__panel-heading">
											<h2 class="calcforge-settings__panel-title"><?php echo esc_html( $section['title'] ); ?></h2>
											<p class="calcforge-settings__panel-description"><?php echo esc_html( $section['description'] ); ?></p>
										</div>
									</div>
									<table class="form-table" role="presentation">
										<?php do_settings_fields( self::PAGE_SLUG, $section_id ); ?>
									</table>
								</div>
							<?php endforeach; ?>

							<div class="calcforge-settings__save-bar">
								<?php submit_button( __( 'Save Changes', CALCFORGE_TEXT_DOMAIN ), 'primary', 'submit', false ); ?>
								<span class="calcforge-settings__save-note">
									<?php esc_html_e( 'Saved defaults immediately apply to all newly inserted calculators.', CALCFORGE_TEXT_DOMAIN ); ?>
								</span>
							</div>
						</form>

						<?php
						/*
						 * Reference tabs own no options, so they sit outside the
						 * settings form and never show the Save button.
						 */
						foreach ( $this->get_reference_tabs() as $ref_id => $ref ) :
							?>
							<div
								class="calcforge-settings__tab-panel"
								id="<?php echo esc_attr( $ref_id ); ?>"
								role="tabpanel"
								aria-hidden="true"
							>
								<div class="calcforge-settings__panel-header">
									<div class="calcforge-settings__panel-icon calcforge-settings__panel-icon--<?php echo esc_attr( $ref_id ); ?>">
										<?php $this->render_section_icon( $ref_id ); ?>
									</div>
									<div class="calcforge-settings__panel-heading">
										<h2 class="calcforge-settings__panel-title"><?php echo esc_html( $ref['title'] ); ?></h2>
										<p class="calcforge-settings__panel-description"><?php echo esc_html( $ref['description'] ); ?></p>
									</div>
								</div>

								<?php
								$render = $ref['render'];
								$this->$render();
								?>
							</div>
							<?php
						endforeach;
						?>
					</div>
				</div>

				<?php $this->render_sidebar(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the shortcode reference shown on the Shortcode tab.
	 */
	private function render_shortcode_reference() {		?>
		<div class="calcforge-settings__reference">
			<p class="calcforge-settings__card-text">
				<?php esc_html_e( 'Paste the calculator into any post, page, or widget area:', CALCFORGE_TEXT_DOMAIN ); ?>
			</p>
			<div class="calcforge-settings__code-box">
				<code class="calcforge-settings__code">[calcforge]</code>
				<button type="button" class="calcforge-copy-btn" data-clipboard-text="[calcforge]" title="<?php esc_attr_e( 'Copy to clipboard', CALCFORGE_TEXT_DOMAIN ); ?>">
					<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
						<rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
						<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
					</svg>
					<span class="calcforge-copy-btn__text"><?php esc_html_e( 'Copy', CALCFORGE_TEXT_DOMAIN ); ?></span>
				</button>
			</div>

			<p class="calcforge-settings__card-text">
				<?php esc_html_e( 'Add attributes to override the defaults, then add it as a block to keep changing it visually:', CALCFORGE_TEXT_DOMAIN ); ?>
			</p>
			<div class="calcforge-settings__code-box">
				<code class="calcforge-settings__code">[calcforge loanamount="350000" interestrate="4.75" loanterm="30" charttype="bar"]</code>
				<button type="button" class="calcforge-copy-btn" data-clipboard-text='[calcforge loanamount="350000" interestrate="4.75" loanterm="30" charttype="bar"]' title="<?php esc_attr_e( 'Copy to clipboard', CALCFORGE_TEXT_DOMAIN ); ?>">
					<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
						<rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
						<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
					</svg>
					<span class="calcforge-copy-btn__text"><?php esc_html_e( 'Copy', CALCFORGE_TEXT_DOMAIN ); ?></span>
				</button>
			</div>

			<h3 class="calcforge-settings__subheading">
				<?php esc_html_e( 'Attribute names are lowercase. Wrap the value in quotes. Attributes accept:', CALCFORGE_TEXT_DOMAIN ); ?>
			</h3>
			<dl class="calcforge-settings__attrs">
				<?php foreach ( CalcForge_Shortcode::get_documented_attributes() as $attr => $spec ) : ?>
					<dt>
						<code class="calcforge-settings__code"><?php echo esc_html( $attr ); ?></code>
						<span class="calcforge-settings__attr-type"><?php echo esc_html( $spec['type'] ); ?></span>
					</dt>
					<dd><?php echo esc_html( $spec['description'] ); ?></dd>
				<?php endforeach; ?>
			</dl>
		</div>
		<?php
	}

	/**
	 * Renders the REST endpoint reference shown on the Developer API tab.
	 */
	private function render_api_reference() {
		$endpoint = rest_url( CalcForge_REST::NAMESPACE_V1 . '/calculate' );
		$params   = array(
			'amount'          => array(
				'type'    => __( 'number', CALCFORGE_TEXT_DOMAIN ),
				'meta'    => __( 'required', CALCFORGE_TEXT_DOMAIN ),
				'summary' => __( 'Total amount being financed.', CALCFORGE_TEXT_DOMAIN ),
			),
			'down_payment'    => array(
				'type'    => __( 'number', CALCFORGE_TEXT_DOMAIN ),
				'meta'    => __( 'optional, default 0', CALCFORGE_TEXT_DOMAIN ),
				'summary' => __( 'Paid up front. Interest is charged on the remainder.', CALCFORGE_TEXT_DOMAIN ),
			),
			'interest_rate'   => array(
				'type'    => __( 'number', CALCFORGE_TEXT_DOMAIN ),
				'meta'    => __( 'optional, default 0', CALCFORGE_TEXT_DOMAIN ),
				'summary' => __( 'Annual rate as a percentage, up to 100.', CALCFORGE_TEXT_DOMAIN ),
			),
			'term_years'      => array(
				'type'    => __( 'integer', CALCFORGE_TEXT_DOMAIN ),
				'meta'    => __( 'optional, default 30', CALCFORGE_TEXT_DOMAIN ),
				'summary' => __( 'Length of the loan in years, from 1 to 60.', CALCFORGE_TEXT_DOMAIN ),
			),
			'with_schedule'   => array(
				'type'    => __( 'boolean', CALCFORGE_TEXT_DOMAIN ),
				'meta'    => __( 'optional, default false', CALCFORGE_TEXT_DOMAIN ),
				'summary' => __( 'Return the year-by-year amortization schedule alongside the totals.', CALCFORGE_TEXT_DOMAIN ),
			),
		);
		?>
		<div class="calcforge-settings__reference">
			<p class="calcforge-settings__card-text">
				<?php esc_html_e( 'A stateless endpoint that runs the same arithmetic as the calculator. It stores nothing and returns no private data, so it needs no authentication or nonce.', CALCFORGE_TEXT_DOMAIN ); ?>
			</p>
			<div class="calcforge-settings__code-box">
				<code class="calcforge-settings__code">POST <?php echo esc_html( $endpoint ); ?></code>
				<button type="button" class="calcforge-copy-btn" data-clipboard-text="<?php echo esc_attr( 'POST ' . $endpoint ); ?>" title="<?php esc_attr_e( 'Copy to clipboard', CALCFORGE_TEXT_DOMAIN ); ?>">
					<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
						<rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
						<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
					</svg>
					<span class="calcforge-copy-btn__text"><?php esc_html_e( 'Copy', CALCFORGE_TEXT_DOMAIN ); ?></span>
				</button>
			</div>

			<h3 class="calcforge-settings__subheading">
				<?php esc_html_e( 'Parameters', CALCFORGE_TEXT_DOMAIN ); ?>
			</h3>
			<dl class="calcforge-settings__attrs calcforge-settings__attrs--flat">
				<?php foreach ( $params as $param => $spec ) : ?>
					<dt>
						<code class="calcforge-settings__code"><?php echo esc_html( $param ); ?></code>
						<span class="calcforge-settings__attr-type"><?php echo esc_html( $spec['type'] ); ?></span>
						<span class="calcforge-settings__attr-note"><?php echo esc_html( $spec['meta'] ); ?></span>
					</dt>
					<dd><?php echo esc_html( $spec['summary'] ); ?></dd>
				<?php endforeach; ?>
			</dl>

			<h3 class="calcforge-settings__subheading">
				<?php esc_html_e( 'Example request', CALCFORGE_TEXT_DOMAIN ); ?>
			</h3>
			<div class="calcforge-settings__code-box">
				<code class="calcforge-settings__code calcforge-settings__code--pre">curl -X POST <?php echo esc_html( $endpoint ); ?> \
	-H 'Content-Type: application/json' \
	-d '{"amount":350000,"down_payment":70000,"interest_rate":4.75,"term_years":30}'</code>
			</div>

			<h3 class="calcforge-settings__subheading">
				<?php esc_html_e( 'Example response', CALCFORGE_TEXT_DOMAIN ); ?>
			</h3>
			<div class="calcforge-settings__code-box">
				<code class="calcforge-settings__code calcforge-settings__code--pre">{
	"principal": 280000,
	"monthly_payment": 1460.61,
	"total_paid": 525819.6,
	"total_interest": 245819.6,
	"months": 360,
	"schedule": []
}</code>
			</div>
			<p class="calcforge-settings__card-text">
				<?php esc_html_e( 'The schedule is empty unless you pass with_schedule. It runs the same calculations as the block, so the calcforge_calculation_result filter applies to responses too. Return false from the calcforge_rest_calculate_allowed filter to require authentication.', CALCFORGE_TEXT_DOMAIN ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Renders the help and developer reference sidebar.
	 */
	private function render_sidebar() {
		?>
		<aside class="calcforge-settings__sidebar">
			<div class="calcforge-settings__card calcforge-settings__card--help">
				<div class="calcforge-settings__card-header">
					<span class="calcforge-settings__card-badge-icon calcforge-settings__card-badge-icon--amber">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<circle cx="12" cy="12" r="10"></circle>
							<line x1="12" y1="16" x2="12" y2="12"></line>
							<line x1="12" y1="8" x2="12.01" y2="8"></line>
						</svg>
					</span>
					<h2 class="calcforge-settings__card-title">
						<?php esc_html_e( 'Help', CALCFORGE_TEXT_DOMAIN ); ?>
					</h2>
				</div>
				<ul class="calcforge-settings__tips-list">
					<li>
						<?php esc_html_e( 'Insert the block from the block inserter and search for Mortgage Calculator. Each block keeps its own values, so changing the defaults here only affects calculators inserted from now on.', CALCFORGE_TEXT_DOMAIN ); ?>
					</li>
					<li>
						<?php esc_html_e( 'Interest is charged on the financed principal only, which is the loan amount minus the down payment. Charts and the amortization table can be switched off per block from the block sidebar.', CALCFORGE_TEXT_DOMAIN ); ?>
					</li>
				</ul>
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
