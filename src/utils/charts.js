/**
 * Dependency-free SVG chart builders shared by the editor preview and the
 * front-end view script.
 *
 * Every node is created with createElementNS/setAttribute and all labels are
 * plain strings supplied by callers (already-translated text or numbers), so
 * no markup injection is possible.
 */

const SVG_NS = 'http://www.w3.org/2000/svg';

/**
 * Creates a namespaced SVG element with attributes.
 *
 * @param {string} name  Element name (e.g., "circle").
 * @param {Object} attrs Attribute map applied verbatim.
 * @return {SVGElement} New SVG element.
 */
function svgEl( name, attrs ) {
	const node = document.createElementNS( SVG_NS, name );
	Object.entries( attrs || {} ).forEach( ( [ key, value ] ) =>
		node.setAttribute( key, String( value ) )
	);
	return node;
}

/**
 * Rounds a maximum up to a friendly axis bound (1/2/2.5/5 × 10^k).
 *
 * @param {number} value Raw maximum.
 * @return {number} Nice ceiling value.
 */
function niceCeil( value ) {
	if ( ! Number.isFinite( value ) || value <= 0 ) {
		return 1;
	}

	const exponent = Math.floor( Math.log10( value ) );
	const magnitude = Math.pow( 10, exponent );
	const fraction = value / magnitude;

	let nice;
	if ( fraction <= 1 ) {
		nice = 1;
	} else if ( fraction <= 2 ) {
		nice = 2;
	} else if ( fraction <= 2.5 ) {
		nice = 2.5;
	} else if ( fraction <= 5 ) {
		nice = 5;
	} else {
		nice = 10;
	}

	return nice * magnitude;
}

/**
 * Builds a donut (pie) chart showing the composition of total payments.
 *
 * @param {Array<{value: number, color: string}>} items Segments; colors come
 *                                                      from the active skin palette.
 * @param {Object}                                opts  { size, thickness, centerTitle, centerValue }.
 * @return {SVGSVGElement} Rendered donut chart.
 */
export function createDonutChart( items, opts ) {
	const size = opts.size || 180;
	const thickness = opts.thickness || 24;
	const center = size / 2;
	const radius = ( size - thickness ) / 2 - 2;
	const circumference = 2 * Math.PI * radius;

	const svg = svgEl( 'svg', {
		viewBox: `0 0 ${ size } ${ size }`,
		role: 'img',
		class: 'calcforge-chart calcforge-chart--donut',
	} );

	const group = svgEl( 'g', {
		transform: `rotate( -90 ${ center } ${ center } )`,
	} );

	const total = items.reduce(
		( sum, item ) => sum + Math.max( item.value, 0 ),
		0
	);
	let offset = 0;

	items.forEach( ( item ) => {
		const fraction = total > 0 ? Math.max( item.value, 0 ) / total : 0;
		if ( fraction <= 0 ) {
			return;
		}

		const length = Math.max( fraction * circumference - 2, 0 );

		group.appendChild(
			svgEl( 'circle', {
				cx: center,
				cy: center,
				r: radius,
				fill: 'none',
				stroke: item.color,
				'stroke-width': thickness,
				'stroke-dasharray': `${ length } ${ circumference - length }`,
				'stroke-dashoffset': -offset,
			} )
		);

		offset += fraction * circumference;
	} );

	svg.appendChild( group );

	if ( opts.centerTitle ) {
		const title = svgEl( 'text', {
			x: center,
			y: center - 6,
			'text-anchor': 'middle',
			class: 'calcforge-chart__center-title',
		} );
		title.textContent = opts.centerTitle;
		svg.appendChild( title );
	}

	if ( opts.centerValue ) {
		const value = svgEl( 'text', {
			x: center,
			y: center + 14,
			'text-anchor': 'middle',
			class: 'calcforge-chart__center-value',
		} );
		value.textContent = opts.centerValue;
		svg.appendChild( value );
	}

	return svg;
}

/**
 * Builds a multi-series line/area chart over yearly data points.
 *
 * @param {Array<{points: Array<number>, color: string, area?: boolean}>} series
 *                                                                               One entry per line; points are indexed per year starting at year 1.
 * @param {Object}                                                        opts   { width, height, pad, xLabels: Array<{at, text}>, formatY }.
 * @return {SVGSVGElement} Rendered line chart.
 */
export function createLineChart( series, opts ) {
	const width = opts.width || 520;
	const height = opts.height || 260;
	const pad = {
		top: 18,
		right: 16,
		bottom: 30,
		left: 56,
		...( opts.pad || {} ),
	};

	const count = Math.max(
		...series.map( ( line ) => line.points.length ),
		2
	);

	const rawMax = Math.max( ...series.flatMap( ( line ) => line.points ), 1 );
	const yMax = niceCeil( rawMax );

	const innerW = width - pad.left - pad.right;
	const innerH = height - pad.top - pad.bottom;

	const xAt = ( index ) =>
		pad.left +
		( count > 1 ? ( index / ( count - 1 ) ) * innerW : innerW / 2 );
	const yAt = ( value ) => pad.top + innerH - ( value / yMax ) * innerH;

	const svg = svgEl( 'svg', {
		viewBox: `0 0 ${ width } ${ height }`,
		role: 'img',
		class: 'calcforge-chart calcforge-chart--line',
	} );

	for ( let tick = 0; tick <= 4; tick++ ) {
		const value = ( yMax / 4 ) * tick;
		const y = yAt( value );

		svg.appendChild(
			svgEl( 'line', {
				x1: pad.left,
				x2: width - pad.right,
				y1: y,
				y2: y,
				class: 'calcforge-chart__gridline',
			} )
		);

		const label = svgEl( 'text', {
			x: pad.left - 8,
			y: y + 3,
			'text-anchor': 'end',
			class: 'calcforge-chart__axis',
		} );
		label.textContent = opts.formatY
			? opts.formatY( value )
			: String( Math.round( value ) );
		svg.appendChild( label );
	}

	series.forEach( ( line ) => {
		if ( ! line.points.length ) {
			return;
		}

		const path = line.points
			.map(
				( value, index ) =>
					`${ index === 0 ? 'M' : 'L' } ${ xAt( index ).toFixed(
						1
					) } ${ yAt( value ).toFixed( 1 ) }`
			)
			.join( ' ' );

		if ( line.area ) {
			svg.appendChild(
				svgEl( 'path', {
					d: `${ path } L ${ xAt( line.points.length - 1 ).toFixed(
						1
					) } ${ ( pad.top + innerH ).toFixed(
						1
					) } L ${ pad.left.toFixed( 1 ) } ${ (
						pad.top + innerH
					).toFixed( 1 ) } Z`,
					fill: line.color,
					opacity: '0.12',
					stroke: 'none',
				} )
			);
		}

		svg.appendChild(
			svgEl( 'path', {
				d: path,
				fill: 'none',
				stroke: line.color,
				'stroke-width': 2.5,
				'stroke-linejoin': 'round',
				'stroke-linecap': 'round',
			} )
		);
	} );

	( opts.xLabels || [] ).forEach( ( tick ) => {
		if ( tick.at < 0 || tick.at > count - 1 ) {
			return;
		}

		const label = svgEl( 'text', {
			x: xAt( tick.at ),
			y: height - 8,
			'text-anchor': 'middle',
			class: 'calcforge-chart__axis',
		} );
		label.textContent = tick.text;
		svg.appendChild( label );
	} );

	return svg;
}

/**
 * Builds a grouped bar chart over the same yearly data points as the line chart.
 *
 * One group per year, one bar per series, so principal-versus-interest per year
 * reads the same way the line chart does. Bars are centred in their group and
 * share the axis and label conventions of createLineChart.
 *
 * @param {Array<{points: Array<number>, color: string, label?: string}>} series
 *                                                                               One entry per bar group; points are indexed per year starting at year 1.
 * @param {Object}                                                        opts   { width, height, pad, xLabels: Array<{at, text}>, formatY }.
 * @return {SVGSVGElement} Rendered bar chart.
 */
export function createBarChart( series, opts ) {
	const width = opts.width || 520;
	const height = opts.height || 260;
	const pad = {
		top: 18,
		right: 16,
		bottom: 30,
		left: 56,
		...( opts.pad || {} ),
	};

	const active = series.filter( ( group ) => group.points.length );
	const count = Math.max(
		...active.map( ( group ) => group.points.length ),
		2
	);

	const rawMax = Math.max(
		...active.flatMap( ( group ) => group.points ),
		1
	);
	const yMax = niceCeil( rawMax );

	const innerW = width - pad.left - pad.right;
	const innerH = height - pad.top - pad.bottom;
	const baseline = pad.top + innerH;

	const slot = innerW / count;
	const groupPad = Math.min( slot * 0.18, 10 );
	const barW = Math.max(
		( slot - groupPad * 2 ) / Math.max( active.length, 1 ),
		1
	);

	const svg = svgEl( 'svg', {
		viewBox: `0 0 ${ width } ${ height }`,
		role: 'img',
		class: 'calcforge-chart calcforge-chart--bar',
	} );

	for ( let tick = 0; tick <= 4; tick++ ) {
		const value = ( yMax / 4 ) * tick;
		const y = baseline - ( value / yMax ) * innerH;

		svg.appendChild(
			svgEl( 'line', {
				x1: pad.left,
				x2: width - pad.right,
				y1: y,
				y2: y,
				class: 'calcforge-chart__gridline',
			} )
		);

		const label = svgEl( 'text', {
			x: pad.left - 8,
			y: y + 3,
			'text-anchor': 'end',
			class: 'calcforge-chart__axis',
		} );
		label.textContent = opts.formatY
			? opts.formatY( value )
			: String( Math.round( value ) );
		svg.appendChild( label );
	}

	active.forEach( ( group, seriesIndex ) => {
		group.points.forEach( ( value, index ) => {
			if ( index >= count ) {
				return;
			}

			const barH = Math.max( ( value / yMax ) * innerH, 0 );
			const groupLeft = pad.left + slot * index;
			const x =
				groupLeft +
				groupPad +
				barW * seriesIndex +
				( slot - groupPad * 2 - barW * active.length ) / 2;

			svg.appendChild(
				svgEl( 'rect', {
					x: x.toFixed( 1 ),
					y: ( baseline - barH ).toFixed( 1 ),
					width: barW.toFixed( 1 ),
					height: barH.toFixed( 1 ),
					fill: group.color,
					rx: 2,
					class: 'calcforge-chart__bar',
				} )
			);
		} );
	} );

	( opts.xLabels || [] ).forEach( ( tick ) => {
		if ( tick.at < 0 || tick.at > count - 1 ) {
			return;
		}

		const label = svgEl( 'text', {
			x: pad.left + slot * tick.at + slot / 2,
			y: height - 8,
			'text-anchor': 'middle',
			class: 'calcforge-chart__axis',
		} );
		label.textContent = tick.text;
		svg.appendChild( label );
	} );

	return svg;
}
