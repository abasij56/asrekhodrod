<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Theme {

	public static function init(): void {
		Appearance::init();

		require_once ASREKHODRO_THEME_DIR . '/inc/Setup.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/PostTypes.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/ImporterBridge.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/PersianDate.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/PersianDigits.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/ExternalMedia.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/LegacyRedirects.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/Magazines.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/ArchiveHero.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/NewsArchive.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/NavMenu.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/ReviewsArchive.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/VideosArchive.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/NewsPermalinks.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/VideoPermalinks.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/HomepageData.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/blocks/load.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/ThemeCategories.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/RfPage.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/SinglePost.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/PostOverTitleMeta.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/VideoSingle.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/PostViews.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/SidebarWidgets.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/AjaxSearch.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/ContactForm.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/ContactPage.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/Comments.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/AboutPage.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/LoginPage.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/AcfFields.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/FooterSocial.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/CarsInfo.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/CarsInfoDirectory.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/CarInfo3d.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/ThemeModels.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/CinfoToc.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/AdminPostList.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/AdminNewsDateFilter.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/AdminAds.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/AdminCategoryParent.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/CarBrandAssets.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/AdminCategoryBrandLogo.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/Appearances/ClassicAppearance.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/Appearances/ModernAppearance.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/Appearances/ModernBlueAppearance.php';
		require_once ASREKHODRO_THEME_DIR . '/inc/Appearances/RfardaAppearance.php';

		Appearances\RfardaAppearance::init();
		BlockDataResolver::init();
		LayoutResolver::init();
		LayoutBuilderAdmin::init();

		Setup::init();
		PostTypes::init();
		CarsInfo::init();
		CarInfo3d::init();
		CinfoToc::init();
		PersianDigits::init();
		ImporterBridge::init();
		Magazines::init();
		NewsArchive::init();
		ReviewsArchive::init();
		VideosArchive::init();
		NewsPermalinks::init();
		VideoPermalinks::init();
		ExternalMedia::init();
		LegacyRedirects::init();
		PostViews::init();
		AjaxSearch::init();
		AdminPostList::init();
		AdminNewsDateFilter::init();
		AdminAds::init();
		AdminCategoryParent::init();
		AdminCategoryBrandLogo::init();
		ContactForm::init();
		Comments::init();
		SinglePost::init();
		PostOverTitleMeta::init();
		AboutPage::init();
		LoginPage::init();

		add_action( 'after_setup_theme', array( AcfFields::class, 'init' ) );
		add_action( 'acf/init', array( CinfoBlocks::class, 'init' ) );
		add_action( 'acf/include_fields', array( CinfoBlocks::class, 'register_field_groups' ) );

		add_filter( 'timber/context', array( self::class, 'global_context' ) );
	}

	/**
	 * @param array<string, mixed> $context
	 * @return array<string, mixed>
	 */
	public static function global_context( array $context ): array {
		$context['site']            = \Timber\Timber::context()['site'] ?? $context['site'] ?? null;
		$context['theme_uri']       = ASREKHODRO_THEME_URI;
		$context['appearance_id']   = Appearance::id();
		$context['appearance_uri']  = Appearance::uri();
		$context['options']  = function_exists( 'get_fields' ) ? ( get_fields( 'option' ) ?: array() ) : array();
		$context['menu_strip_ads']   = ImporterBridge::get_ads_by_position( 'menu_strip', 3 );
		$context['sidebar_ads']      = ImporterBridge::get_ads_by_position( 'sidebar_left', 40 );
		$context['main_nav']         = \Timber\Timber::get_menu( 'main-nav' );
		$context['main_nav_items']   = NavMenu::items();
		$context['nav_date_filter']  = NewsArchive::nav_date_filter_context();
		$context['footer_nav_about'] = \Timber\Timber::get_menu( 'footer-about' );
		$context['footer_nav_cats']  = \Timber\Timber::get_menu( 'footer-categories' );
		$context['footer_about_links']      = self::resolve_footer_links(
			$context['options']['footer_about_links'] ?? null,
			$context['footer_nav_about']
		);
		$context['footer_categories_links'] = self::resolve_footer_links(
			$context['options']['footer_categories_links'] ?? null,
			$context['footer_nav_cats']
		);
		$context['footer_certificates']     = self::resolve_footer_certificates( $context['options'] );
		$context['footer_social_links']     = FooterSocial::links_for_footer( $context['options'] );
		$context['body_class']       = implode( ' ', get_body_class() );
		$context['today_persian']    = PersianDate::format_date( time() );
		$context['today_iso']        = wp_date( 'Y-m-d' );
		$context['google_follow_url'] = is_front_page() ? '' : self::google_follow_url( $context['options'] );

		return $context;
	}

	private static function google_follow_url( array $options ): string {
		$url = trim( (string) ( $options['google_follow_url'] ?? '' ) );
		if ( $url !== '' ) {
			return esc_url( $url );
		}

		$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		if ( ! is_string( $host ) || $host === '' ) {
			return '';
		}

		return esc_url( 'https://google.com/preferences/source?q=' . rawurlencode( $host ) );
	}

	/**
	 * Footer column links from theme options, with WP menu fallback when repeaters are empty.
	 *
	 * @param mixed $repeater ACF repeater rows.
	 * @param mixed $menu     Timber menu.
	 * @return list<array{title: string, url: string}>
	 */
	private static function resolve_footer_links( $repeater, $menu ): array {
		$links = self::normalize_footer_repeater_links( $repeater );
		if ( $links !== array() ) {
			return $links;
		}

		return self::footer_links_from_menu( $menu );
	}

	/**
	 * @param mixed $rows
	 * @return list<array{title: string, url: string}>
	 */
	private static function normalize_footer_repeater_links( $rows ): array {
		if ( ! is_array( $rows ) || $rows === array() ) {
			return array();
		}

		$links = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$title = trim( (string) ( $row['link_title'] ?? '' ) );
			$url   = trim( (string) ( $row['link_url'] ?? '' ) );
			if ( $title === '' || $url === '' ) {
				continue;
			}

			$links[] = array(
				'title' => $title,
				'url'   => $url,
			);
		}

		return $links;
	}

	/**
	 * @param mixed $menu
	 * @return list<array{title: string, url: string}>
	 */
	private static function footer_links_from_menu( $menu ): array {
		if ( ! $menu || empty( $menu->items ) || ! is_iterable( $menu->items ) ) {
			return array();
		}

		$links = array();
		foreach ( $menu->items as $item ) {
			$title = trim( (string) ( $item->title ?? '' ) );
			$url   = trim( (string) ( $item->link ?? '' ) );
			if ( $title === '' || $url === '' ) {
				continue;
			}

			$links[] = array(
				'title' => $title,
				'url'   => $url,
			);
		}

		return $links;
	}

	/**
	 * Footer certificate badges from theme options (with legacy e-Rasaneh fallback).
	 *
	 * @param array<string, mixed> $options
	 * @return list<array{title: string, alt: string, url: string, image: string}>
	 */
	private static function resolve_footer_certificates( array $options ): array {
		$certificates = self::normalize_footer_certificates( $options['footer_certificates'] ?? null );
		if ( $certificates !== array() ) {
			return $certificates;
		}

		$legacy_enabled = $options['footer_eresane_enable'] ?? true;
		if ( ! $legacy_enabled ) {
			return array();
		}

		$alt   = trim( (string) ( $options['footer_eresane_alt'] ?? 'e-Rasaneh' ) );
		$url   = trim( (string) ( $options['footer_eresane_url'] ?? 'https://e-rasaneh.ir' ) );
		$image = '';
		if ( is_array( $options['footer_eresane_image'] ?? null ) && ! empty( $options['footer_eresane_image']['url'] ) ) {
			$image = (string) $options['footer_eresane_image']['url'];
		}
		if ( $image === '' ) {
			$image = 'https://e-rasaneh.ir/static/img/logo.jpg';
		}

		if ( $url === '' && $image === '' ) {
			return array();
		}

		return array(
			array(
				'title' => $alt !== '' ? $alt : 'e-Rasaneh',
				'alt'   => $alt !== '' ? $alt : 'e-Rasaneh',
				'url'   => $url,
				'image' => $image,
			),
		);
	}

	/**
	 * @param mixed $rows
	 * @return list<array{title: string, alt: string, url: string, image: string}>
	 */
	private static function normalize_footer_certificates( $rows ): array {
		if ( ! is_array( $rows ) || $rows === array() ) {
			return array();
		}

		$certificates = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$title = trim( (string) ( $row['cert_title'] ?? '' ) );
			$alt   = trim( (string) ( $row['cert_alt'] ?? '' ) );
			$url   = trim( (string) ( $row['cert_url'] ?? '' ) );
			$image = '';
			if ( is_array( $row['cert_image'] ?? null ) && ! empty( $row['cert_image']['url'] ) ) {
				$image = trim( (string) $row['cert_image']['url'] );
			}
			if ( $image === '' ) {
				$image = trim( (string) ( $row['cert_image_url'] ?? '' ) );
			}

			if ( $title === '' || $image === '' ) {
				continue;
			}

			$certificates[] = array(
				'title' => $title,
				'alt'   => $alt !== '' ? $alt : $title,
				'url'   => $url,
				'image' => $image,
			);
		}

		return $certificates;
	}
}
