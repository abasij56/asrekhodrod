<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thumbnail and view-count columns on admin post lists.
 */
final class AdminPostList {

	/** @var array<int, string> */
	private static array $post_types = array( 'post', 'ad_slot', 'ak_video', 'ak_magazine', 'ak_review', 'carsinfo' );

	public static function init(): void {
		foreach ( self::$post_types as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( self::class, 'manage_columns' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( self::class, 'render_custom_column' ), 10, 2 );
			add_filter( "manage_edit-{$post_type}_sortable_columns", array( self::class, 'sortable_columns' ) );
		}

		add_action( 'restrict_manage_posts', array( self::class, 'render_views_filter' ) );
		add_action( 'pre_get_posts', array( self::class, 'adjust_admin_query' ) );
		add_action( 'admin_head', array( self::class, 'admin_styles' ) );
	}

	/**
	 * @param array<string, string> $columns
	 * @return array<string, string>
	 */
	public static function manage_columns( array $columns ): array {
		$ordered = array( 'ak_thumb' => 'تصویر' );

		foreach ( $columns as $key => $label ) {
			if ( $key === 'date' ) {
				$ordered['ak_views'] = 'تعداد بازدید';
			}

			$ordered[ $key ] = $label;
		}

		return $ordered;
	}

	/**
	 * @param array<string, string> $columns
	 * @return array<string, string>
	 */
	public static function sortable_columns( array $columns ): array {
		$columns['ak_views'] = 'ak_views';

		return $columns;
	}

	public static function render_views_filter( string $post_type ): void {
		if ( ! in_array( $post_type, self::$post_types, true ) ) {
			return;
		}

		$min_views = isset( $_GET['ak_min_views'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['ak_min_views'] ) ) : '';

		printf(
			'<label class="screen-reader-text" for="ak-min-views">%s</label>',
			esc_html( 'حداقل تعداد بازدید' )
		);
		printf(
			'<input type="number" id="ak-min-views" name="ak_min_views" class="ak-admin-views-filter" min="0" step="1" placeholder="%s" value="%s" />',
			esc_attr( 'حداقل بازدید' ),
			esc_attr( $min_views )
		);
	}

	public static function adjust_admin_query( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		global $pagenow;
		if ( $pagenow !== 'edit.php' ) {
			return;
		}

		$post_type = $query->get( 'post_type' );
		if ( ! is_string( $post_type ) || $post_type === '' ) {
			$post_type = 'post';
		}

		if ( ! in_array( $post_type, self::$post_types, true ) ) {
			return;
		}

		if ( $query->get( 'orderby' ) === 'ak_views' ) {
			add_filter( 'posts_clauses', array( self::class, 'order_by_views_clauses' ), 10, 2 );
		}

		if ( ! isset( $_GET['ak_min_views'] ) || $_GET['ak_min_views'] === '' ) {
			return;
		}

		$min_views = max( 0, (int) wp_unslash( (string) $_GET['ak_min_views'] ) );
		$meta_query = $query->get( 'meta_query' );
		if ( ! is_array( $meta_query ) ) {
			$meta_query = array();
		}

		$meta_query[] = array(
			'key'     => PostViews::total_meta_key(),
			'value'   => $min_views,
			'compare' => '>=',
			'type'    => 'NUMERIC',
		);

		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * @param array<string, string> $clauses
	 * @return array<string, string>
	 */
	public static function order_by_views_clauses( array $clauses, \WP_Query $query ): array {
		if ( $query->get( 'orderby' ) !== 'ak_views' ) {
			return $clauses;
		}

		global $wpdb;

		$meta_key  = PostViews::total_meta_key();
		$direction = strtoupper( (string) $query->get( 'order' ) ) === 'ASC' ? 'ASC' : 'DESC';

		$clauses['join'] .= $wpdb->prepare(
			" LEFT JOIN {$wpdb->postmeta} AS ak_views_pm ON ({$wpdb->posts}.ID = ak_views_pm.post_id AND ak_views_pm.meta_key = %s)",
			$meta_key
		);
		$clauses['orderby'] = "CAST(COALESCE(ak_views_pm.meta_value, '0') AS UNSIGNED) {$direction}, {$wpdb->posts}.post_date DESC";

		return $clauses;
	}

	public static function render_custom_column( string $column, int $post_id ): void {
		if ( $column === 'ak_thumb' ) {
			self::render_thumb_column( $post_id );

			return;
		}

		if ( $column === 'ak_views' ) {
			printf(
				'<span class="ak-admin-views-count">%s</span>',
				esc_html( number_format_i18n( PostViews::get_total( $post_id ) ) )
			);
		}
	}

	public static function render_thumb_column( int $post_id ): void {
		$url       = self::get_list_thumb_url( $post_id );
		$is_mag    = get_post_type( $post_id ) === 'ak_magazine';
		$thumb_cls = 'ak-admin-post-thumb__img' . ( $is_mag ? ' ak-admin-post-thumb__img--magazine' : '' );
		$img_w     = $is_mag ? 45 : 60;
		$img_h     = $is_mag ? 60 : 40;

		if ( $url === '' ) {
			$empty_label = $is_mag ? esc_html__( 'بدون تصویر شاخص', 'asrekhodro' ) : '—';
			printf(
				'<span class="ak-admin-post-thumb ak-admin-post-thumb--empty" title="%s" aria-hidden="true">%s</span>',
				$is_mag ? esc_attr__( 'تصویر شاخص تنظیم نشده — ایمپورت کاور انجام نشده یا ناموفق بوده', 'asrekhodro' ) : '',
				$empty_label
			);
			return;
		}

		$edit_link = get_edit_post_link( $post_id, 'raw' );
		if ( ! is_string( $edit_link ) || $edit_link === '' ) {
			printf(
				'<img class="%s" src="%s" alt="" width="%d" height="%d" loading="lazy" decoding="async" />',
				esc_attr( $thumb_cls ),
				esc_url( $url ),
				$img_w,
				$img_h
			);
			return;
		}

		printf(
			'<a href="%s" class="ak-admin-post-thumb" aria-label="%s"><img class="%s" src="%s" alt="" width="%d" height="%d" loading="lazy" decoding="async" /></a>',
			esc_url( $edit_link ),
			esc_attr( get_the_title( $post_id ) ),
			esc_attr( $thumb_cls ),
			esc_url( $url ),
			$img_w,
			$img_h
		);
	}

	public static function get_list_thumb_url( int $post_id ): string {
		if ( get_post_type( $post_id ) === 'ad_slot' ) {
			return self::get_ad_thumb_url( $post_id );
		}

		if ( get_post_type( $post_id ) === 'ak_magazine' ) {
			return self::get_magazine_featured_thumb_url( $post_id );
		}

		if ( has_post_thumbnail( $post_id ) ) {
			$attachment_id = (int) get_post_thumbnail_id( $post_id );
			if ( class_exists( '\AsreKhodro\Theme\ExternalMedia' ) ) {
				$src = \AsreKhodro\Theme\ExternalMedia::get_attachment_url( $attachment_id );
				if ( $src !== '' ) {
					return $src;
				}
			}

			$src = wp_get_attachment_image_url( $attachment_id, 'ak-news-list' );
			if ( ! $src ) {
				$src = wp_get_attachment_image_url( get_post_thumbnail_id( $post_id ), 'thumbnail' );
			}
			if ( $src ) {
				return esc_url( $src );
			}
		}

		$from_meta = ImporterBridge::resolve_media_url( get_post_meta( $post_id, '_asrekhodro_image_url', true ) );
		if ( $from_meta !== '' ) {
			return $from_meta;
		}

		$content = (string) get_post_field( 'post_content', $post_id );
		$from_body = ImporterBridge::extract_image_from_html( $content );
		if ( $from_body !== '' ) {
			$resolved = ImporterBridge::resolve_media_url( $from_body );
			if ( $resolved !== '' ) {
				return $resolved;
			}
			if ( preg_match( '#^https?://#i', $from_body ) ) {
				return esc_url( $from_body );
			}
		}

		return '';
	}

	/**
	 * Magazine admin list: featured image only (verify import set_post_thumbnail).
	 */
	private static function get_magazine_featured_thumb_url( int $post_id ): string {
		if ( ! has_post_thumbnail( $post_id ) ) {
			return '';
		}

		$attachment_id = (int) get_post_thumbnail_id( $post_id );
		if ( $attachment_id <= 0 ) {
			return '';
		}

		if ( class_exists( '\AsreKhodro\Theme\ExternalMedia' ) ) {
			$src = \AsreKhodro\Theme\ExternalMedia::get_attachment_url( $attachment_id );
			if ( $src !== '' ) {
				return $src;
			}
		}

		$src = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );

		return $src ? esc_url( $src ) : '';
	}

	private static function get_ad_thumb_url( int $post_id ): string {
		if ( function_exists( 'get_field' ) ) {
			$image = get_field( 'ad_image', $post_id );
			$url   = self::resolve_attachment_thumb_url( $image );
			if ( $url !== '' ) {
				return $url;
			}
		}

		$attachment_id = (int) get_post_meta( $post_id, 'ad_image', true );
		if ( $attachment_id > 0 ) {
			$url = self::resolve_attachment_thumb_url( $attachment_id );
			if ( $url !== '' ) {
				return $url;
			}
		}

		$from_meta = ImporterBridge::resolve_media_url( get_post_meta( $post_id, '_asrekhodro_image_url', true ) );
		if ( $from_meta !== '' ) {
			return $from_meta;
		}

		return '';
	}

	/**
	 * @param mixed $image ACF image field value or attachment ID.
	 */
	private static function resolve_attachment_thumb_url( mixed $image ): string {
		if ( is_array( $image ) ) {
			if ( ! empty( $image['url'] ) ) {
				return esc_url( (string) $image['url'] );
			}

			if ( ! empty( $image['ID'] ) ) {
				$image = (int) $image['ID'];
			}
		}

		if ( is_numeric( $image ) ) {
			$attachment_id = (int) $image;
			if ( $attachment_id <= 0 ) {
				return '';
			}

			if ( class_exists( '\AsreKhodro\Theme\ExternalMedia' ) ) {
				$src = \AsreKhodro\Theme\ExternalMedia::get_attachment_url( $attachment_id );
				if ( $src !== '' ) {
					return $src;
				}
			}

			$src = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
			if ( $src ) {
				return esc_url( $src );
			}
		}

		return '';
	}

	public static function admin_styles(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || $screen->base !== 'edit' || ! in_array( $screen->post_type, self::$post_types, true ) ) {
			return;
		}
		?>
		<style>
			.wp-list-table .column-ak_thumb {
				width: 72px;
				text-align: center;
				vertical-align: middle;
			}
			.wp-list-table .column-ak_views {
				width: 88px;
				text-align: center;
			}
			.wp-list-table .ak-admin-views-count {
				display: inline-block;
				min-width: 3ch;
				font-variant-numeric: tabular-nums;
			}
			input.ak-admin-views-filter {
				width: 8em;
				margin: 0 4px 0 0;
			}
			body.post-type-ak_magazine .wp-list-table .column-ak_thumb {
				width: 56px;
			}
			.wp-list-table .ak-admin-post-thumb {
				display: inline-block;
				line-height: 0;
			}
			.wp-list-table .ak-admin-post-thumb__img {
				display: block;
				width: 60px;
				height: 40px;
				object-fit: cover;
				border-radius: 2px;
				background: #f0f0f1;
			}
			.wp-list-table .ak-admin-post-thumb__img--magazine {
				width: 45px;
				height: 60px;
			}
			.wp-list-table .ak-admin-post-thumb--empty {
				color: #c3c4c7;
				font-size: 11px;
				line-height: 1.35;
				display: inline-block;
				max-width: 52px;
			}
		</style>
		<?php
	}
}
