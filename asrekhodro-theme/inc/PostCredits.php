<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writer / photographer credits for news, stored as taxonomies (term relationships), not post meta.
 */
final class PostCredits {

	public const WRITER       = 'ak_writer';
	public const PHOTOGRAPHER = 'ak_photographer';

	public const TAXONOMIES = array( self::WRITER, self::PHOTOGRAPHER );

	private const REWRITE_FLAG = 'ak_post_credits_rewrite_v1';

	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ), 5 );
		add_action( 'wp_loaded', array( self::class, 'maybe_flush_rewrites' ) );
	}

	public static function register(): void {
		register_taxonomy(
			self::WRITER,
			'post',
			self::taxonomy_args( 'writer', 'نویسندگان', 'نویسنده' )
		);

		register_taxonomy(
			self::PHOTOGRAPHER,
			'post',
			self::taxonomy_args( 'photographer', 'عکاسان', 'عکاس' )
		);
	}

	/**
	 * Rules must be flushed after every init-time rewrite is registered.
	 */
	public static function maybe_flush_rewrites(): void {
		if ( get_option( self::REWRITE_FLAG ) ) {
			return;
		}

		update_option( self::REWRITE_FLAG, 1, false );
		flush_rewrite_rules( false );
	}

	public static function is_archive_request(): bool {
		return is_tax( self::TAXONOMIES );
	}

	/**
	 * Non-empty credit groups for a post; empty array when nothing is assigned.
	 *
	 * @return list<array{label: string, items: list<array{name: string, link: string}>}>
	 */
	public static function for_post( int $post_id ): array {
		$groups = array(
			self::WRITER       => 'نویسنده',
			self::PHOTOGRAPHER => 'عکاس',
		);

		$credits = array();
		foreach ( $groups as $taxonomy => $label ) {
			$terms = get_the_terms( $post_id, $taxonomy );
			if ( ! is_array( $terms ) || $terms === array() ) {
				continue;
			}

			$items = array();
			foreach ( $terms as $term ) {
				$link = get_term_link( $term );
				if ( is_wp_error( $link ) ) {
					continue;
				}

				$items[] = array(
					'name' => $term->name,
					'link' => $link,
				);
			}

			if ( $items !== array() ) {
				$credits[] = array(
					'label' => $label,
					'items' => $items,
				);
			}
		}

		return $credits;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function taxonomy_args( string $slug, string $name, string $singular ): array {
		return array(
			'labels'             => array(
				'name'                       => $name,
				'singular_name'              => $singular,
				'menu_name'                  => $name,
				'all_items'                  => 'همه ' . $name,
				'edit_item'                  => 'ویرایش ' . $singular,
				'view_item'                  => 'مشاهده ' . $singular,
				'update_item'                => 'به‌روزرسانی ' . $singular,
				'add_new_item'               => 'افزودن ' . $singular . ' جدید',
				'new_item_name'              => 'نام ' . $singular . ' جدید',
				'search_items'               => 'جستجوی ' . $name,
				'popular_items'              => $name . ' پرکاربرد',
				'separate_items_with_commas' => 'نام‌ها را با ویرگول جدا کنید',
				'add_or_remove_items'        => 'افزودن یا حذف ' . $singular,
				'choose_from_most_used'      => 'انتخاب از ' . $name . ' پرکاربرد',
				'not_found'                  => $singular . ' یافت نشد.',
				'no_terms'                   => 'بدون ' . $singular,
				'back_to_items'              => '← بازگشت به ' . $name,
			),
			'public'             => true,
			'hierarchical'       => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => false,
			'show_tagcloud'      => false,
			'show_in_quick_edit' => true,
			'show_admin_column'  => true,
			'show_in_rest'       => true,
			'rewrite'            => array(
				'slug'       => $slug,
				'with_front' => false,
			),
		);
	}
}
