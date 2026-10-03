<?php
/**
 * Global settings screen.
 *
 * @package Amortexa
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers Settings -> Amortexa, where administrators choose the defaults
 * every newly inserted calculator block starts from.
 *
 * Built on register_setting()/add_settings_section()/add_settings_field() so
 * nonce verification and capability checks are handled by core rather than by
 * custom form code. The field table below is the single definition of the
 * screen: registration, markup, and input attributes all read from it, so a new
 * option cannot be added in one place and forgotten in another.
 */
class Amortexa_Settings {

	/**
	 * Option group used by register_setting() and settings_fields().
	 */
	const OPTION_GROUP = 'amortexa_settings_group';

	/**
	 * Name of the settings option.
	 */
	const OPTION_NAME = 'amortexa_settings';

	/**
	 * Settings page slug.
	 */
	const PAGE_SLUG = 'amortexa-settings';

	/**
	 * Capability required to manage settings.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * DOM id prefix for a tab button, so each tabpanel can name itself.
	 */
	const TAB_ID_PREFIX = 'amortexa-tab-';

	/**
	 * Registers the hooks this component responds to.
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Adds the settings page as its own top-level admin menu.
	 *
	 * The calculator is a standalone feature with its own screen, reference tabs
	 * and asset bundle, so it gets a dedicated menu entry rather than being filed
	 * under Settings. The Settings screen already hosts dozens of unrelated
	 * options, which buried it.
	 *
	 * The option is still registered and saved through the Settings API against
	 * the same option group, so `settings_fields()` and options.php keep working
	 * unchanged from a top-level page.
	 */
	public function add_page() {
		add_menu_page(
			esc_html__( 'Amortexa', 'amortexa-mortgage-calculator' ),
			esc_html__( 'Amortexa', 'amortexa-mortgage-calculator' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-calculator',
			58
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
				'section'     => 'amortexa_currency',
				'type'        => 'text',
				'label'       => __( 'Currency symbol', 'amortexa-mortgage-calculator' ),
				'description' => __( 'Shown next to every calculated amount. Up to 8 characters.', 'amortexa-mortgage-calculator' ),
				'input'       => array(
					'maxlength' => 8,
					'class'     => 'small-text',
				),
			),
			'currency_position'     => array(
				'section'     => 'amortexa_currency',
				'type'        => 'select',
				'label'       => __( 'Symbol position', 'amortexa-mortgage-calculator' ),
				'description' => __( 'Which side of the amount the symbol sits on. Each block can change it later.', 'amortexa-mortgage-calculator' ),
				'options'     => 'amortexa_get_currency_positions',
			),
			'decimal_precision'     => array(
				'section'     => 'amortexa_currency',
				'type'        => 'number',
				'label'       => __( 'Decimal places', 'amortexa-mortgage-calculator' ),
				'description' => __( 'Digits shown after the decimal separator, from 0 to 4.', 'amortexa-mortgage-calculator' ),
				'input'       => array(
					'min'   => 0,
					'max'   => 4,
					'step'  => 1,
					'class' => 'small-text',
				),
			),
			'default_loan_amount'   => array(
				'section'     => 'amortexa_loan',
				'type'        => 'number',
				'label'       => __( 'Loan amount', 'amortexa-mortgage-calculator' ),
				'description' => __( 'Pre-filled amount for a newly inserted calculator.', 'amortexa-mortgage-calculator' ),
				'input'       => array(
					'min'   => 0,
					'step'  => 'any',
					'class' => 'regular-text',
				),
			),
			'default_down_payment'  => array(
				'section'     => 'amortexa_loan',
				'type'        => 'number',
				'label'       => __( 'Down payment', 'amortexa-mortgage-calculator' ),
				'description' => __( 'Pre-filled down payment. Interest is charged on the financed principal only.', 'amortexa-mortgage-calculator' ),
				'input'       => array(
					'min'   => 0,
					'step'  => 'any',
					'class' => 'regular-text',
				),
			),
			'default_loan_term'     => array(
				'section'     => 'amortexa_loan',
				'type'        => 'number',
				'label'       => __( 'Loan term', 'amortexa-mortgage-calculator' ),
				'suffix'      => __( 'years', 'amortexa-mortgage-calculator' ),
				'description' => __( 'Term in years, from 1 to 60.', 'amortexa-mortgage-calculator' ),
				'input'       => array(
					'min'   => 1,
					'max'   => 60,
					'step'  => 1,
					'class' => 'small-text',
				),
			),
			'default_interest_rate' => array(
				'section'     => 'amortexa_loan',
				'type'        => 'number',
				'label'       => __( 'Interest rate', 'amortexa-mortgage-calculator' ),
				'suffix'      => __( '% per year', 'amortexa-mortgage-calculator' ),
				'description' => __( 'Annual rate used for new calculators, from 0 to 100.', 'amortexa-mortgage-calculator' ),
				'input'       => array(
					'min'   => 0,
					'max'   => 100,
					'step'  => '0.01',
					'class' => 'small-text',
				),
			),
			'enable_amortization'   => array(
				'section'     => 'amortexa_loan',
				'type'        => 'checkbox',
				'label'       => __( 'Include the amortization table', 'amortexa-mortgage-calculator' ),
				'description' => __( 'Adds the year-by-year schedule to new calculators.', 'amortexa-mortgage-calculator' ),
			),
			'default_theme'         => array(
				'section'     => 'amortexa_appearance',
				'type'        => 'select',
				'label'       => __( 'Skin', 'amortexa-mortgage-calculator' ),
				'description' => __( 'Colour scheme applied to new calculators. Each block can change it later.', 'amortexa-mortgage-calculator' ),
				'options'     => 'amortexa_get_skins',
			),
			'default_chart_type'    => array(
				'section'     => 'amortexa_appearance',
				'type'        => 'select',
				'label'       => __( 'Charts', 'amortexa-mortgage-calculator' ),
				'description' => __( 'Which charts new calculators start with.', 'amortexa-mortgage-calculator' ),
				'options'     => 'amortexa_get_chart_types',
			),
			'default_layout'        => array(
				'section'     => 'amortexa_appearance',
				'type'        => 'select',
				'label'       => __( 'Layout', 'amortexa-mortgage-calculator' ),
				'description' => __( 'Split places the inputs beside the results on wide screens and stacks them on narrow ones.', 'amortexa-mortgage-calculator' ),
				'options'     => 'amortexa_get_layouts',
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
			'amortexa_currency'   => array(
				'title'       => __( 'Currency and formatting', 'amortexa-mortgage-calculator' ),
				'description' => __( 'How amounts are written across every calculator on the site.', 'amortexa-mortgage-calculator' ),
			),
			'amortexa_loan'       => array(
				'title'       => __( 'Loan defaults', 'amortexa-mortgage-calculator' ),
				'description' => __( 'The figures a brand new calculator block starts with.', 'amortexa-mortgage-calculator' ),
			),
			'amortexa_appearance' => array(
				'title'       => __( 'Appearance', 'amortexa-mortgage-calculator' ),
				'description' => __( 'Default skin and chart selection for new calculators.', 'amortexa-mortgage-calculator' ),
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
				'icon'    => str_replace( 'amortexa_', '', $id ),
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
			'amortexa_shortcode' => array(
				'title'       => __( 'Shortcode', 'amortexa-mortgage-calculator' ),
				'description' => __( 'Add the calculator to any post, page, or widget area without using the block editor.', 'amortexa-mortgage-calculator' ),
				'icon'        => 'shortcode',
				'section'     => '',
				'render'      => 'render_shortcode_reference',
			),
			'amortexa_api'       => array(
				'title'       => __( 'Developer API', 'amortexa-mortgage-calculator' ),
				'description' => __( 'Run the same mortgage arithmetic from your own theme, plugin, or headless front end.', 'amortexa-mortgage-calculator' ),
				'icon'        => 'api',
				'section'     => '',
				'render'      => 'render_api_reference',
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
				'default'           => amortexa_get_default_settings(),
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
			'<p class="description amortexa-settings__section-description">%s</p>',
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

		$settings = amortexa_get_settings();
		$name     = self::OPTION_NAME . '[' . $key . ']';
		$id       = $this->get_field_id( $key );
		$value    = $settings[ $key ] ?? '';

		switch ( $field['type'] ) {
			case 'checkbox':
				/*
				 * A companion hidden field guarantees the key is always posted, so
				 * an unchecked box submits an explicit 0 instead of vanishing and
				 * becoming indistinguishable from a partial payload. PHP keeps the
				 * last value for a repeated name, so checking the box still wins.
				 *
				 * do_settings_fields() already labels this control in the row
				 * header, so the switch carries no text of its own and only exists
				 * to give the slider a click target. A second copy of the label
				 * would compete with the header's for the same control.
				 */
				printf(
					'<input type="hidden" name="%1$s" value="0" />
					<label class="amortexa-switch">
						<input type="checkbox" id="%2$s" name="%1$s" value="1" %3$s />
						<span class="amortexa-switch__slider" aria-hidden="true"></span>
					</label>',
					esc_attr( $name ),
					esc_attr( $id ),
					checked( ! empty( $value ), true, false )
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
				echo '<div class="amortexa-settings__input-group">';
				printf( '<input type="number" id="%1$s" name="%2$s" value="%3$s"', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
				$this->print_input_attributes( $field );
				echo ' />';
				if ( ! empty( $field['suffix'] ) ) {
					printf( '<span class="amortexa-settings__suffix-badge">%s</span>', esc_html( $field['suffix'] ) );
				}
				echo '</div>';
				break;

			default:
				printf( '<input type="text" id="%1$s" name="%2$s" value="%3$s"', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
				$this->print_input_attributes( $field );
				echo ' />';
				break;
		}

		if ( ! empty( $field['description'] ) ) {
			printf( '<p class="description amortexa-settings__field-desc">%s</p>', esc_html( $field['description'] ) );
		}
	}

	/**
	 * Prints the extra DOM attributes a field definition asks for.
	 *
	 * Each attribute is escaped as it is printed rather than the finished
	 * string being escaped once at the call site: the definition supplies both
	 * the attribute names and their values, and escaping a joined string would
	 * mangle the quotes that make it valid markup.
	 *
	 * @param array<string,mixed> $field Field definition.
	 * @return void
	 */
	private function print_input_attributes( $field ) {
		foreach ( (array) ( $field['input'] ?? array() ) as $key => $value ) {
			echo ' ' . esc_attr( $key ) . '="' . esc_attr( (string) $value ) . '"';
		}
	}

	/**
	 * Returns the DOM id used for a field's input and label.
	 *
	 * @param string $key Option key.
	 * @return string Element id.
	 */
	private function get_field_id( $key ) {
		return 'amortexa-' . str_replace( '_', '-', $key );
	}

	/**
	 * Enqueues the admin stylesheet and script on this screen only.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'amortexa-admin-style',
			AMORTEXA_PLUGIN_URL . 'assets/admin/amortexa-admin.css',
			array(),
			AMORTEXA_VERSION
		);

		wp_enqueue_script(
			'amortexa-admin-script',
			AMORTEXA_PLUGIN_URL . 'assets/admin/amortexa-admin.js',
			array(),
			AMORTEXA_VERSION,
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
			case 'amortexa_currency':
				?>
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<circle cx="12" cy="12" r="9"></circle>
					<path d="M14.5 9a2.5 2.5 0 0 0-5 0v6a2.5 2.5 0 0 0 5 0"></path>
					<path d="M8 12h8"></path>
				</svg>
				<?php
				break;

			case 'amortexa_loan':
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

			case 'amortexa_appearance':
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

			case 'amortexa_shortcode':
				?>
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<polyline points="16 18 22 12 16 6"></polyline>
					<polyline points="8 6 2 12 8 18"></polyline>
				</svg>
				<?php
				break;

			case 'amortexa_api':
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
		<div class="wrap amortexa-settings">
			<div class="amortexa-settings__frame">
				<div class="amortexa-settings__header">
					<div class="amortexa-settings__header-brand">
						<div class="amortexa-settings__logo-badge">
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
							<div class="amortexa-settings__title-row">
								<h1 class="amortexa-settings__title"><?php echo esc_html( get_admin_page_title() ); ?></h1>
								<span class="amortexa-settings__version-badge"><?php echo esc_html( 'v' . AMORTEXA_VERSION ); ?></span>
							</div>
							<p class="amortexa-settings__intro">
								<?php esc_html_e( 'These values are the site-wide defaults. Every new Mortgage Calculator block starts with them, and each block can then be adjusted on its own without affecting the others.', 'amortexa-mortgage-calculator' ); ?>
							</p>
						</div>
					</div>
					<div class="amortexa-settings__status-pills">
						<span class="amortexa-pill amortexa-pill--success">
							<span class="amortexa-pill__dot"></span>
							<?php esc_html_e( 'Block Active', 'amortexa-mortgage-calculator' ); ?>
						</span>
						<span class="amortexa-pill amortexa-pill--info">
							<span class="amortexa-pill__dot"></span>
							<?php esc_html_e( 'REST API Ready', 'amortexa-mortgage-calculator' ); ?>
						</span>
					</div>
				</div>

				<div class="amortexa-settings__layout">
					<div class="amortexa-settings__main">
						<?php
						$tabs     = $this->get_tabs();
						$sections = $this->get_sections();
						?>

						<!-- Tab navigation -->
						<div class="amortexa-settings__tabs" role="tablist">
							<?php
							/*
							 * The first tab and its panel are marked active here rather
							 * than in the script, so the page still shows a usable
							 * settings form if the script fails to load. Without it every
							 * panel would stay hidden and a Save would post nothing.
							 */
							$first_tab = true;
							foreach ( $tabs as $tab_id => $tab ) :
								?>
								<button
									type="button"
									id="<?php echo esc_attr( self::TAB_ID_PREFIX . $tab_id ); ?>"
									class="amortexa-settings__tab-btn"
									role="tab"
									data-tab="<?php echo esc_attr( $tab_id ); ?>"
									aria-controls="<?php echo esc_attr( $tab_id ); ?>"
									aria-selected="<?php echo $first_tab ? 'true' : 'false'; ?>"
									tabindex="<?php echo $first_tab ? '0' : '-1'; ?>"
								>
									<span class="amortexa-settings__tab-icon amortexa-settings__tab-icon--<?php echo esc_attr( $tab['icon'] ); ?>">
										<?php $this->render_section_icon( $tab_id ); ?>
									</span>
									<?php echo esc_html( $tab['title'] ); ?>
								</button>
								<?php
								$first_tab = false;
							endforeach;
							?>
						</div>

						<!-- Tab panels -->
						<div class="amortexa-settings__tab-panels">
							<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
								<?php settings_fields( self::OPTION_GROUP ); ?>

								<?php
								$first_panel = true;
								foreach ( $sections as $section_id => $section ) :
									?>
									<div
										class="amortexa-settings__tab-panel"
										id="<?php echo esc_attr( $section_id ); ?>"
										role="tabpanel"
										aria-labelledby="<?php echo esc_attr( self::TAB_ID_PREFIX . $section_id ); ?>"
										aria-hidden="<?php echo $first_panel ? 'false' : 'true'; ?>"
									>
										<div class="amortexa-settings__panel-header">
											<p class="amortexa-settings__panel-description"><?php echo esc_html( $section['description'] ); ?></p>
										</div>
										<table class="form-table" role="presentation">
											<?php do_settings_fields( self::PAGE_SLUG, $section_id ); ?>
										</table>
									</div>
									<?php
										$first_panel = false;
									endforeach;
								?>

									<div class="amortexa-settings__save-bar">
									<?php submit_button( __( 'Save Changes', 'amortexa-mortgage-calculator' ), 'primary', 'submit', false ); ?>
									<span class="amortexa-settings__save-note">
										<?php esc_html_e( 'Saved defaults immediately apply to all newly inserted calculators.', 'amortexa-mortgage-calculator' ); ?>
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
									class="amortexa-settings__tab-panel"
									id="<?php echo esc_attr( $ref_id ); ?>"
									role="tabpanel"
									aria-labelledby="<?php echo esc_attr( self::TAB_ID_PREFIX . $ref_id ); ?>"
									aria-hidden="true"
								>
									<div class="amortexa-settings__panel-header">
										<p class="amortexa-settings__panel-description"><?php echo esc_html( $ref['description'] ); ?></p>
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
		</div>
		<?php
	}

	/**
	 * Renders a button that copies the given text to the clipboard.
	 *
	 * Shared by every reference tab so the icon markup lives in one place.
	 *
	 * @param string $text Text to place on the clipboard. Escaped with esc_attr(),
	 *                    which keeps quotes and newlines valid in the attribute.
	 */
	private function render_copy_button( $text ) {
		?>
		<button type="button" class="amortexa-copy-btn" data-clipboard-text="<?php echo esc_attr( $text ); ?>" title="<?php esc_attr_e( 'Copy to clipboard', 'amortexa-mortgage-calculator' ); ?>">
			<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
				<rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
				<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
			</svg>
			<span class="amortexa-copy-btn__text"><?php esc_html_e( 'Copy', 'amortexa-mortgage-calculator' ); ?></span>
		</button>
		<?php
	}

	/**
	 * Renders the shortcode builder shown on the Shortcode tab.
	 *
	 * Every documented attribute gets a live input in the same layout as the
	 * Developer API parameters, so editing one rebuilds a copyable shortcode
	 * instead of a hand-written example.
	 */
	private function render_shortcode_reference() {
		$plain    = '[amortexa-mortgage-calculator]';
		$defaults = amortexa_get_default_attributes();

		$controls = array(
			'loanamount'       => array(
				'kind'  => 'number',
				'value' => $defaults['loanAmount'],
				'min'   => 0,
			),
			'downpayment'      => array(
				'kind'  => 'number',
				'value' => $defaults['downPayment'],
				'min'   => 0,
			),
			'interestrate'     => array(
				'kind'  => 'number',
				'value' => $defaults['interestRate'],
				'min'   => 0,
				'max'   => 100,
			),
			'loanterm'         => array(
				'kind'    => 'number',
				'value'   => $defaults['loanTerm'],
				'min'     => 1,
				'max'     => 60,
				'integer' => true,
			),
			'currencysymbol'   => array(
				'kind'  => 'text',
				'value' => $defaults['currencySymbol'],
			),
			'currencyposition' => array(
				'kind'    => 'select',
				'options' => amortexa_get_currency_positions(),
				'value'   => $defaults['currencyPosition'],
			),
			'showcharts'       => array(
				'kind' => 'boolean',
			),
			'charttype'        => array(
				'kind'    => 'select',
				'options' => amortexa_get_chart_types(),
				'value'   => $defaults['chartType'],
			),
			'formcolumns'      => array(
				'kind'    => 'select',
				'options' => amortexa_get_form_columns(),
				'value'   => $defaults['formColumns'],
			),
			'panelorder'       => array(
				'kind'  => 'text',
				'value' => implode( ',', $defaults['panelOrder'] ),
			),
			'layout'           => array(
				'kind'    => 'select',
				'options' => amortexa_get_layouts(),
				'value'   => $defaults['layout'],
			),
			'theme'            => array(
				'kind'    => 'select',
				'options' => amortexa_get_skins(),
				'value'   => $defaults['theme'],
			),
			'showamortization' => array(
				'kind' => 'boolean',
			),
			'showsliders'      => array(
				'kind' => 'boolean',
			),
			'showresults'      => array(
				'kind' => 'boolean',
			),
		);

		$attrs = Amortexa_Shortcode::get_documented_attributes();
		?>
		<div class="amortexa-settings__reference" data-amortexa-shortcode>
			<p class="amortexa-settings__card-text">
				<?php esc_html_e( 'Paste the calculator into any post, page, or widget area:', 'amortexa-mortgage-calculator' ); ?>
			</p>
			<div class="amortexa-settings__code-box">
				<code class="amortexa-settings__code"><?php echo esc_html( $plain ); ?></code>
				<?php $this->render_copy_button( $plain ); ?>
			</div>

			<h3 class="amortexa-settings__subheading">
				<?php esc_html_e( 'Attributes accept the values below', 'amortexa-mortgage-calculator' ); ?>
			</h3>
			<p class="amortexa-settings__card-text">
				<?php esc_html_e( 'Attribute names are lowercase and values are wrapped in quotes. Change any field to rebuild the shortcode; clear a field to leave the attribute out and use the site default instead.', 'amortexa-mortgage-calculator' ); ?>
			</p>

			<div class="amortexa-settings__api-grid">
				<?php foreach ( $attrs as $attr => $spec ) : ?>
					<?php
					/*
					 * Any attribute without a bespoke control falls back to a text box
					 * that starts empty, which is what tells the shortcode builder to
					 * leave that attribute out and defer to the site default. The
					 * `value` key therefore has to exist even though it is blank, or
					 * the field below reads an undefined index.
					 */
					$control  = isset( $controls[ $attr ] )
						? $controls[ $attr ]
						: array(
							'kind'  => 'text',
							'value' => '',
						);
					$field_id = 'amortexa-shortcode-' . $attr;
					?>
					<div class="amortexa-settings__api-field">
						<label class="amortexa-settings__api-label" for="<?php echo esc_attr( $field_id ); ?>">
							<code class="amortexa-settings__code"><?php echo esc_html( $attr ); ?></code>
							<span class="amortexa-settings__attr-type"><?php echo esc_html( $spec['type'] ); ?></span>
						</label>

						<?php if ( 'boolean' === $control['kind'] ) : ?>
							<select
								class="amortexa-settings__api-input"
								id="<?php echo esc_attr( $field_id ); ?>"
								data-shortcode-param="<?php echo esc_attr( $attr ); ?>"
							>
								<option value=""><?php esc_html_e( 'default', 'amortexa-mortgage-calculator' ); ?></option>
								<option value="true"><?php esc_html_e( 'true', 'amortexa-mortgage-calculator' ); ?></option>
								<option value="false"><?php esc_html_e( 'false', 'amortexa-mortgage-calculator' ); ?></option>
							</select>
						<?php elseif ( 'select' === $control['kind'] ) : ?>
							<select
								class="amortexa-settings__api-input"
								id="<?php echo esc_attr( $field_id ); ?>"
								data-shortcode-param="<?php echo esc_attr( $attr ); ?>"
							>
								<option value=""><?php esc_html_e( 'default', 'amortexa-mortgage-calculator' ); ?></option>
								<?php foreach ( $control['options'] as $option_value => $option_label ) : ?>
									<option
										value="<?php echo esc_attr( (string) $option_value ); ?>"
										<?php selected( (string) $control['value'], (string) $option_value ); ?>
									><?php echo esc_html( $option_label ); ?></option>
								<?php endforeach; ?>
							</select>
						<?php else : ?>
							<input
								type="<?php echo 'number' === $control['kind'] ? 'number' : 'text'; ?>"
								class="amortexa-settings__api-input"
								id="<?php echo esc_attr( $field_id ); ?>"
								data-shortcode-param="<?php echo esc_attr( $attr ); ?>"
								<?php echo ! empty( $control['integer'] ) ? 'data-shortcode-integer="true" ' : ''; ?>
								value="<?php echo esc_attr( (string) $control['value'] ); ?>"
								<?php
								if ( isset( $control['min'] ) ) {
									echo 'min="' . esc_attr( (string) $control['min'] ) . '" '; }
								?>
								<?php
								if ( isset( $control['max'] ) ) {
									echo 'max="' . esc_attr( (string) $control['max'] ) . '" '; }
								?>
								<?php echo 'number' === $control['kind'] ? 'step="any"' : ''; ?>
							/>
						<?php endif; ?>

						<p class="amortexa-settings__api-hint"><?php echo esc_html( $spec['type'] ); ?> &mdash; <?php echo esc_html( $spec['description'] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>

			<h3 class="amortexa-settings__subheading">
				<?php esc_html_e( 'Sample shortcode', 'amortexa-mortgage-calculator' ); ?>
			</h3>
			<div class="amortexa-settings__code-box">
				<code class="amortexa-settings__code amortexa-settings__code--pre" data-shortcode-request></code>
				<?php $this->render_copy_button( '' ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the REST endpoint reference shown on the Developer API tab.
	 *
	 * The parameters double as inputs: editing them rebuilds a sample request,
	 * which is more useful than a canned request and a response that only ever
	 * describes one fixed loan.
	 */
	private function render_api_reference() {
		$endpoint = rest_url( Amortexa_REST::NAMESPACE_V1 . '/calculate' );
		$params   = array(
			'amount'        => array(
				'type'    => __( 'number', 'amortexa-mortgage-calculator' ),
				'meta'    => __( 'required', 'amortexa-mortgage-calculator' ),
				'summary' => __( 'Total amount being financed.', 'amortexa-mortgage-calculator' ),
				'input'   => array(
					'value' => 350000,
					'min'   => 0,
				),
			),
			'down_payment'  => array(
				'type'    => __( 'number', 'amortexa-mortgage-calculator' ),
				'meta'    => __( 'optional, default 0', 'amortexa-mortgage-calculator' ),
				'summary' => __( 'Paid up front. Interest is charged on the remainder.', 'amortexa-mortgage-calculator' ),
				'input'   => array(
					'value' => 0,
					'min'   => 0,
				),
			),
			'interest_rate' => array(
				'type'    => __( 'number', 'amortexa-mortgage-calculator' ),
				'meta'    => __( 'optional, default 0', 'amortexa-mortgage-calculator' ),
				'summary' => __( 'Annual rate as a percentage, up to 100.', 'amortexa-mortgage-calculator' ),
				'input'   => array(
					'value' => 0,
					'min'   => 0,
					'max'   => 100,
				),
			),
			'term_years'    => array(
				'type'    => __( 'integer', 'amortexa-mortgage-calculator' ),
				'meta'    => __( 'optional, default 30', 'amortexa-mortgage-calculator' ),
				'summary' => __( 'Length of the loan in years, from 1 to 60.', 'amortexa-mortgage-calculator' ),
				'input'   => array(
					'value'   => 30,
					'min'     => 1,
					'max'     => 60,
					'integer' => true,
				),
			),
			'with_schedule' => array(
				'type'    => __( 'boolean', 'amortexa-mortgage-calculator' ),
				'meta'    => __( 'optional, default false', 'amortexa-mortgage-calculator' ),
				'summary' => __( 'Return the year-by-year amortization schedule alongside the totals.', 'amortexa-mortgage-calculator' ),
				'input'   => array(
					'checkbox' => true,
				),
			),
		);

		$endpoint_label = 'POST ' . $endpoint;
		?>
		<div class="amortexa-settings__reference">
			<p class="amortexa-settings__card-text">
				<?php esc_html_e( 'A stateless endpoint that runs the same arithmetic as the calculator. It stores nothing and returns no private data, so it needs no authentication or nonce.', 'amortexa-mortgage-calculator' ); ?>
			</p>
			<div class="amortexa-settings__code-box">
				<code class="amortexa-settings__code"><?php echo esc_html( $endpoint_label ); ?></code>
				<?php $this->render_copy_button( $endpoint_label ); ?>
			</div>

			<h3 class="amortexa-settings__subheading">
				<?php esc_html_e( 'Parameters', 'amortexa-mortgage-calculator' ); ?>
			</h3>
			<p class="amortexa-settings__card-text">
				<?php esc_html_e( 'Change any value to rebuild the sample request below.', 'amortexa-mortgage-calculator' ); ?>
			</p>

			<div class="amortexa-settings__api" data-amortexa-api data-endpoint="<?php echo esc_attr( $endpoint ); ?>">
				<div class="amortexa-settings__api-grid">
					<?php foreach ( $params as $param => $spec ) : ?>
						<?php
						$field_id = 'amortexa-api-' . str_replace( '_', '-', $param );
						$is_check = ! empty( $spec['input']['checkbox'] );
						$is_int   = ! empty( $spec['input']['integer'] );
						?>
						<div class="amortexa-settings__api-field">
							<label class="amortexa-settings__api-label" for="<?php echo esc_attr( $field_id ); ?>">
								<code class="amortexa-settings__code"><?php echo esc_html( $param ); ?></code>
								<span class="amortexa-settings__attr-type"><?php echo esc_html( $spec['type'] ); ?></span>
							</label>

							<?php if ( $is_check ) : ?>
								<span class="amortexa-settings__api-check">
									<input
										type="checkbox"
										id="<?php echo esc_attr( $field_id ); ?>"
										data-api-param="<?php echo esc_attr( $param ); ?>"
										data-api-boolean="true"
									/>
									<span><?php esc_html_e( 'Include the schedule', 'amortexa-mortgage-calculator' ); ?></span>
								</span>
							<?php else : ?>
								<input
									type="number"
									id="<?php echo esc_attr( $field_id ); ?>"
									class="amortexa-settings__api-input"
									data-api-param="<?php echo esc_attr( $param ); ?>"
									<?php echo $is_int ? 'data-api-integer="true" ' : ''; ?>
									value="<?php echo esc_attr( (string) $spec['input']['value'] ); ?>"
									<?php
									if ( isset( $spec['input']['min'] ) ) {
										echo 'min="' . esc_attr( (string) $spec['input']['min'] ) . '" '; }
									?>
									<?php
									if ( isset( $spec['input']['max'] ) ) {
										echo 'max="' . esc_attr( (string) $spec['input']['max'] ) . '" '; }
									?>
									step="any"
								/>
							<?php endif; ?>

							<p class="amortexa-settings__api-hint">
								<?php echo esc_html( $spec['meta'] ); ?> &mdash; <?php echo esc_html( $spec['summary'] ); ?>
							</p>
						</div>
					<?php endforeach; ?>
				</div>

				<h3 class="amortexa-settings__subheading">
					<?php esc_html_e( 'Sample request', 'amortexa-mortgage-calculator' ); ?>
				</h3>
				<div class="amortexa-settings__code-box">
					<code class="amortexa-settings__code amortexa-settings__code--pre" data-api-request></code>
					<?php $this->render_copy_button( '' ); ?>
				</div>
				<p class="amortexa-settings__api-warning" hidden>
					<?php esc_html_e( 'Enter a loan amount to build a request. It is the only required parameter.', 'amortexa-mortgage-calculator' ); ?>
				</p>
			</div>

			<p class="amortexa-settings__card-text">
				<?php esc_html_e( 'The response carries principal, monthly_payment, total_paid, total_interest, months, and schedule. The schedule stays empty unless with_schedule is passed. It runs the same calculations as the block, so the amortexa_calculation_result filter applies to responses too.', 'amortexa-mortgage-calculator' ); ?>
			</p>
			<p class="amortexa-settings__card-text">
				<?php
				printf(
					/* translators: %d: Requests allowed per minute. */
					esc_html__( 'Calls are rate limited to %d per minute per client address, after which the endpoint answers 429 with a Retry-After header. Change that with the amortexa_rest_calculate_rate_limit and amortexa_rest_calculate_rate_window filters, or return 0 from the first to switch throttling off. Buckets use the remote address alone, so behind a proxy or CDN supply a trusted client address through the amortexa_rate_limit_client_key filter. Return false from amortexa_rest_calculate_allowed to require authentication instead.', 'amortexa-mortgage-calculator' ),
					(int) AMORTEXA_RATE_LIMIT_MAX
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Renders the help and developer reference sidebar.
	 */
	private function render_sidebar() {
		?>
		<aside class="amortexa-settings__sidebar">
			<div class="amortexa-settings__card amortexa-settings__card--help">
				<div class="amortexa-settings__card-header">
					<span class="amortexa-settings__card-badge-icon amortexa-settings__card-badge-icon--amber">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<circle cx="12" cy="12" r="10"></circle>
							<line x1="12" y1="16" x2="12" y2="12"></line>
							<line x1="12" y1="8" x2="12.01" y2="8"></line>
						</svg>
					</span>
					<h2 class="amortexa-settings__card-title">
						<?php esc_html_e( 'Help', 'amortexa-mortgage-calculator' ); ?>
					</h2>
				</div>
				<ul class="amortexa-settings__tips-list">
					<li>
						<?php esc_html_e( 'Insert the block from the block inserter and search for Mortgage Calculator. Each block keeps its own values, so changing the defaults here only affects calculators inserted from now on.', 'amortexa-mortgage-calculator' ); ?>
					</li>
					<li>
						<?php esc_html_e( 'Interest is charged on the financed principal only, which is the loan amount minus the down payment. Charts and the amortization table can be switched off per block from the block sidebar.', 'amortexa-mortgage-calculator' ); ?>
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
		$sanitized = amortexa_sanitize_settings( $input );

		/**
		 * Fires after the admin settings have been sanitized and saved.
		 *
		 * @param array<string,mixed> $sanitized The settings about to be persisted.
		 */
		do_action( 'amortexa_settings_saved', $sanitized );

		return $sanitized;
	}
}
