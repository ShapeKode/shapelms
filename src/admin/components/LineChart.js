/**
 * Small dependency-free SVG line chart for daily series.
 */
import { useState } from '@wordpress/element';

export default function LineChart( { series, height = 220 } ) {
	const [ hover, setHover ] = useState( null );
	const width = 640;
	const pad = { top: 16, right: 16, bottom: 28, left: 36 };
	const points = series[ 0 ]?.data || [];

	if ( ! points.length ) {
		return <p className="shapelms-muted">No data for this period.</p>;
	}

	const max = Math.max( 1, ...series.flatMap( ( s ) => s.data.map( ( p ) => p.value ) ) );
	const innerW = width - pad.left - pad.right;
	const innerH = height - pad.top - pad.bottom;
	const x = ( i ) => pad.left + ( points.length > 1 ? ( i / ( points.length - 1 ) ) * innerW : innerW / 2 );
	const y = ( v ) => pad.top + innerH - ( v / max ) * innerH;
	const ticks = [ 0, Math.ceil( max / 2 ), max ];
	const labelEvery = Math.max( 1, Math.ceil( points.length / 6 ) );

	const path = ( data ) => data.map( ( p, i ) => `${ i === 0 ? 'M' : 'L' }${ x( i ).toFixed( 1 ) } ${ y( p.value ).toFixed( 1 ) }` ).join( ' ' );

	return (
		<div className="shapelms-chart">
			<svg viewBox={ `0 0 ${ width } ${ height }` } role="img" aria-label={ series.map( ( s ) => s.label ).join( ', ' ) } onMouseLeave={ () => setHover( null ) }>
				{ ticks.map( ( t ) => (
					<g key={ t }>
						<line x1={ pad.left } x2={ width - pad.right } y1={ y( t ) } y2={ y( t ) } className="shapelms-chart__grid" />
						<text x={ pad.left - 8 } y={ y( t ) + 4 } textAnchor="end" className="shapelms-chart__tick">{ t }</text>
					</g>
				) ) }
				{ points.map( ( p, i ) => ( i % labelEvery === 0 ? (
					<text key={ p.date } x={ x( i ) } y={ height - 8 } textAnchor="middle" className="shapelms-chart__tick">{ p.date.slice( 5 ) }</text>
				) : null ) ) }
				{ series.map( ( s, si ) => (
					<path key={ si } d={ path( s.data ) } fill="none" stroke={ s.color || 'var(--shapelms-primary, #3b5bdb)' } strokeWidth="2" strokeLinejoin="round" />
				) ) }
				{ points.map( ( p, i ) => (
					<rect key={ p.date } x={ x( i ) - ( innerW / points.length ) / 2 } y={ pad.top } width={ innerW / points.length } height={ innerH } fill="transparent" onMouseEnter={ () => setHover( i ) } />
				) ) }
				{ hover !== null && (
					<g>
						<line x1={ x( hover ) } x2={ x( hover ) } y1={ pad.top } y2={ pad.top + innerH } className="shapelms-chart__cursor" />
						{ series.map( ( s, si ) => (
							<circle key={ si } cx={ x( hover ) } cy={ y( s.data[ hover ]?.value || 0 ) } r="4" fill={ s.color || 'var(--shapelms-primary, #3b5bdb)' } />
						) ) }
					</g>
				) }
			</svg>
			<div className="shapelms-chart__legend">
				{ series.map( ( s, si ) => (
					<span key={ si }>
						<i className="shapelms-chart__swatch" data-color={ s.color || '' } />
						{ s.label }
						{ hover !== null && <strong>: { s.data[ hover ]?.value ?? 0 }</strong> }
					</span>
				) ) }
				{ hover !== null && <span className="shapelms-muted">{ points[ hover ].date }</span> }
			</div>
		</div>
	);
}
