<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unified block registry: auto-discovers inc/blocks/{name}/config.php and ACF block classes.
 */
final class BlockRegistry {

	private const SKIP_DIRS = array( 'layout', 'Support', 'definitions', 'acf' );

	/** @var array<string, array<string, mixed>>|null */
	private static ?array $configs = null;

	/** @var array<string, class-string> */
	private static array $acf_blocks = array();

	public static function boot(): void {
		foreach ( self::discover_block_dirs() as $dir ) {
			$config_file = $dir . '/config.php';
			if ( ! file_exists( $config_file ) ) {
				continue;
			}

			/** @var array<string, mixed> $config */
			$config = require $config_file;
			$name   = (string) ( $config['name'] ?? basename( $dir ) );
			if ( $name === '' ) {
				continue;
			}

			if ( self::is_acf_block( $config ) ) {
				self::boot_acf_block( $dir );
			}
		}
	}

	/**
	 * @param class-string $block_class
	 */
	public static function register_acf( string $block_class ): void {
		if ( ! method_exists( $block_class, 'name' ) ) {
			return;
		}

		self::$acf_blocks[ $block_class::name() ] = $block_class;
	}

	/**
	 * @return class-string|null
	 */
	public static function acf_class( string $block_name ): ?string {
		return self::$acf_blocks[ $block_name ] ?? null;
	}

	/**
	 * @return array<string, class-string>
	 */
	public static function acf_classes(): array {
		return self::$acf_blocks;
	}

	public static function register_acf_field_groups(): void {
		foreach ( self::$acf_blocks as $block_class ) {
			if ( method_exists( $block_class, 'register_fields' ) ) {
				$block_class::register_fields();
			}
		}
	}

	public static function register_acf_block_types(): void {
		foreach ( self::$acf_blocks as $block_class ) {
			if ( method_exists( $block_class, 'register_block_type' ) ) {
				$block_class::register_block_type();
			}
		}
	}

	/**
	 * @param array<string, mixed> $fields
	 * @return array<string, mixed>
	 */
	public static function build_acf_context( string $block_name, array $fields, ?\Timber\Post $post = null ): array {
		$block_class = self::acf_class( $block_name );
		if ( $block_class === null || ! method_exists( $block_class, 'build_context' ) ) {
			return array();
		}

		return $block_class::build_context( $fields, $post );
	}

	public static function acf_template_file( string $block_name ): string {
		$block_class = self::acf_class( $block_name );
		if ( $block_class === null || ! method_exists( $block_class, 'template_file' ) ) {
			return '';
		}

		return $block_class::template_file();
	}

	/**
	 * @return list<string>
	 */
	public static function acf_block_names(): array {
		return array_keys( self::$acf_blocks );
	}

	/**
	 * @return array<int, array{0: string, 1?: array<string, mixed>}>
	 */
	public static function carsinfo_editor_template(): array {
		$items = array();

		foreach ( self::$acf_blocks as $block_class ) {
			if ( ! method_exists( $block_class, 'seed_carsinfo' ) || ! $block_class::seed_carsinfo() ) {
				continue;
			}

			$config = method_exists( $block_class, 'config' ) ? $block_class::config() : array();
			$order  = (int) ( $config['seed_order'] ?? 100 );
			$items[] = array(
				'order' => $order,
				'item'  => array( 'acf/' . $block_class::name() ),
			);
		}

		usort(
			$items,
			static function ( array $a, array $b ): int {
				return $a['order'] <=> $b['order'];
			}
		);

		return array_map(
			static fn( array $row ): array => $row['item'],
			$items
		);
	}

	/**
	 * Serialize default block markup for carsinfo posts.
	 */
	public static function default_block_content( string $context = 'carsinfo' ): string {
		unset( $context );
		$content = '';

		foreach ( self::carsinfo_editor_template() as $item ) {
			$name  = (string) ( $item[0] ?? '' );
			$attrs = (array) ( $item[1] ?? array() );

			if ( $name === '' ) {
				continue;
			}

			$content .= serialize_block(
				array(
					'blockName'    => $name,
					'attrs'        => array_merge(
						array(
							'name' => $name,
							'data' => array(),
							'mode' => 'edit',
						),
						$attrs
					),
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				)
			);
		}

		return $content;
	}

	/**
	 * Manifest entries for layout engine and appearance merging.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function manifest_entries(): array {
		$entries = array();

		foreach ( self::configs() as $name => $config ) {
			if ( self::is_acf_block( $config ) ) {
				$entry = is_array( $config['manifest'] ?? null ) ? $config['manifest'] : array();
				if ( ! isset( $entry['label'] ) ) {
					$entry['label'] = (string) ( $config['title'] ?? $config['label'] ?? $name );
				}
				if ( ! isset( $entry['source'] ) ) {
					$entry['source'] = 'acf';
				}
				if ( ! isset( $entry['template'] ) ) {
					$entry['template'] = $name . '/template.twig';
				}
				$entries[ $name ] = $entry;
				continue;
			}

			$entry = $config;
			unset( $entry['name'], $entry['type'] );
			$entries[ $name ] = $entry;
		}

		return $entries;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function configs(): array {
		if ( self::$configs !== null ) {
			return self::$configs;
		}

		self::$configs = array();

		foreach ( self::discover_block_dirs() as $dir ) {
			$config_file = $dir . '/config.php';
			if ( ! file_exists( $config_file ) ) {
				continue;
			}

			/** @var array<string, mixed> $config */
			$config = require $config_file;
			$name   = (string) ( $config['name'] ?? basename( $dir ) );
			if ( $name === '' ) {
				continue;
			}

			self::$configs[ $name ] = $config;
		}

		return self::$configs;
	}

	public static function config( string $block_name ): array {
		return self::configs()[ $block_name ] ?? array();
	}

	public static function resolve_partial( string $block_name ): string {
		$config  = self::config( $block_name );
		$partial = (string) ( $config['partial'] ?? '' );
		if ( $partial === '' ) {
			return '';
		}

		return Appearance::resolve_template( $partial );
	}

	/**
	 * @return list<string>
	 */
	private static function discover_block_dirs(): array {
		$base = ASREKHODRO_THEME_DIR . '/inc/blocks';
		$dirs = array();

		foreach ( glob( $base . '/*', GLOB_ONLYDIR ) ?: array() as $dir ) {
			$name = basename( $dir );
			if ( in_array( $name, self::SKIP_DIRS, true ) ) {
				continue;
			}
			$dirs[] = $dir;
		}

		sort( $dirs );

		return $dirs;
	}

	/**
	 * @param array<string, mixed> $config
	 */
	private static function is_acf_block( array $config ): bool {
		if ( ( $config['type'] ?? '' ) === 'acf' ) {
			return true;
		}

		return ( $config['source'] ?? '' ) === 'acf' && file_exists( self::block_dir( (string) ( $config['name'] ?? '' ) ) . '/Block.php' );
	}

	private static function block_dir( string $name ): string {
		return ASREKHODRO_THEME_DIR . '/inc/blocks/' . $name;
	}

	private static function boot_acf_block( string $dir ): void {
		foreach ( array( 'Fields.php', 'View.php', 'Block.php' ) as $file ) {
			$path = $dir . '/' . $file;
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}

		$block_file = $dir . '/Block.php';
		if ( ! file_exists( $block_file ) ) {
			return;
		}

		$content = (string) file_get_contents( $block_file );
		if ( ! preg_match( '/namespace\s+([^;]+);/', $content, $matches ) ) {
			return;
		}

		$class = $matches[1] . '\\Block';
		if ( class_exists( $class ) && method_exists( $class, 'boot' ) ) {
			$class::boot();
		}
	}
}
