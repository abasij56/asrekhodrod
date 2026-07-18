<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NewsArchive {

	private const DATE_QUERY_KEYS = array( 'jyear', 'jmonth', 'jday' );

	public static function init(): void {
		add_action( 'pre_get_posts', array( self::class, 'apply_date_filter' ), 20 );
		add_filter( 'get_pagenum_link', array( self::class, 'preserve_date_filter_in_pagination' ) );
	}

	public static function is_archive_request(): bool {
		if ( is_home() ) {
			return true;
		}

		if ( is_category() || is_tag() || is_author() || is_date() ) {
			return true;
		}

		return is_post_type_archive( 'post' );
	}

	/**
	 * @return array<string, string|bool>
	 */
	public static function get_archive_page_context( string $wp_title = '' ): array {
		$panel = self::get_active_panel();
		$hero  = self::get_hero_for_panel( $panel );

		return array(
			'news_archive_title'                => self::get_archive_title(),
			'news_archive_subtitle'             => self::get_subtitle_for_panel( $panel ),
			'news_archive_hero_image'           => $hero['url'],
			'news_archive_hero_aspect'          => $hero['aspect'],
			'news_archive_hero_aspect_fallback' => $hero['aspect_fallback'],
			'archive_hero_title'                => self::get_hero_title( $wp_title ),
			'news_date_filter'                  => self::date_filter_context(),
		);
	}

	/**
	 * Shared Jalali date filter context for archive pages and navigation.
	 *
	 * @return array<string, mixed>
	 */
	public static function date_filter_context( ?string $action_url = null ): array {
		$base_url = $action_url !== null && $action_url !== ''
			? $action_url
			: self::current_archive_url();

		return self::build_date_filter_context( $base_url );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function nav_date_filter_context(): array {
		return self::date_filter_context( HomepageData::news_archive_url() );
	}

	public static function apply_date_filter( \WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() || ! self::matches_news_archive_query( $query ) ) {
			return;
		}

		self::apply_jalali_date_query( $query, self::active_jalali_filter_from_request() );
	}

	/**
	 * @return array{year: int, month: int, day: int}|null
	 */
	public static function active_jalali_filter_from_request(
		string $year_key = 'jyear',
		string $month_key = 'jmonth',
		string $day_key = 'jday'
	): ?array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$year = isset( $_GET[ $year_key ] ) ? (int) wp_unslash( $_GET[ $year_key ] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$month = isset( $_GET[ $month_key ] ) ? (int) wp_unslash( $_GET[ $month_key ] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$day = isset( $_GET[ $day_key ] ) ? (int) wp_unslash( $_GET[ $day_key ] ) : 0;

		if ( $year <= 0 ) {
			return null;
		}

		return array(
			'year'  => $year,
			'month' => max( 0, $month ),
			'day'   => max( 0, $day ),
		);
	}

	/**
	 * @param array{year: int, month: int, day: int}|null $filter
	 */
	public static function apply_jalali_date_query( \WP_Query $query, ?array $filter ): void {
		if ( $filter === null ) {
			return;
		}

		$bounds = PersianDate::jalali_filter_bounds(
			$filter['year'],
			$filter['month'],
			$filter['day']
		);
		if ( $bounds === null ) {
			return;
		}

		$query->set(
			'date_query',
			array(
				array(
					'after'     => $bounds['after'],
					'before'    => $bounds['before'],
					'inclusive' => true,
					'column'    => 'post_date',
				),
			)
		);
	}

	/**
	 * Jalali year/month/day options for filter forms.
	 *
	 * @param array{year?: int, month?: int, day?: int}|null $active
	 * @return array{years: list<array{value: int, label: string, selected: bool}>, months: list<array{value: int, label: string, selected: bool}>, days: list<array{value: int, label: string, selected: bool}>, month_lengths_json: string, year: int, month: int, day: int}
	 */
	public static function jalali_filter_form_fields( ?array $active = null ): array {
		$now    = PersianDate::now_jalali_parts();
		$active = $active ?? array(
			'year'  => 0,
			'month' => 0,
			'day'   => 0,
		);
		$year        = (int) ( $active['year'] ?? 0 );
		$month       = (int) ( $active['month'] ?? 0 );
		$day         = (int) ( $active['day'] ?? 0 );
		$year_start  = 1388;
		$year_end    = $now['year'];
		$years       = array();

		for ( $candidate = $year_end; $candidate >= $year_start; --$candidate ) {
			$years[] = array(
				'value'    => $candidate,
				'label'    => PersianDigits::convert( (string) $candidate ),
				'selected' => $candidate === $year,
			);
		}

		$months = array();
		foreach ( PersianDate::jalali_month_options() as $option ) {
			$months[] = array(
				'value'    => $option['value'],
				'label'    => $option['label'],
				'selected' => $option['value'] === $month,
			);
		}

		return array(
			'year'               => $year,
			'month'              => $month,
			'day'                => $day,
			'years'              => $years,
			'months'             => $months,
			'days'               => self::day_options_for_filter( $year, $month, $day ),
			'month_lengths_json' => wp_json_encode( self::month_lengths_map( $year_start, $year_end ) ) ?: '{}',
		);
	}

	public static function preserve_date_filter_in_pagination( string $link ): string {
		if ( ! self::is_archive_request() ) {
			return $link;
		}

		$args = self::get_active_date_filter_query_args();
		if ( $args === array() ) {
			return $link;
		}

		return add_query_arg( $args, $link );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function build_date_filter_context( string $action_url ): array {
		$active = self::get_active_date_filter();
		$fields = self::jalali_filter_form_fields( $active ?? array() );

		return array_merge(
			$fields,
			array(
				'action_url' => $action_url,
				'clear_url'  => remove_query_arg( array_merge( self::DATE_QUERY_KEYS, array( 'paged' ) ), $action_url ),
				'is_active'  => $active !== null,
			)
		);
	}

	/**
	 * @return list<array{value: int, label: string, selected: bool}>
	 */
	private static function day_options_for_filter( int $year, int $month, int $selected_day ): array {
		$days = array(
			array(
				'value'    => 0,
				'label'    => 'همه روزها',
				'selected' => false,
			),
		);

		if ( $year <= 0 || $month <= 0 ) {
			$days[0]['selected'] = true;

			return $days;
		}

		$max_day = PersianDate::jalali_month_length( $year, $month );

		$days[0]['selected'] = $selected_day <= 0;

		for ( $candidate = 1; $candidate <= $max_day; ++$candidate ) {
			$days[] = array(
				'value'    => $candidate,
				'label'    => PersianDigits::convert( (string) $candidate ),
				'selected' => $candidate === $selected_day,
			);
		}

		return $days;
	}

	/**
	 * @return array<string, array<int, int>>
	 */
	private static function month_lengths_map( int $year_start, int $year_end ): array {
		$map = array();
		for ( $year = $year_start; $year <= $year_end; ++$year ) {
			$map[ (string) $year ] = array();
			for ( $month = 1; $month <= 12; ++$month ) {
				$map[ (string) $year ][ $month ] = PersianDate::jalali_month_length( $year, $month );
			}
		}

		return $map;
	}

	/**
	 * @return array{year: int, month: int, day: int}|null
	 */
	private static function get_active_date_filter(): ?array {
		return self::active_jalali_filter_from_request();
	}

	/**
	 * @return array<string, int>
	 */
	private static function get_active_date_filter_query_args(): array {
		$filter = self::get_active_date_filter();
		if ( $filter === null ) {
			return array();
		}

		$args = array(
			'jyear' => $filter['year'],
		);

		if ( $filter['month'] > 0 ) {
			$args['jmonth'] = $filter['month'];
		}

		if ( $filter['day'] > 0 ) {
			$args['jday'] = $filter['day'];
		}

		return $args;
	}

	private static function current_archive_url(): string {
		if ( is_category() ) {
			$link = get_term_link( get_queried_object_id(), 'category' );

			return is_string( $link ) && ! is_wp_error( $link ) ? $link : home_url( '/' );
		}

		if ( is_tag() ) {
			$link = get_term_link( get_queried_object_id(), 'post_tag' );

			return is_string( $link ) && ! is_wp_error( $link ) ? $link : home_url( '/' );
		}

		if ( is_author() ) {
			$link = get_author_posts_url( (int) get_queried_object_id() );

			return $link !== '' ? $link : home_url( '/' );
		}

		if ( is_home() ) {
			$posts_page = (int) get_option( 'page_for_posts' );
			if ( $posts_page > 0 ) {
				$link = get_permalink( $posts_page );
				if ( is_string( $link ) && $link !== '' ) {
					return $link;
				}
			}
		}

		$archive = get_post_type_archive_link( 'post' );

		return is_string( $archive ) && $archive !== '' ? $archive : home_url( '/' );
	}

	private static function matches_news_archive_query( \WP_Query $query ): bool {
		if ( $query->is_home() ) {
			return true;
		}

		if ( ! $query->is_archive() ) {
			return false;
		}

		if ( $query->is_category() || $query->is_tag() || $query->is_author() ) {
			return true;
		}

		$post_type = $query->get( 'post_type' );
		if ( is_array( $post_type ) ) {
			return $post_type === array( 'post' );
		}

		return $post_type === '' || $post_type === 'post';
	}

	private static function get_active_panel(): string {
		if ( is_category() ) {
			return 'category';
		}

		if ( is_tag() ) {
			return 'tag';
		}

		return 'news';
	}

	public static function get_archive_title(): string {
		if ( function_exists( 'get_field' ) ) {
			$title = get_field( 'news_archive_title', 'option' );
			if ( is_string( $title ) && trim( $title ) !== '' ) {
				return trim( $title );
			}
		}

		return __( 'اخبار خودرو', 'asrekhodro' );
	}

	public static function get_archive_subtitle(): string {
		return self::get_subtitle_for_panel( 'news' );
	}

	private static function get_subtitle_for_panel( string $panel ): string {
		$map = array(
			'category' => array(
				'field'   => 'category_archive_subtitle',
				'default' => __( 'آخرین اخبار این دسته‌بندی', 'asrekhodro' ),
			),
			'tag'      => array(
				'field'   => 'tag_archive_subtitle',
				'default' => __( 'آخرین اخبار این برچسب', 'asrekhodro' ),
			),
			'news'     => array(
				'field'   => 'news_archive_subtitle',
				'default' => __( 'آخرین اخبار منتشر شده', 'asrekhodro' ),
			),
		);

		$config = $map[ $panel ] ?? $map['news'];

		if ( function_exists( 'get_field' ) ) {
			$subtitle = get_field( $config['field'], 'option' );
			if ( is_string( $subtitle ) && trim( $subtitle ) !== '' ) {
				return trim( $subtitle );
			}
		}

		return $config['default'];
	}

	public static function get_archive_hero_image_url(): string {
		return self::get_hero_for_panel( 'news' )['url'];
	}

	/**
	 * @return array{url: string, aspect: string, aspect_fallback: bool}
	 */
	private static function get_hero_for_panel( string $panel ): array {
		$map = array(
			'category' => 'category_archive_hero_image',
			'tag'      => 'tag_archive_hero_image',
			'news'     => 'news_archive_hero_image',
		);

		$field = $map[ $panel ] ?? $map['news'];

		return ArchiveHero::from_acf_option( $field );
	}

	public static function get_hero_title( string $wp_title = '' ): string {
		if ( is_category() ) {
			$label = single_cat_title( '', false );

			return is_string( $label ) && $label !== '' ? $label : self::get_archive_title();
		}

		if ( is_tag() ) {
			$label = single_tag_title( '', false );

			return is_string( $label ) && $label !== '' ? $label : self::get_archive_title();
		}

		if ( is_tax() ) {
			$label = single_term_title( '', false );

			return is_string( $label ) && $label !== '' ? $label : self::get_archive_title();
		}

		if ( is_author() ) {
			$label = get_the_author();

			return is_string( $label ) && $label !== '' ? $label : self::get_archive_title();
		}

		if ( is_date() ) {
			$label = trim( wp_strip_all_tags( $wp_title ) );

			return $label !== '' ? $label : self::get_archive_title();
		}

		return self::get_archive_title();
	}
}
