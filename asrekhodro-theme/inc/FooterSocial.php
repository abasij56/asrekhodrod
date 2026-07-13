<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FooterSocial {

	/**
	 * @var array<string, array{url: string, svg: string, label: string}>
	 */
	private const LEGACY_NETWORKS = array(
		'instagram' => array(
			'url'   => 'social_instagram',
			'svg'   => 'social_instagram_svg',
			'label' => 'اینستاگرام',
		),
		'telegram'  => array(
			'url'   => 'social_telegram',
			'svg'   => 'social_telegram_svg',
			'label' => 'تلگرام',
		),
		'youtube'   => array(
			'url'   => 'social_youtube',
			'svg'   => 'social_youtube_svg',
			'label' => 'یوتیوب',
		),
		'linkedin'  => array(
			'url'   => 'social_linkedin',
			'svg'   => 'social_linkedin_svg',
			'label' => 'لینکدین',
		),
	);

	public static function init(): void {
		add_filter( 'acf/load_value/name=footer_social_links', array( self::class, 'load_repeater_value' ), 10, 3 );
	}

	/**
	 * @param mixed                $value
	 * @param string|int           $post_id
	 * @param array<string, mixed> $field
	 * @return mixed
	 */
	public static function load_repeater_value( $value, $post_id, array $field ) {
		if ( $post_id !== 'options' ) {
			return $value;
		}
		if ( is_array( $value ) && $value !== array() ) {
			return $value;
		}

		// Do not call get_fields() here — this runs during get_fields()/get_field()
		// and would recurse until Apache crashes (stack overflow).
		$options = array();
		foreach ( self::LEGACY_NETWORKS as $network ) {
			$url_key = 'options_' . $network['url'];
			$svg_key = 'options_' . $network['svg'];
			$options[ $network['url'] ] = (string) get_option( $url_key, '' );
			$options[ $network['svg'] ] = (string) get_option( $svg_key, '' );
		}

		$legacy = self::legacy_rows_from_options( $options );

		return $legacy !== array() ? $legacy : $value;
	}

	/**
	 * @param array<string, mixed> $options
	 * @return list<array<string, string>>
	 */
	public static function legacy_rows_from_options( array $options ): array {
		$rows = array();

		foreach ( self::LEGACY_NETWORKS as $id => $network ) {
			$url = trim( (string) ( $options[ $network['url'] ] ?? '' ) );
			if ( $url === '' ) {
				continue;
			}

			$custom_svg = trim( (string) ( $options[ $network['svg'] ] ?? '' ) );
			$svg        = $custom_svg !== '' ? $custom_svg : self::default_svg( $id );

			$rows[] = array(
				'social_title' => $network['label'],
				'social_url'   => $url,
				'social_svg'   => $svg,
			);
		}

		return $rows;
	}

	/**
	 * @param array<string, mixed> $options
	 * @return list<array{id: string, url: string, label: string, svg: string}>
	 */
	public static function links_for_footer( array $options ): array {
		$rows = $options['footer_social_links'] ?? null;
		if ( is_array( $rows ) && $rows !== array() ) {
			return self::links_from_repeater( $rows );
		}

		return self::links_from_legacy( $options );
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 * @return list<array{id: string, url: string, label: string, svg: string}>
	 */
	private static function links_from_repeater( array $rows ): array {
		$links = array();
		$index = 0;

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$url = trim( (string) ( $row['social_url'] ?? '' ) );
			if ( $url === '' ) {
				continue;
			}

			$title      = trim( (string) ( $row['social_title'] ?? '' ) );
			$custom_svg = trim( (string) ( $row['social_svg'] ?? '' ) );
			$svg        = self::sanitize_svg( $custom_svg );
			if ( $svg === '' ) {
				continue;
			}

			$links[] = array(
				'id'    => 'social-' . $index,
				'url'   => esc_url( $url ),
				'label' => $title !== '' ? $title : __( 'شبکه اجتماعی', 'asrekhodro' ),
				'svg'   => $svg,
			);
			++$index;
		}

		return $links;
	}

	/**
	 * @param array<string, mixed> $options
	 * @return list<array{id: string, url: string, label: string, svg: string}>
	 */
	private static function links_from_legacy( array $options ): array {
		$links = array();

		foreach ( self::LEGACY_NETWORKS as $id => $network ) {
			$url = trim( (string) ( $options[ $network['url'] ] ?? '' ) );
			if ( $url === '' ) {
				continue;
			}

			$custom_svg = trim( (string) ( $options[ $network['svg'] ] ?? '' ) );
			$svg        = self::sanitize_svg( $custom_svg !== '' ? $custom_svg : self::default_svg( $id ) );
			if ( $svg === '' ) {
				continue;
			}

			$links[] = array(
				'id'    => $id,
				'url'   => esc_url( $url ),
				'label' => $network['label'],
				'svg'   => $svg,
			);
		}

		return $links;
	}

	/**
	 * @param array<string, mixed> $options
	 */
	public static function url_for_label( array $options, string $needle ): string {
		$needle = mb_strtolower( trim( $needle ) );
		if ( $needle === '' ) {
			return '';
		}

		$rows = $options['footer_social_links'] ?? array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$title = mb_strtolower( trim( (string) ( $row['social_title'] ?? '' ) ) );
				$url   = trim( (string) ( $row['social_url'] ?? '' ) );
				if ( $url !== '' && ( $title === $needle || str_contains( $title, $needle ) ) ) {
					return esc_url( $url );
				}
			}
		}

		foreach ( self::LEGACY_NETWORKS as $id => $network ) {
			if ( $id !== $needle && ! str_contains( mb_strtolower( $network['label'] ), $needle ) ) {
				continue;
			}
			$url = trim( (string) ( $options[ $network['url'] ] ?? '' ) );
			if ( $url !== '' ) {
				return esc_url( $url );
			}
		}

		return '';
	}

	public static function default_svg( string $network ): string {
		switch ( $network ) {
			case 'instagram':
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/></svg>';
			case 'telegram':
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.831-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>';
			case 'youtube':
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>';
			case 'linkedin':
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.065 2.065 0 1 1-.001 4.13 2.065 2.065 0 0 1 .001-4.13zM6.756 20.452H3.555V9h3.201v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>';
			default:
				return '';
		}
	}

	/**
	 * @return array<string, string>
	 */
	public static function default_svgs_for_admin(): array {
		$defaults = array();
		foreach ( array_keys( self::LEGACY_NETWORKS ) as $network ) {
			$defaults[ $network ] = self::default_svg( $network );
		}

		return $defaults;
	}

	public static function sanitize_svg( string $svg ): string {
		$svg = trim( $svg );
		if ( $svg === '' ) {
			return '';
		}

		$allowed = array(
			'svg'    => array(
				'xmlns'       => true,
				'viewbox'     => true,
				'viewBox'     => true,
				'fill'        => true,
				'width'       => true,
				'height'      => true,
				'aria-hidden' => true,
				'role'        => true,
				'class'       => true,
				'focusable'   => true,
			),
			'path'   => array(
				'd'         => true,
				'fill'      => true,
				'fill-rule' => true,
				'clip-rule' => true,
			),
			'g'      => array(
				'fill' => true,
			),
			'circle' => array(
				'cx'   => true,
				'cy'   => true,
				'r'    => true,
				'fill' => true,
			),
			'rect'   => array(
				'x'      => true,
				'y'      => true,
				'width'  => true,
				'height' => true,
				'fill'   => true,
				'rx'     => true,
			),
		);

		$svg = wp_kses( $svg, $allowed );

		return self::normalize_svg_dimensions( $svg );
	}

	private static function normalize_svg_dimensions( string $svg ): string {
		if ( ! preg_match( '/<svg\b/i', $svg ) ) {
			return $svg;
		}

		if ( ! preg_match( '/\sviewBox=/i', $svg ) ) {
			$svg = preg_replace( '/<svg/i', '<svg viewBox="0 0 24 24"', $svg, 1 ) ?? $svg;
		}

		$normalized = preg_replace_callback(
			'/^(\s*<svg\b)([^>]*)(>)/is',
			static function ( array $matches ): string {
				$attrs = (string) preg_replace( '/\s(width|height)=["\'][^"\']*["\']/i', '', $matches[2] );
				return $matches[1] . $attrs . $matches[3];
			},
			$svg,
			1
		);

		return is_string( $normalized ) ? $normalized : $svg;
	}
}
