<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Jalali date filter on the admin posts list (اخبار).
 */
final class AdminNewsDateFilter {

	private const YEAR_KEY  = 'ak_jyear';
	private const MONTH_KEY = 'ak_jmonth';
	private const DAY_KEY   = 'ak_jday';

	/** @var array<int, string> */
	private const QUERY_KEYS = array( self::YEAR_KEY, self::MONTH_KEY, self::DAY_KEY );

	public static function init(): void {
		add_action( 'restrict_manage_posts', array( self::class, 'render_filter' ), 12 );
		add_action( 'pre_get_posts', array( self::class, 'apply_filter' ), 25 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
		add_action( 'admin_head', array( self::class, 'admin_styles' ) );
	}

	public static function render_filter( string $post_type ): void {
		if ( $post_type !== 'post' ) {
			return;
		}

		$active = NewsArchive::active_jalali_filter_from_request( self::YEAR_KEY, self::MONTH_KEY, self::DAY_KEY );
		$fields = NewsArchive::jalali_filter_form_fields( $active ?? array() );

		if ( $fields['years'] === array() ) {
			return;
		}

		$clear_url = remove_query_arg( array_merge( self::QUERY_KEYS, array( 'paged' ) ) );

		echo '<span class="ak-admin-jalali-date-filter" data-ak-news-date-filter data-ak-date-filter-compact="1" data-month-lengths="' . esc_attr( $fields['month_lengths_json'] ) . '">';
		echo '<span class="ak-admin-jalali-date-filter__label">تاریخ شمسی</span>';

		echo '<select name="' . esc_attr( self::YEAR_KEY ) . '" class="ak-admin-jalali-date-filter__select" data-news-date-year aria-label="سال">';
		echo '<option value="">سال</option>';
		foreach ( $fields['years'] as $option ) {
			printf(
				'<option value="%1$d"%2$s>%3$s</option>',
				(int) $option['value'],
				! empty( $option['selected'] ) ? ' selected' : '',
				esc_html( (string) $option['label'] )
			);
		}
		echo '</select>';

		echo '<select name="' . esc_attr( self::MONTH_KEY ) . '" class="ak-admin-jalali-date-filter__select" data-news-date-month aria-label="ماه">';
		echo '<option value="">ماه</option>';
		foreach ( $fields['months'] as $option ) {
			printf(
				'<option value="%1$d"%2$s>%3$s</option>',
				(int) $option['value'],
				! empty( $option['selected'] ) ? ' selected' : '',
				esc_html( (string) $option['label'] )
			);
		}
		echo '</select>';

		$day_disabled = $fields['month'] <= 0 ? ' disabled' : '';
		echo '<select name="' . esc_attr( self::DAY_KEY ) . '" class="ak-admin-jalali-date-filter__select" data-news-date-day' . esc_attr( $day_disabled ) . ' aria-label="روز">';
		foreach ( $fields['days'] as $option ) {
			$label = ( (int) $option['value'] === 0 ) ? 'روز' : (string) $option['label'];
			printf(
				'<option value="%1$d"%2$s>%3$s</option>',
				(int) $option['value'],
				! empty( $option['selected'] ) ? ' selected' : '',
				esc_html( $label )
			);
		}
		echo '</select>';

		if ( $active !== null ) {
			printf(
				'<a href="%s" class="ak-admin-jalali-date-filter__clear">حذف</a>',
				esc_url( $clear_url )
			);
		}

		echo '</span>';
	}

	public static function apply_filter( \WP_Query $query ): void {
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

		if ( $post_type !== 'post' ) {
			return;
		}

		NewsArchive::apply_jalali_date_query(
			$query,
			NewsArchive::active_jalali_filter_from_request( self::YEAR_KEY, self::MONTH_KEY, self::DAY_KEY )
		);
	}

	public static function enqueue_assets( string $hook_suffix ): void {
		if ( $hook_suffix !== 'edit.php' ) {
			return;
		}

		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( (string) wp_unslash( $_GET['post_type'] ) ) : 'post';
		if ( $post_type !== 'post' ) {
			return;
		}

		$js_path = ASREKHODRO_THEME_DIR . '/assets/admin/news-date-filter.js';
		if ( ! is_readable( $js_path ) ) {
			return;
		}

		wp_enqueue_script(
			'ak-admin-news-date-filter',
			ASREKHODRO_THEME_URI . '/assets/admin/news-date-filter.js',
			array(),
			(string) filemtime( $js_path ),
			true
		);
	}

	public static function admin_styles(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || $screen->id !== 'edit-post' ) {
			return;
		}
		?>
		<style>
			.ak-admin-jalali-date-filter {
				display: inline-flex;
				align-items: center;
				flex-wrap: wrap;
				gap: 4px;
				margin: 0 6px 0 0;
				vertical-align: middle;
			}
			.ak-admin-jalali-date-filter__label {
				font-weight: 600;
				margin-left: 2px;
			}
			.ak-admin-jalali-date-filter__select {
				max-width: 7.5em;
				vertical-align: middle;
			}
			.ak-admin-jalali-date-filter__clear {
				margin-right: 2px;
				text-decoration: none;
			}
		</style>
		<?php
	}
}
