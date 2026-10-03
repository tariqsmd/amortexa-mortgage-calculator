<?php
/**
 * Calculator widget.
 *
 * @package Amortexa
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the calculator as a classic widget.
 *
 * A widget is the one place a site owner can drop the calculator into a sidebar
 * or footer without touching content, and it keeps working in the classic
 * widgets screen as well as in the block based widget editor, where WordPress
 * offers any registered widget as a legacy widget block.
 *
 * The widget is a thin shell over the [amortexa-mortgage-calculator] shortcode rather than a second
 * rendering path. That is deliberate: the shortcode already funnels into
 * render_block(), so the widget inherits the block's attribute sanitization,
 * stylesheet, and view script, and it cannot drift from the block when a new
 * attribute is added. Every field is optional - a blank one leaves the attribute
 * out of the shortcode, so the site's own default applies.
 */
final class Amortexa_Widget extends WP_Widget {

	/**
	 * Widget id base.
	 */
	const ID_BASE = 'amortexa_calculator';

	/**
	 * Registers the widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
			self::ID_BASE,
			__( 'Mortgage Calculator', 'amortexa-mortgage-calculator' ),
			array(
				'classname'                   => 'amortexa-widget',
				'description'                 => __( 'Renders the mortgage calculator. Any field left blank follows the site default.', 'amortexa-mortgage-calculator' ),
				'customize_selective_refresh' => true,
			)
		);
	}

	/**
	 * Registers the widget with WordPress.
	 *
	 * Called on widgets_init, which is the only hook that exists for widgets.
	 */
	public static function register() {
		register_widget( __CLASS__ );
	}

	/**
	 * The optional overrides this widget exposes.
	 *
	 * The keys are the shortcode attribute names, so the instance can be turned
	 * into a shortcode without a translation table. The list is deliberately
	 * short: a sidebar is a poor home for fifteen inputs, and anything not
	 * offered here stays under the site's control.
	 *
	 * @return array<string,array<string,mixed>> Field schema.
	 */
	private static function get_fields() {
		$fields = array(
			'loanamount'   => array(
				'type'        => 'number',
				'label'       => __( 'Loan amount', 'amortexa-mortgage-calculator' ),
				'min'         => 0,
				'max'         => 999999999999,
				'description' => __( 'Blank follows the site default.', 'amortexa-mortgage-calculator' ),
			),
			'interestrate' => array(
				'type'        => 'number',
				'label'       => __( 'Interest rate', 'amortexa-mortgage-calculator' ),
				'min'         => 0,
				'max'         => 100,
				'description' => __( 'Percent per year. Blank follows the site default.', 'amortexa-mortgage-calculator' ),
			),
			'loanterm'     => array(
				'type'        => 'number',
				'label'       => __( 'Loan term', 'amortexa-mortgage-calculator' ),
				'min'         => 1,
				'max'         => 60,
				'integer'     => true,
				'description' => __( 'Years. Blank follows the site default.', 'amortexa-mortgage-calculator' ),
			),
			'layout'       => array(
				'type'        => 'select',
				'label'       => __( 'Layout', 'amortexa-mortgage-calculator' ),
				'options'     => 'amortexa_get_layouts',
				'description' => __( 'Blank follows the site default.', 'amortexa-mortgage-calculator' ),
			),
			'theme'        => array(
				'type'        => 'select',
				'label'       => __( 'Skin', 'amortexa-mortgage-calculator' ),
				'options'     => 'amortexa_get_skins',
				'description' => __( 'Blank follows the site default.', 'amortexa-mortgage-calculator' ),
			),
		);

		/**
		 * Filters the fields the calculator widget offers.
		 *
		 * @param array<string,array<string,mixed>> $fields Field schema.
		 */
		return apply_filters( 'amortexa_widget_fields', $fields );
	}

	/**
	 * Renders the calculator inside a widget area.
	 *
	 * @param array<string,string> $args     Sidebar arguments.
	 * @param array<string,mixed>  $instance Saved widget settings.
	 */
	public function widget( $args, $instance ) {
		$shortcode = $this->build_shortcode( $instance );

		// Nothing to render, so leave the sidebar untouched.
		if ( '' === $shortcode ) {
			return;
		}

		/*
		 * Core always supplies these four wrappers through
		 * wp_widgets_defaults(), but a theme or page builder can call the widget
		 * directly with a partial argument array. Falling back to an empty string
		 * renders the calculator unwrapped instead of raising a PHP warning.
		 */
		$before_widget = isset( $args['before_widget'] ) ? (string) $args['before_widget'] : '';
		$after_widget  = isset( $args['after_widget'] ) ? (string) $args['after_widget'] : '';
		$before_title  = isset( $args['before_title'] ) ? (string) $args['before_title'] : '';
		$after_title   = isset( $args['after_title'] ) ? (string) $args['after_title'] : '';

		echo $before_widget; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core sidebar markup.

		$title = isset( $instance['title'] ) ? trim( (string) $instance['title'] ) : '';

		if ( '' !== $title ) {
			echo $before_title . esc_html( $title ) . $after_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core sidebar markup.
		}

		echo do_shortcode( $shortcode ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The shortcode renders escaped block markup.

		echo $after_widget; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core sidebar markup.
	}

	/**
	 * Builds the shortcode for a saved widget instance.
	 *
	 * Values are wrapped in double quotes and a literal quote is swapped for a
	 * single one, because a double quote inside the value would otherwise end
	 * the attribute early in shortcode_parse_atts(). The numeric and enum fields
	 * cannot contain a quote at all, so this only guards future fields.
	 *
	 * @param array<string,mixed> $instance Saved widget settings.
	 * @return string Shortcode, or an empty string when nothing is overridden.
	 */
	private function build_shortcode( $instance ) {
		$attributes = array();

		foreach ( self::get_fields() as $key => $field ) {
			$value = isset( $instance[ $key ] ) ? (string) $instance[ $key ] : '';

			if ( '' === $value ) {
				continue;
			}

			$attributes[] = $key . '="' . str_replace( '"', "'", $value ) . '"';
		}

		return '[amortexa-mortgage-calculator' . ( $attributes ? ' ' . implode( ' ', $attributes ) : '' ) . ']';
	}

	/**
	 * Prints the widget form on the classic widgets screen.
	 *
	 * @param array<string,mixed> $instance Saved widget settings.
	 */
	public function form( $instance ) {
		/*
		 * WP_Widget::form() is a stub that prints "There are no options for this
		 * widget.", so the title field every widget is expected to have has to be
		 * rendered here.
		 */
		printf(
			'<p><label for="%1$s">%2$s</label><input class="widefat" id="%1$s" name="%3$s" type="text" value="%4$s" /></p>',
			esc_attr( $this->get_field_id( 'title' ) ),
			// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- "Title:" is a core string, so it is translated from the default domain.
			esc_html__( 'Title:', 'default' ),
			esc_attr( $this->get_field_name( 'title' ) ),
			esc_attr( isset( $instance['title'] ) ? (string) $instance['title'] : '' )
		);

		foreach ( self::get_fields() as $key => $field ) {
			$id    = $this->get_field_id( $key );
			$value = isset( $instance[ $key ] ) ? (string) $instance[ $key ] : '';

			printf(
				'<p class="amortexa-widget__field"><label for="%1$s">%2$s</label>',
				esc_attr( $id ),
				esc_html( $field['label'] )
			);

			if ( 'select' === $field['type'] ) {
				$options = call_user_func( $field['options'] );

				printf( '<select id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $this->get_field_name( $key ) ) );
				printf( '<option value="">%s</option>', esc_html__( 'Site default', 'amortexa-mortgage-calculator' ) );

				foreach ( $options as $option_value => $option_label ) {
					printf(
						'<option value="%1$s" %2$s>%3$s</option>',
						esc_attr( (string) $option_value ),
						selected( $value, (string) $option_value, false ),
						esc_html( $option_label )
					);
				}

				echo '</select>';
			} else {
				printf(
					'<input type="number" id="%1$s" name="%2$s" value="%3$s" min="%4$s" max="%5$s" step="%6$s" class="widefat" />',
					esc_attr( $id ),
					esc_attr( $this->get_field_name( $key ) ),
					esc_attr( $value ),
					esc_attr( (string) $field['min'] ),
					esc_attr( (string) $field['max'] ),
					esc_attr( ! empty( $field['integer'] ) ? '1' : 'any' )
				);
			}

			printf(
				'<small class="description">%s</small></p>',
				esc_html( $field['description'] )
			);
		}
	}

	/**
	 * Sanitizes a submitted widget form.
	 *
	 * @param array<string,mixed> $new_instance Submitted values.
	 * @param array<string,mixed> $old_instance Previously saved values.
	 * @return array<string,mixed> Values to store.
	 */
	public function update( $new_instance, $old_instance ) {
		/*
		 * The title is submitted by the form but is not one of the plugin's own
		 * fields, so it is carried through explicitly. Without this every save
		 * would blank the heading the site owner typed.
		 */
		$instance = array(
			'title' => isset( $new_instance['title'] ) && is_scalar( $new_instance['title'] )
				? sanitize_text_field( (string) $new_instance['title'] )
				: '',
		);

		foreach ( self::get_fields() as $key => $field ) {
			$raw = isset( $new_instance[ $key ] ) ? $new_instance[ $key ] : '';

			$instance[ $key ] = $this->sanitize_field( $raw, $field );
		}

		return $instance;
	}

	/**
	 * Sanitizes one widget field.
	 *
	 * @param mixed               $value Submitted value.
	 * @param array<string,mixed> $field Field schema.
	 * @return string Clean value, or an empty string to follow the site default.
	 */
	private function sanitize_field( $value, $field ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		if ( 'select' === $field['type'] ) {
			$options = call_user_func( $field['options'] );

			return array_key_exists( $value, $options ) ? $value : '';
		}

		$clamped = amortexa_clamp_float( $value, $field['min'], $field['max'] );

		if ( ! empty( $field['integer'] ) ) {
			return (string) (int) $clamped;
		}

		// Trailing zeros read better in a sidebar, so a whole number stays whole.
		return (string) ( floor( $clamped ) === $clamped ? (int) $clamped : $clamped );
	}
}
