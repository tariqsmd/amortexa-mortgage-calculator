/**
 * The Design tab of the block inspector.
 *
 * Every control here is generated from the token schema that PHP hands to the
 * editor, so adding a token to includes/design-tokens.php adds its control here
 * with no further work, and the inspector, the server-side sanitizer, and the
 * rendered markup can never disagree about what a token means.
 *
 * An unset token is simply absent from the design object, which is what makes a
 * control inherit the active skin: clearing it removes the inline custom
 * property and the stylesheet value comes back. Tokens that replaced one of the
 * older per-block colour attributes also clear that attribute on reset, so a
 * reset reads as a reset instead of revealing the older value again.
 */

import {
	BaseControl,
	Button,
	ColorPalette,
	PanelBody,
	PanelRow,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { getColorSwatches } from '../utils/editor-data';
import {
	getDesignGroups,
	getDesignTokenMap,
	parseSpacing,
	resolveDesignValues,
	serializeSpacing,
} from '../utils/design';

/**
 * A small label/value reset affordance shown whenever a token is set.
 *
 * @param {Object}   props         Component props.
 * @param {boolean}  props.isSet   Whether a value is currently applied.
 * @param {Function} props.onReset Called when the user asks to clear it.
 * @return {JSX.Element|null} Reset row, or null when the token is unset.
 */
function ResetRow( { isSet, onReset } ) {
	if ( ! isSet ) {
		return null;
	}

	return (
		<PanelRow>
			<Button
				variant="link"
				onClick={ onReset }
				style={ { alignSelf: 'flex-start' } }
			>
				{ __( 'Reset', 'amortexa-mortgage-calculator' ) }
			</Button>
		</PanelRow>
	);
}

/**
 * Renders the uniform value, or the four per-side inputs when expanded.
 *
 * One token and one custom property serve both cases: the uniform case stores a
 * single number, the per-side case stores a CSS shorthand. That is why the
 * expand toggle changes the shape of the stored value rather than adding more
 * tokens to the schema. The toggle itself lives once per group, at the bottom of
 * the panel, rather than being repeated under every spacing control.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.token    Token definition.
 * @param {string}   props.value    Resolved stored value.
 * @param {boolean}  props.expanded Whether the per-side inputs are showing.
 * @param {Function} props.onChange Receives the new stored value.
 * @return {JSX.Element} Spacing control.
 */
function SpacingControl( { token, value, expanded, onChange } ) {
	const sides = parseSpacing( value );
	const min = Number.isFinite( token.min ) ? token.min : 0;
	const max = Number.isFinite( token.max ) ? token.max : 120;
	const labels = [
		__( 'Top', 'amortexa-mortgage-calculator' ),
		__( 'Right', 'amortexa-mortgage-calculator' ),
		__( 'Bottom', 'amortexa-mortgage-calculator' ),
		__( 'Left', 'amortexa-mortgage-calculator' ),
	];

	if ( ! expanded ) {
		return (
			<RangeControl
				label={ token.label }
				value={ sides.length ? sides[ 0 ] : undefined }
				min={ min }
				max={ max }
				step={ token.step || 1 }
				onChange={ ( next ) =>
					onChange(
						'' === next || undefined === next ? '' : String( next )
					)
				}
				__nextHasNoMarginBottom
			/>
		);
	}

	// A uniform value expands to the same number on all four sides.
	const current = [ 0, 1, 2, 3 ].map(
		( index ) =>
			sides[ Math.min( index, sides.length - 1 ) ] ?? sides[ 0 ] ?? 0
	);

	return (
		<>
			{ [ 0, 1, 2, 3 ].map( ( index ) => (
				<TextControl
					key={ labels[ index ] }
					label={ labels[ index ] }
					type="number"
					min={ min }
					max={ max }
					value={ current[ index ] }
					onChange={ ( next ) => {
						const updated = current.slice();
						updated[ index ] = Number( next ) || 0;
						onChange( serializeSpacing( updated ) );
					} }
					__nextHasNoMarginBottom
				/>
			) ) }
		</>
	);
}

/**
 * Renders one token's control, chosen by its type.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.token    Token definition.
 * @param {string}   props.value    Resolved stored value.
 * @param {boolean}  props.expanded Per-side expansion for spacing tokens.
 * @param {Function} props.onChange Receives the new stored value.
 * @return {JSX.Element} The control.
 */
function TokenControl( { token, value, expanded, onChange } ) {
	if ( 'color' === token.type ) {
		return (
			<BaseControl
				label={ token.label }
				id={ `amortexa-design-${ token.key }` }
				__nextHasNoMarginBottom
			>
				<ColorPalette
					colors={ getColorSwatches() }
					value={ value || undefined }
					onChange={ ( next ) => onChange( next || '' ) }
					__experimentalIsRenderedInSidebar
				/>
			</BaseControl>
		);
	}

	if ( 'spacing' === token.type ) {
		return (
			<SpacingControl
				token={ token }
				value={ value }
				expanded={ expanded }
				onChange={ onChange }
			/>
		);
	}

	if ( 'select' === token.type ) {
		// Already ordered pairs from PHP, so the order is preserved exactly.
		const options = token.options || [];

		return (
			<SelectControl
				label={ token.label }
				value={ value || '' }
				options={ options }
				onChange={ ( next ) => onChange( next || '' ) }
				__nextHasNoMarginBottom
			/>
		);
	}

	return (
		<RangeControl
			label={ token.label }
			value={
				'' === value || undefined === value
					? undefined
					: Number( value )
			}
			min={ Number.isFinite( token.min ) ? token.min : 0 }
			max={ Number.isFinite( token.max ) ? token.max : 64 }
			step={ token.step || 1 }
			allowReset
			onChange={ ( next ) =>
				onChange(
					'' === next || undefined === next ? '' : String( next )
				)
			}
			__nextHasNoMarginBottom
		/>
	);
}

/**
 * Renders the Design tab.
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Current attribute values.
 * @param {Function} props.setAttributes Attribute updater.
 * @return {JSX.Element|null} The tab contents.
 */
export default function DesignControls( { attributes, setAttributes } ) {
	// Per group, so a sidebar full of spacing controls does not open four extra
	// panels on first visit.
	const [ expanded, setExpanded ] = useState( {} );

	const groups = getDesignGroups();

	if ( ! groups.length ) {
		return null;
	}

	const tokens = getDesignTokenMap();
	const values = resolveDesignValues( attributes );

	const setToken = ( token, value ) => {
		const design = { ...( attributes.design || {} ) };

		if ( '' === value || null === value || undefined === value ) {
			delete design[ token.key ];

			/*
			 * A token that replaced one of the older colour attributes is
			 * layered on top of it, so deleting only the token would make the
			 * old value reappear and the reset look like it did nothing.
			 */
			if ( token.legacy && attributes[ token.legacy ] ) {
				setAttributes( {
					design,
					[ token.legacy ]: '',
				} );

				return;
			}
		} else {
			design[ token.key ] = String( value );
		}

		setAttributes( { design } );
	};

	return (
		<>
			<PanelBody
				title={ __( 'Design', 'amortexa-mortgage-calculator' ) }
				initialOpen={ true }
			>
				<p
					style={ {
						marginTop: 0,
						color: '#757575',
						fontSize: 12,
					} }
				>
					{ __(
						'Anything left untouched follows the colour skin. Clearing a control returns that element to the skin value.',
						'amortexa-mortgage-calculator'
					) }
				</p>
			</PanelBody>

			{ groups.map( ( group ) => {
				const hasSpacing = ( group.tokens || [] ).some(
					( token ) => 'spacing' === token.type
				);
				const isExpanded = !! expanded[ group.key ];

				return (
					<PanelBody
						key={ group.key }
						title={ group.label }
						initialOpen={ false }
					>
						{ group.summary ? (
							<p
								style={ {
									marginTop: 0,
									color: '#757575',
									fontSize: 12,
								} }
							>
								{ group.summary }
							</p>
						) : null }

						{ ( group.tokens || [] ).map( ( token ) => {
							const key = token.key;

							return (
								<div key={ key }>
									<TokenControl
										token={ token }
										value={ values[ key ] || '' }
										expanded={ isExpanded }
										onChange={ ( value ) =>
											setToken(
												tokens[ key ] || token,
												value
											)
										}
									/>
									<ResetRow
										isSet={ !! values[ key ] }
										onReset={ () =>
											setToken(
												tokens[ key ] || token,
												''
											)
										}
									/>
								</div>
							);
						} ) }

						{ hasSpacing ? (
							<ToggleControl
								label={ __(
									'Set spacing per side',
									'amortexa-mortgage-calculator'
								) }
								checked={ isExpanded }
								onChange={ ( next ) =>
									setExpanded( ( state ) => ( {
										...state,
										[ group.key ]: next,
									} ) )
								}
								__nextHasNoMarginBottom
							/>
						) : null }
					</PanelBody>
				);
			} ) }
		</>
	);
}
