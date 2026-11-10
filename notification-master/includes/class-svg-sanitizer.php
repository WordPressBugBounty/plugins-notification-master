<?php
/**
 * Class Svg_Sanitizer
 *
 * @package notification-master
 *
 * @since 2.0.0
 */

namespace Notification_Master;

/**
 * Strict SVG allowlist: shapes, paint servers and same-document references only.
 */
class Svg_Sanitizer {

	/**
	 * Allowed elements and attributes for wp_kses().
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public static function allowed_html() {
		$core  = array(
			'id'        => true,
			'class'     => true,
			'transform' => true,
			'opacity'   => true,
		);
		$paint = array(
			'fill'              => true,
			'fill-rule'         => true,
			'fill-opacity'      => true,
			'clip-rule'         => true,
			'clip-path'         => true,
			'mask'              => true,
			'stroke'            => true,
			'stroke-width'      => true,
			'stroke-linecap'    => true,
			'stroke-linejoin'   => true,
			'stroke-miterlimit' => true,
			'stroke-dasharray'  => true,
			'stroke-dashoffset' => true,
			'stroke-opacity'    => true,
		);
		$shape = array_merge( $core, $paint );
		$grad  = array_merge(
			$core,
			array(
				'x1'                => true,
				'y1'                => true,
				'x2'                => true,
				'y2'                => true,
				'cx'                => true,
				'cy'                => true,
				'r'                 => true,
				'fx'                => true,
				'fy'                => true,
				'gradientunits'     => true,
				'gradienttransform' => true,
				'spreadmethod'      => true,
				'href'              => true,
				'xlink:href'        => true,
			)
		);

		return array(
			'svg'            => array_merge(
				$shape,
				array(
					'xmlns'               => true,
					'xmlns:xlink'         => true,
					'version'             => true,
					'width'               => true,
					'height'              => true,
					'viewbox'             => true,
					'preserveaspectratio' => true,
					'aria-hidden'         => true,
					'focusable'           => true,
					'role'                => true,
				)
			),
			'g'              => $shape,
			'defs'           => $core,
			'title'          => array(),
			'desc'           => array(),
			'path'           => array_merge( $shape, array( 'd' => true ) ),
			'circle'         => array_merge(
				$shape,
				array(
					'cx' => true,
					'cy' => true,
					'r'  => true,
				)
			),
			'ellipse'        => array_merge(
				$shape,
				array(
					'cx' => true,
					'cy' => true,
					'rx' => true,
					'ry' => true,
				)
			),
			'rect'           => array_merge(
				$shape,
				array(
					'x'      => true,
					'y'      => true,
					'width'  => true,
					'height' => true,
					'rx'     => true,
					'ry'     => true,
				)
			),
			'line'           => array_merge(
				$shape,
				array(
					'x1' => true,
					'y1' => true,
					'x2' => true,
					'y2' => true,
				)
			),
			'polyline'       => array_merge( $shape, array( 'points' => true ) ),
			'polygon'        => array_merge( $shape, array( 'points' => true ) ),
			'use'            => array_merge(
				$shape,
				array(
					'href'       => true,
					'xlink:href' => true,
					'x'          => true,
					'y'          => true,
					'width'      => true,
					'height'     => true,
				)
			),
			'lineargradient' => $grad,
			'radialgradient' => $grad,
			'stop'           => array_merge(
				$core,
				array(
					'offset'       => true,
					'stop-color'   => true,
					'stop-opacity' => true,
				)
			),
			'clippath'       => array_merge( $core, array( 'clippathunits' => true ) ),
			'mask'           => array_merge(
				$core,
				array(
					'maskunits' => true,
					'x'         => true,
					'y'         => true,
					'width'     => true,
					'height'    => true,
				)
			),
		);
	}

	/**
	 * Clean SVG markup; returns '' when nothing usable is left.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $svg Raw SVG markup.
	 *
	 * @return string
	 */
	public static function sanitize( $svg ) {
		if ( ! is_string( $svg ) || '' === trim( $svg ) ) {
			return '';
		}

		// Prolog, doctype (entities), comments, and dangerous elements with their content.
		$svg = (string) preg_replace(
			array(
				'/<\?.*?\?>/s',
				'/<!DOCTYPE(?:[^>\[]|\[[^\]]*\])*>/i',
				'/<!--.*?-->/s',
				'/<!\[CDATA\[.*?\]\]>/s',
				'#<(script|style|foreignobject|iframe|object|embed)\b.*?</\1\s*>#is',
			),
			'',
			$svg
		);

		$clean = wp_kses( $svg, self::allowed_html(), array( 'http', 'https' ) );

		// wp_kses lowercases attribute names; XML parsers (an <img> showing the file) need the camelCase ones back.
		$clean = self::restore_attribute_case( $clean );

		// Only same-document references: no external href, no external url().
		$clean = (string) preg_replace( '/\s(?:xlink:)?href\s*=\s*(["\'])(?!#)[^"\']*\1/i', '', $clean );
		$clean = (string) preg_replace( '/url\(\s*(?![\'"]?#)[^)]*\)/i', 'none', $clean );

		return false === stripos( $clean, '<svg' ) ? '' : trim( $clean );
	}

	/**
	 * Put back the camelCase names of SVG attributes that wp_kses lowercased.
	 *
	 * @since 2.0.0
	 *
	 * @param string $svg Sanitized markup.
	 *
	 * @return string
	 */
	private static function restore_attribute_case( $svg ) {
		$names = array(
			'viewbox'             => 'viewBox',
			'preserveaspectratio' => 'preserveAspectRatio',
			'gradientunits'       => 'gradientUnits',
			'gradienttransform'   => 'gradientTransform',
			'spreadmethod'        => 'spreadMethod',
			'clippathunits'       => 'clipPathUnits',
			'maskunits'           => 'maskUnits',
		);

		return (string) preg_replace_callback(
			'/(\s)(' . implode( '|', array_keys( $names ) ) . ')(\s*=)/',
			static function ( $m ) use ( $names ) {
				return $m[1] . $names[ $m[2] ] . $m[3];
			},
			$svg
		);
	}
}
