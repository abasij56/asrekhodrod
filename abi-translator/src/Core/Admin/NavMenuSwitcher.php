<?php

namespace ABI\Translator\Core\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABI\Translator\Core\Language\LanguageDetector;
use ABI\Translator\Core\Language\UrlBuilder;

/**
 * Adds a "Language switcher" meta box to Appearance > Menus so the switcher
 * can be inserted into any nav menu (like Polylang/WPML). The placeholder menu
 * item is expanded on the front-end into one link per active language.
 */
final class NavMenuSwitcher {

	/**
	 * Marker stored as the placeholder menu item's URL. Front-end rendering
	 * detects it and expands the item into one link per language.
	 */
	private const MARKER = '#abi-lang-switcher';

	public function register(): void {
		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'add_meta_box' ) );
		} else {
			add_filter( 'wp_nav_menu_objects', array( $this, 'expand_items' ), 10, 2 );
		}
	}

	public function add_meta_box(): void {
		add_meta_box(
			'abi-translator-nav-switcher',
			__( 'سوییچ زبان', 'abi-translator' ),
			array( $this, 'render_meta_box' ),
			'nav-menus',
			'side',
			'low'
		);
	}

	public function render_meta_box(): void {
		?>
		<div id="abi-lang-switcher-metabox" class="posttypediv">
			<div class="tabs-panel tabs-panel-active">
				<ul class="categorychecklist form-no-clear">
					<li>
						<label class="menu-item-title">
							<input type="checkbox" class="menu-item-checkbox" name="menu-item[-1][menu-item-object-id]" value="-1" />
							<?php esc_html_e( 'سوییچ زبان', 'abi-translator' ); ?>
						</label>
						<input type="hidden" class="menu-item-type" name="menu-item[-1][menu-item-type]" value="custom" />
						<input type="hidden" class="menu-item-title" name="menu-item[-1][menu-item-title]" value="<?php esc_attr_e( 'سوییچ زبان', 'abi-translator' ); ?>" />
						<input type="hidden" class="menu-item-url" name="menu-item[-1][menu-item-url]" value="<?php echo esc_attr( self::MARKER ); ?>" />
						<input type="hidden" class="menu-item-classes" name="menu-item[-1][menu-item-classes]" value="abi-lang-switcher" />
					</li>
				</ul>
			</div>
			<p class="button-controls">
				<span class="add-to-menu">
					<input
						type="submit"
						class="button-secondary submit-add-to-menu right"
						value="<?php esc_attr_e( 'افزودن به منو', 'abi-translator' ); ?>"
						name="add-post-type-menu-item"
						id="submit-abi-lang-switcher-metabox"
					/>
					<span class="spinner"></span>
				</span>
			</p>
		</div>
		<?php
	}

	/**
	 * Replace each placeholder menu item with one link per active language.
	 *
	 * @param array<int, \WP_Post|object> $items
	 * @param object                      $args
	 * @return array<int, object>
	 */
	public function expand_items( array $items, $args ): array {
		$has_marker = false;
		foreach ( $items as $item ) {
			if ( $this->is_marker( $item ) ) {
				$has_marker = true;
				break;
			}
		}

		if ( ! $has_marker ) {
			return $items;
		}

		$languages = LanguageDetector::active_languages();
		$current   = LanguageDetector::current();

		$result       = array();
		$synthetic_id = 990000;

		foreach ( $items as $item ) {
			if ( ! $this->is_marker( $item ) ) {
				$result[] = $item;
				continue;
			}

			// Drop the switcher entirely when there is nothing to switch between.
			if ( count( $languages ) < 2 ) {
				continue;
			}

			foreach ( $languages as $lang ) {
				$clone = clone $item;

				$clone->ID        = ++$synthetic_id;
				$clone->db_id     = $clone->ID;
				$clone->object_id = $clone->ID;
				$clone->object    = 'custom';
				$clone->type      = 'custom';
				$clone->title     = $this->label( $lang );
				$clone->attr_title = '';
				$clone->url       = UrlBuilder::switch_url( $lang );

				$classes   = array( 'abi-lang-switcher-item', 'abi-lang-' . $lang );
				$is_current = $lang === $current;
				if ( $is_current ) {
					$classes[]        = 'current-menu-item';
					$clone->current   = true;
				}
				$clone->classes = $classes;

				$result[] = $clone;
			}
		}

		return $result;
	}

	private function is_marker( object $item ): bool {
		return isset( $item->url ) && str_contains( (string) $item->url, 'abi-lang-switcher' );
	}

	private function label( string $lang ): string {
		$names = array(
			'fa' => 'فارسی',
			'en' => 'English',
			'ar' => 'العربية',
		);

		$label = $names[ $lang ] ?? strtoupper( $lang );

		/** Reuse the same filter as the shortcode switcher for consistency. */
		return (string) apply_filters( 'abi_translator_switcher_label', $label, $lang );
	}
}
