<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves the active theme appearance (classic, modern, …) and its view/asset paths.
 */
final class Appearance {

	public const DEFAULT_ID  = 'classic';
	public const FALLBACK_ID = 'classic';

	private static ?string $active_id = null;

	public static function init(): void {
		add_filter( 'body_class', array( self::class, 'filter_body_class' ) );
		add_action( 'after_setup_theme', array( self::class, 'refresh_timber_dirs' ), 20 );
	}

	public static function refresh_timber_dirs(): void {
		self::$active_id = null;
		\Timber\Timber::$dirname = self::timber_locations();
	}

	/**
	 * Safe Timber paths before ACF / options are available.
	 *
	 * @return array<string, list<string>>
	 */
	public static function bootstrap_timber_locations(): array {
		return array(
			\Timber\Loader::MAIN_NAMESPACE => array( 'appearances/classic/views', 'inc/blocks' ),
		);
	}

	/**
	 * @param array<int, string> $classes
	 * @return array<int, string>
	 */
	public static function filter_body_class( array $classes ): array {
		$classes[] = 'appearance-' . sanitize_html_class( self::id() );

		return $classes;
	}

	public static function id(): string {
		if ( self::$active_id === null ) {
			// Guard first: get_field()/ACF bootstrap can re-enter Appearance::id()
			// and overflow the stack (Apache exit 3221225725 / ERR_CONNECTION_RESET).
			self::$active_id = self::DEFAULT_ID;
			self::$active_id = self::detect_active_id();
		}

		return self::$active_id;
	}

	/**
	 * @return list<string>
	 */
	public static function registered_ids(): array {
		$base = ASREKHODRO_THEME_DIR . '/appearances';
		if ( ! is_dir( $base ) ) {
			return array( self::DEFAULT_ID );
		}

		$ids = array();
		foreach ( scandir( $base ) ?: array() as $entry ) {
			if ( $entry === '.' || $entry === '..' || $entry === 'shared' ) {
				continue;
			}

			$path = $base . '/' . $entry;
			if ( is_dir( $path ) && file_exists( $path . '/manifest.php' ) ) {
				$ids[] = $entry;
			}
		}

		sort( $ids );

		return $ids !== array() ? $ids : array( self::DEFAULT_ID );
	}

	/**
	 * @return array<string, string>
	 */
	public static function choices_for_acf(): array {
		$choices = array();

		foreach ( self::registered_ids() as $appearance_id ) {
			$manifest = self::load_manifest( $appearance_id );
			$id       = (string) ( $manifest['id'] ?? $appearance_id );
			$label    = (string) ( $manifest['label'] ?? ucfirst( str_replace( array( '-', '_' ), ' ', $appearance_id ) ) );

			$choices[ $id ] = $label;
		}

		return $choices;
	}

	public static function dir( ?string $appearance_id = null ): string {
		return ASREKHODRO_THEME_DIR . '/appearances/' . ( $appearance_id ?? self::id() );
	}

	public static function uri( ?string $appearance_id = null ): string {
		return ASREKHODRO_THEME_URI . '/appearances/' . ( $appearance_id ?? self::id() );
	}

	public static function asset_url( string $relative, ?string $appearance_id = null ): string {
		$relative = ltrim( $relative, '/' );

		if ( $appearance_id !== null ) {
			return self::uri( $appearance_id ) . '/assets/' . $relative;
		}

		foreach ( self::view_search_ids() as $search_id ) {
			if ( file_exists( self::dir( $search_id ) . '/assets/' . $relative ) ) {
				return self::uri( $search_id ) . '/assets/' . $relative;
			}
		}

		return self::uri() . '/assets/' . $relative;
	}

	/**
	 * Theme-relative Twig directories for Timber (active → fallback → shared).
	 *
	 * @return array<string, list<string>>
	 */
	public static function timber_locations(): array {
		$dirs = self::timber_relative_dirs();

		if ( $dirs === array() ) {
			return array( \Timber\Loader::MAIN_NAMESPACE => array( 'views' ) );
		}

		return array( \Timber\Loader::MAIN_NAMESPACE => $dirs );
	}

	/**
	 * @return list<string>
	 */
	public static function timber_relative_dirs(): array {
		$dirs = array();

		foreach ( self::view_search_ids() as $appearance_id ) {
			$relative = 'appearances/' . $appearance_id . '/views';
			if ( is_dir( ASREKHODRO_THEME_DIR . '/' . $relative ) ) {
				$dirs[] = $relative;
			}
		}

		$shared = 'appearances/shared/views';
		if ( is_dir( ASREKHODRO_THEME_DIR . '/' . $shared ) ) {
			$dirs[] = $shared;
		}

		$acf_blocks = 'inc/blocks';
		if ( is_dir( ASREKHODRO_THEME_DIR . '/' . $acf_blocks ) ) {
			$dirs[] = $acf_blocks;
		}

		return array_values( array_unique( $dirs ) );
	}

	/**
	 * Verify template exists in search path; returns Twig-relative path for Timber::render().
	 */
	public static function resolve_template( string $relative ): string {
		$relative = ltrim( str_replace( '\\', '/', $relative ), '/' );

		foreach ( self::view_search_ids() as $appearance_id ) {
			if ( file_exists( self::dir( $appearance_id ) . '/views/' . $relative ) ) {
				return $relative;
			}
		}

		if ( file_exists( ASREKHODRO_THEME_DIR . '/appearances/shared/views/' . $relative ) ) {
			return $relative;
		}

		if ( file_exists( ASREKHODRO_THEME_DIR . '/inc/blocks/' . $relative ) ) {
			return $relative;
		}

		return $relative;
	}

	/**
	 * @return list<string>|null
	 */
	public static function page_sections( string $page_key ): ?array {
		$manifest = self::load_manifest();
		$sections = $manifest['pages'][ $page_key ]['sections'] ?? null;

		return is_array( $sections ) ? array_values( $sections ) : null;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function page_zones( string $page_key, ?string $appearance_id = null ): array {
		$manifest = self::load_manifest( $appearance_id );
		$zones      = $manifest['pages'][ $page_key ]['zones'] ?? array();

		return is_array( $zones ) ? $zones : array();
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function page_defaults( string $page_key, ?string $appearance_id = null ): array {
		$manifest = self::load_manifest( $appearance_id );
		$defaults = $manifest['pages'][ $page_key ]['defaults'] ?? array();

		return is_array( $defaults ) ? $defaults : array();
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function all_block_definitions( ?string $appearance_id = null ): array {
		$definitions   = BlockRegistry::manifest_entries();
		$appearance_id = $appearance_id ?? self::id();
		$ids           = $appearance_id === self::id()
			? array_merge( array( $appearance_id ), array_diff( self::registered_ids(), array( $appearance_id ) ) )
			: array( $appearance_id, self::FALLBACK_ID );

		foreach ( array_values( array_unique( $ids ) ) as $id ) {
			$blocks = self::load_manifest( $id )['blocks'] ?? array();
			foreach ( $blocks as $name => $config ) {
				if ( is_array( $config ) ) {
					$definitions[ $name ] = array_merge( $definitions[ $name ] ?? array(), $config );
				}
			}
		}

		return $definitions;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function block_config( string $block_name ): array {
		$base     = BlockRegistry::manifest_entries()[ $block_name ] ?? array();
		$config   = self::load_manifest()['blocks'][ $block_name ] ?? array();

		if ( $config !== array() ) {
			return is_array( $config ) ? array_merge( $base, $config ) : $base;
		}

		if ( $base !== array() ) {
			return $base;
		}

		$fallback = self::load_manifest( self::FALLBACK_ID )['blocks'][ $block_name ] ?? array();

		return is_array( $fallback ) ? array_merge( $base, $fallback ) : $base;
	}

	/**
	 * @return list<string>
	 */
	public static function block_data_sections( string $block_name ): array {
		$config = self::block_config( $block_name );

		if ( ! empty( $config['data'] ) && is_array( $config['data'] ) ) {
			return array_values( $config['data'] );
		}

		return self::default_block_data_sections( $block_name );
	}

	public static function block_template( string $block_name ): string {
		return (string) ( self::block_config( $block_name )['template'] ?? '' );
	}

	public static function enqueue_assets(): void {
		self::handler()::enqueue_assets();
	}

	public static function preload_font(): void {
		self::handler()::preload_font();
	}

	/**
	 * @return class-string
	 */
	private static function handler(): string {
		$class = 'AsreKhodro\\Theme\\Appearances\\' . self::studly( self::id() ) . 'Appearance';

		if ( class_exists( $class ) ) {
			return $class;
		}

		return \AsreKhodro\Theme\Appearances\ClassicAppearance::class;
	}

	private static function studly( string $id ): string {
		return str_replace( ' ', '', ucwords( str_replace( array( '-', '_' ), ' ', $id ) ) );
	}

	/**
	 * @return list<string>
	 */
	private static function default_block_data_sections( string $block_name ): array {
		$map = array(
			'ak-hero'          => array( 'hero' ),
			'ak-ticker'        => array( 'ticker' ),
			'ak-featured-grid'      => array( 'featured' ),
			'ak-asrekhodro-featured'=> array( 'featured', 'news_archive_url' ),
			'ak-news-list'     => array( 'news_list', 'news_archive_url' ),
			'ak-magazines'     => array( 'magazines' ),
			'ak-videos-2'      => array( 'videos_2' ),
			'ak-videos'        => array( 'videos' ),
			'ak-reviews'       => array( 'reviews' ),
			'ak-newsletter'    => array(),
		);

		return $map[ $block_name ] ?? array();
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function load_manifest( ?string $appearance_id = null ): array {
		$appearance_id = $appearance_id ?? self::id();
		$file          = self::dir( $appearance_id ) . '/manifest.php';

		if ( ! file_exists( $file ) ) {
			return array( 'id' => $appearance_id );
		}

		$manifest = require $file;

		return is_array( $manifest ) ? $manifest : array( 'id' => $appearance_id );
	}

	private static function detect_active_id(): string {
		$registered = self::registered_ids();

		// Use WP options directly — never get_field() here.
		// Early get_field during theme bootstrap re-enters ACF field registration
		// which can call Appearance again and crash the Apache PHP worker.
		$value = get_option( 'options_active_appearance', null );

		if ( is_string( $value ) && $value !== '' && in_array( $value, $registered, true ) ) {
			return $value;
		}

		if ( in_array( self::DEFAULT_ID, $registered, true ) ) {
			return self::DEFAULT_ID;
		}

		return $registered[0];
	}

	/**
	 * @return list<string>
	 */
	private static function view_search_ids(): array {
		$active = self::id();
		$ids    = array( $active );

		if ( $active !== self::FALLBACK_ID && in_array( self::FALLBACK_ID, self::registered_ids(), true ) ) {
			$ids[] = self::FALLBACK_ID;
		}

		return array_values( array_unique( $ids ) );
	}
}
