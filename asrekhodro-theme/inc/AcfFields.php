<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AcfFields {

	public static function init(): void {
		if ( ! function_exists( 'acf_add_options_page' ) ) {
			return;
		}

		acf_add_options_page(
			array(
				'page_title' => 'تنظیمات عصر خودرو',
				'menu_title' => 'تنظیمات تم',
				'menu_slug'  => 'asrekhodro-settings',
				'capability' => 'edit_theme_options',
				'redirect'   => false,
			)
		);

		add_action( 'acf/include_fields', array( self::class, 'register_field_groups' ) );
		add_action( 'acf/input/admin_enqueue_scripts', array( self::class, 'enqueue_admin_assets' ) );
		add_filter( 'acf/load_field/key=field_ak_carsinfo_country_category', array( self::class, 'load_carsinfo_category_select_field' ) );
		add_filter( 'acf/load_field/key=field_ak_carsinfo_brand_category', array( self::class, 'load_carsinfo_category_select_field' ) );
		add_filter( 'acf/load_field/key=field_ak_category_brand_logo', array( CarBrandAssets::class, 'load_acf_select_field' ) );
	}

	public static function enqueue_admin_assets(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || $screen->id !== 'toplevel_page_asrekhodro-settings' ) {
			return;
		}

		$css_path = ASREKHODRO_THEME_DIR . '/assets/admin/theme-options.css';
		if ( ! is_readable( $css_path ) ) {
			return;
		}

		wp_enqueue_style(
			'asrekhodro-theme-options',
			ASREKHODRO_THEME_URI . '/assets/admin/theme-options.css',
			array( 'acf-input' ),
			(string) filemtime( $css_path )
		);

		$js_path = ASREKHODRO_THEME_DIR . '/assets/admin/theme-options.js';
		if ( is_readable( $js_path ) ) {
			wp_enqueue_script(
				'asrekhodro-theme-options',
				ASREKHODRO_THEME_URI . '/assets/admin/theme-options.js',
				array( 'jquery', 'acf-input' ),
				(string) filemtime( $js_path ),
				true
			);
			wp_localize_script(
				'asrekhodro-theme-options',
				'akThemeOptions',
				array(
					'socialSvgDefaults' => FooterSocial::default_svgs_for_admin(),
				)
			);
		}
	}

	public static function register_field_groups(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group(
			array(
				'key'    => 'group_ak_theme_options',
				'title'  => 'تنظیمات تم',
				/**
				 * Allow modules to append their own tabs/fields to the theme
				 * options group (keeps them as vertical tabs in one box).
				 *
				 * @param array<int, array<string, mixed>> $fields
				 */
				'fields' => apply_filters(
					'ak_theme_options_fields',
					array_merge(
						self::general_option_fields(),
						self::homepage_option_fields(),
						self::archive_option_fields(),
						self::contact_option_fields(),
						self::carsinfo_option_fields(),
						self::footer_option_fields()
					)
				),
				'location' => array(
					array(
						array(
							'param'    => 'options_page',
							'operator' => '==',
							'value'    => 'asrekhodro-settings',
						),
					),
				),
			)
		);

		acf_add_local_field_group(
			array(
				'key'      => 'group_ak_category_term',
				'title'    => 'تصویر دسته‌بندی',
				'fields'   => self::category_term_fields(),
				'location' => array(
					array(
						array(
							'param'    => 'taxonomy',
							'operator' => '==',
							'value'    => 'category',
						),
					),
				),
			)
		);

		acf_add_local_field_group(
			array(
				'key'    => 'group_ak_ad_slot',
				'title'  => 'فیلدهای تبلیغ',
				'fields' => array(
					array(
						'key'   => 'field_ak_ad_label',
						'label' => 'برچسب نمایش',
						'name'  => 'ad_label',
						'type'  => 'text',
					),
					array(
						'key'   => 'field_ak_ad_link',
						'label' => 'نشانی لینک',
						'name'  => 'ad_link',
						'type'  => 'url',
					),
					array(
						'key'   => 'field_ak_ad_image',
						'label' => 'تصویر (اختیاری)',
						'name'  => 'ad_image',
						'type'  => 'image',
						'return_format' => 'array',
					),
					array(
						'key'           => 'field_ak_ad_active',
						'label'         => 'فعال',
						'name'          => 'ad_active',
						'type'          => 'true_false',
						'default_value' => 1,
						'ui'            => 1,
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'ad_slot',
						),
					),
				),
			)
		);

		acf_add_local_field_group(
			array(
				'key'    => 'group_ak_video_fields',
				'title'  => 'ویدیو',
				'fields' => array(
					array(
						'key'          => 'field_ak_video_url',
						'label'        => 'نشانی ویدیو',
						'name'         => 'video_url',
						'type'         => 'url',
						'instructions' => 'نشانی مستقیم MP4/FLV روی media.asrekhodro.com (واردشده از CMS قدیمی).',
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'ak_video',
						),
					),
				),
			)
		);

		acf_add_local_field_group(
			array(
				'key'    => 'group_ak_magazine_fields',
				'title'  => 'کاور مجله',
				'fields' => array(
					array(
						'key'           => 'field_ak_magazine_cover',
						'label'         => 'نشانی تصویر کاور',
						'name'          => 'magazine_cover',
						'type'          => 'url',
						'instructions'  => 'نشانی خارجی اختیاری برای کاور (media.asrekhodro.com). تصویر شاخص اولویت دارد.',
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'ak_magazine',
						),
					),
				),
			)
		);

		acf_add_local_field_group(
			array(
				'key'    => 'group_ak_post_legacy',
				'title'  => 'فیلدهای قدیمی عصر خودرو',
				'fields' => array(
					array(
						'key'   => 'field_ak_under_title',
						'label' => 'تیتر دوم',
						'name'  => 'under_title',
						'type'  => 'text',
					),
					array(
						'key'           => 'field_ak_related_posts',
						'label'         => 'اخبار مرتبط',
						'name'          => 'related_posts',
						'type'          => 'relationship',
						'post_type'     => array( 'post' ),
						'filters'       => array( 'search' ),
						'return_format' => 'id',
						'min'           => 0,
						'max'           => 20,
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'post',
						),
					),
				),
			)
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function general_option_fields(): array {
		return array(
			array(
				'key'       => 'field_ak_tab_general',
				'label'     => 'عمومی',
				'name'      => '',
				'type'      => 'tab',
				'placement' => 'left',
			),
			array(
				'key'           => 'field_ak_active_appearance',
				'label'         => 'ظاهر تم',
				'name'          => 'active_appearance',
				'type'          => 'select',
				'choices'       => self::appearance_choices(),
				'default_value' => self::appearance_default(),
				'ui'            => 1,
				'instructions'  => 'ظاهر کلی سایت (قالب، CSS و Twig). گزینه‌ها از manifest هر پوشه در appearances/ خوانده می‌شوند. چیدمان بلاک‌ها: <a href="' . esc_url( admin_url( 'admin.php?page=asrekhodro-layout-builder' ) ) . '">صفحه چیدمان صفحات</a>.',
			),
			array(
				'key'           => 'field_ak_site_logo',
				'label'         => 'لوگوی سایت (هدر)',
				'name'          => 'site_logo',
				'type'          => 'image',
				'return_format' => 'array',
				'preview_size'  => 'medium',
				'instructions'  => 'لوگوی اصلی سایت در هدر. در صورت خالی بودن، لوگوی پیش‌فرض نمایش داده می‌شود.',
			),
			array(
				'key'           => 'field_ak_site_favicon',
				'label'         => 'فاویکون (۳۲×۳۲)',
				'name'          => 'site_favicon',
				'type'          => 'image',
				'return_format' => 'array',
				'preview_size'  => 'thumbnail',
				'instructions'  => 'آیکون مرورگر — PNG یا ICO، 32×32 پیکسل.',
			),
			array(
				'key'           => 'field_ak_site_favicon_16',
				'label'         => 'فاویکون (۱۶×۱۶)',
				'name'          => 'site_favicon_16',
				'type'          => 'image',
				'return_format' => 'array',
				'preview_size'  => 'thumbnail',
				'instructions'  => 'آیکون کوچک مرورگر — 16×16 پیکسل.',
			),
			array(
				'key'           => 'field_ak_site_apple_touch_icon',
				'label'         => 'آیکون Apple Touch',
				'name'          => 'site_apple_touch_icon',
				'type'          => 'image',
				'return_format' => 'array',
				'preview_size'  => 'thumbnail',
				'instructions'  => 'آیکون iOS / iPadOS — 180×180 پیکسل.',
			),
			array(
				'key'           => 'field_ak_site_icon_192',
				'label'         => 'آیکون اندروید / PWA (۱۹۲×۱۹۲)',
				'name'          => 'site_icon_192',
				'type'          => 'image',
				'return_format' => 'array',
				'preview_size'  => 'thumbnail',
			),
			array(
				'key'           => 'field_ak_site_icon_512',
				'label'         => 'آیکون PWA (۵۱۲×۵۱۲)',
				'name'          => 'site_icon_512',
				'type'          => 'image',
				'return_format' => 'array',
				'preview_size'  => 'thumbnail',
			),
			array(
				'key'           => 'field_ak_site_ms_tile_image',
				'label'         => 'تصویر کاشی ویندوز',
				'name'          => 'site_ms_tile_image',
				'type'          => 'image',
				'return_format' => 'array',
				'preview_size'  => 'thumbnail',
				'instructions'  => 'آیکون Windows Start / Pin — 270×270 یا 144×144 پیکسل.',
			),
			array(
				'key'           => 'field_ak_site_ms_tile_color',
				'label'         => 'رنگ کاشی ویندوز',
				'name'          => 'site_ms_tile_color',
				'type'          => 'color_picker',
				'default_value' => '#e10600',
			),
			array(
				'key'           => 'field_ak_site_theme_color',
				'label'         => 'رنگ نوار مرورگر',
				'name'          => 'site_theme_color',
				'type'          => 'color_picker',
				'default_value' => '#e10600',
				'instructions'  => 'رنگ نوار مرورگر در موبایل (meta theme-color).',
			),
			array(
				'key'          => 'field_ak_google_follow_url',
				'label'        => 'Google Follow — نشانی',
				'name'         => 'google_follow_url',
				'type'         => 'url',
				'instructions' => 'لینک دکمه «ما را در گوگل دنبال کنید» در هدر. در صورت خالی بودن، از deeplink رسمی Google Preferred Sources برای دامنه سایت استفاده می‌شود.',
			),
			array(
				'key'       => 'field_ak_tab_login',
				'label'     => 'صفحه ورود',
				'name'      => '',
				'type'      => 'tab',
				'placement' => 'left',
			),
			array(
				'key'           => 'field_ak_login_hero_image',
				'label'         => 'تصویر پس‌زمینه صفحه ورود',
				'name'          => 'login_hero_image',
				'type'          => 'image',
				'return_format' => 'array',
				'preview_size'  => 'medium',
				'instructions'  => 'تصویر پس‌زمینه نیمه چپ صفحه ورود. در صورت خالی بودن، تصویر پیش‌فرض تم استفاده می‌شود.',
			),
			array(
				'key'           => 'field_ak_login_hero_title',
				'label'         => 'عنوان صفحه ورود',
				'name'          => 'login_hero_title',
				'type'          => 'text',
				'default_value' => 'مرجع تخصصی اخبار خودرو',
				'instructions'  => 'عنوان روی تصویر صفحه ورود.',
			),
			array(
				'key'           => 'field_ak_login_hero_subtitle',
				'label'         => 'زیرعنوان صفحه ورود',
				'name'          => 'login_hero_subtitle',
				'type'          => 'textarea',
				'rows'          => 2,
				'default_value' => 'آخرین اخبار، بررسی و تحلیل صنعت خودرو در ایران',
				'instructions'  => 'زیرعنوان روی تصویر صفحه ورود.',
			),
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function homepage_option_fields(): array {
		return array(
			array(
				'key'       => 'field_ak_tab_homepage',
				'label'     => 'صفحه اول',
				'name'      => '',
				'type'      => 'tab',
				'placement' => 'left',
			),
			array(
				'key'   => 'field_ak_share_instagram',
				'label' => 'باکس اشتراک اینستاگرام (خودرو امروز)',
				'name'  => 'share_instagram',
				'type'  => 'url',
			),
			array(
				'key'   => 'field_ak_share_telegram',
				'label' => 'باکس اشتراک کانال تلگرام',
				'name'  => 'share_telegram',
				'type'  => 'url',
			),
			array(
				'key'   => 'field_ak_share_telegram_dl',
				'label' => 'باکس اشتراک دانلود تلگرام',
				'name'  => 'share_telegram_download',
				'type'  => 'url',
			),
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function archive_option_fields(): array {
		return array(
			array(
				'key'       => 'field_ak_tab_archive',
				'label'     => 'صفحات آرشیو',
				'name'      => '',
				'type'      => 'tab',
				'placement' => 'left',
			),
			array(
				'key'           => 'field_ak_archive_panel',
				'label'         => '',
				'name'          => 'archive_settings_panel',
				'type'          => 'button_group',
				'choices'       => array(
					'news'     => 'اخبار خودرو',
					'category' => 'آرشیو کتگوری',
					'tag'      => 'آرشیو تگ',
					'kiosk'    => 'دکه مطبوعات',
					'review'   => 'آرشیو تست و بررسی',
					'video'    => 'آرشیو ویدیو',
				),
				'default_value' => 'news',
				'layout'        => 'horizontal',
				'return_format' => 'value',
				'wrapper'       => array(
					'class' => 'ak-archive-panel-switcher',
				),
			),
			array(
				'key'           => 'field_ak_news_archive_hero_image',
				'label'         => 'تصویر بنر هدر اخبار خودرو',
				'name'          => 'news_archive_hero_image',
				'type'          => 'image',
				'return_format' => 'array',
				'preview_size'  => 'medium',
				'instructions'  => 'تصویر پس‌زمینه بنر بالای صفحه آرشیو اخبار. در صورت خالی بودن، تصویر پیش‌فرض تم استفاده می‌شود.',
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_ak_archive_panel',
							'operator' => '==',
							'value'    => 'news',
						),
					),
				),
			),
			array(
				'key'               => 'field_ak_news_archive_title',
				'label'             => 'عنوان صفحه',
				'name'              => 'news_archive_title',
				'type'              => 'text',
				'default_value'     => 'اخبار خودرو',
				'instructions'      => 'عنوان اصلی بنر هدر صفحه آرشیو اخبار خودرو.',
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_ak_archive_panel',
							'operator' => '==',
							'value'    => 'news',
						),
					),
				),
			),
			array(
				'key'               => 'field_ak_news_archive_subtitle',
				'label'             => 'زیرعنوان صفحه',
				'name'              => 'news_archive_subtitle',
				'type'              => 'text',
				'default_value'     => 'آخرین اخبار منتشر شده',
				'instructions'      => 'زیرعنوان نمایش‌داده‌شده زیر عنوان در بنر هدر.',
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_ak_archive_panel',
							'operator' => '==',
							'value'    => 'news',
						),
					),
				),
			),
			array(
				'key'               => 'field_ak_news_archive_posts_per_page',
				'label'             => 'تعداد نمایش اخبار در هر صفحه',
				'name'              => 'news_archive_posts_per_page',
				'type'              => 'number',
				'default_value'     => 40,
				'min'               => 1,
				'max'               => 100,
				'step'              => 1,
				'instructions'      => 'صفحه آرشیو اخبار خودرو (صفحه وبلاگ).',
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_ak_archive_panel',
							'operator' => '==',
							'value'    => 'news',
						),
					),
				),
			),
			array(
				'key'           => 'field_ak_category_archive_hero_image',
				'label'         => 'تصویر بنر هدر آرشیو کتگوری',
				'name'          => 'category_archive_hero_image',
				'type'          => 'image',
				'return_format' => 'array',
				'preview_size'  => 'medium',
				'instructions'  => 'تصویر پس‌زمینه بنر صفحات آرشیو دسته‌بندی. عنوان بنر از نام همان دسته‌بندی گرفته می‌شود. در صورت خالی بودن، تصویر پیش‌فرض تم استفاده می‌شود.',
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_ak_archive_panel',
							'operator' => '==',
							'value'    => 'category',
						),
					),
				),
			),
			array(
				'key'               => 'field_ak_category_archive_subtitle',
				'label'             => 'زیرعنوان صفحه',
				'name'              => 'category_archive_subtitle',
				'type'              => 'text',
				'default_value'     => 'آخرین اخبار این دسته‌بندی',
				'instructions'      => 'زیرعنوان نمایش‌داده‌شده زیر نام دسته‌بندی در بنر هدر.',
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_ak_archive_panel',
							'operator' => '==',
							'value'    => 'category',
						),
					),
				),
			),
			array(
				'key'           => 'field_ak_tag_archive_hero_image',
				'label'         => 'تصویر بنر هدر آرشیو تگ',
				'name'          => 'tag_archive_hero_image',
				'type'          => 'image',
				'return_format' => 'array',
				'preview_size'  => 'medium',
				'instructions'  => 'تصویر پس‌زمینه بنر صفحات آرشیو برچسب. عنوان بنر از نام همان برچسب گرفته می‌شود. در صورت خالی بودن، تصویر پیش‌فرض تم استفاده می‌شود.',
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_ak_archive_panel',
							'operator' => '==',
							'value'    => 'tag',
						),
					),
				),
			),
			array(
				'key'               => 'field_ak_tag_archive_subtitle',
				'label'             => 'زیرعنوان صفحه',
				'name'              => 'tag_archive_subtitle',
				'type'              => 'text',
				'default_value'     => 'آخرین اخبار این برچسب',
				'instructions'      => 'زیرعنوان نمایش‌داده‌شده زیر نام برچسب در بنر هدر.',
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_ak_archive_panel',
							'operator' => '==',
							'value'    => 'tag',
						),
					),
				),
			),
			array(
				'key'           => 'field_ak_kiosk_archive_hero_image',
				'label'         => 'تصویر بنر هدر دکه مطبوعات',
				'name'          => 'kiosk_archive_hero_image',
				'type'          => 'image',
				'return_format' => 'array',
				'preview_size'  => 'medium',
				'instructions'  => 'تصویر پس‌زمینه بنر بالای صفحه آرشیو مجلات. در صورت خالی بودن، تصویر پیش‌فرض تم استفاده می‌شود.',
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_ak_archive_panel',
							'operator' => '==',
							'value'    => 'kiosk',
						),
					),
				),
			),
			array(
				'key'               => 'field_ak_kiosk_archive_title',
				'label'             => 'عنوان صفحه',
				'name'              => 'kiosk_archive_title',
				'type'              => 'text',
				'default_value'     => 'دکه مطبوعات',
				'instructions'      => 'عنوان اصلی بنر هدر صفحه آرشیو دکه مطبوعات.',
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_ak_archive_panel',
							'operator' => '==',
							'value'    => 'kiosk',
						),
					),
				),
			),
			array(
				'key'               => 'field_ak_kiosk_archive_subtitle',
				'label'             => 'زیرعنوان صفحه',
				'name'              => 'kiosk_archive_subtitle',
				'type'              => 'text',
				'default_value'     => 'آخرین مجلات اضافه شده',
				'instructions'      => 'زیرعنوان نمایش‌داده‌شده زیر عنوان در بنر هدر.',
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_ak_archive_panel',
							'operator' => '==',
							'value'    => 'kiosk',
						),
					),
				),
			),
			array(
				'key'               => 'field_ak_magazine_archive_posts_per_page',
				'label'             => 'تعداد نمایش مجلات در هر صفحه',
				'name'              => 'magazine_archive_posts_per_page',
				'type'              => 'number',
				'default_value'     => 24,
				'min'               => 1,
				'max'               => 100,
				'step'              => 1,
				'instructions'      => 'صفحه آرشیو دکه مطبوعات (مجلات).',
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_ak_archive_panel',
							'operator' => '==',
							'value'    => 'kiosk',
						),
					),
				),
			),
			array(
				'key'           => 'field_ak_review_archive_hero_image',
				'label'         => 'تصویر بنر هدر آرشیو تست و بررسی',
				'name'          => 'review_archive_hero_image',
				'type'          => 'image',
				'return_format' => 'array',
				'preview_size'  => 'medium',
				'instructions'  => 'تصویر پس‌زمینه بنر بالای صفحه آرشیو تست و بررسی. در صورت خالی بودن، تصویر پیش‌فرض تم استفاده می‌شود.',
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_ak_archive_panel',
							'operator' => '==',
							'value'    => 'review',
						),
					),
				),
			),
			array(
				'key'               => 'field_ak_review_archive_title',
				'label'             => 'عنوان صفحه',
				'name'              => 'review_archive_title',
				'type'              => 'text',
				'default_value'     => 'تست و بررسی خودرو',
				'instructions'      => 'عنوان اصلی بنر هدر صفحه آرشیو تست و بررسی.',
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_ak_archive_panel',
							'operator' => '==',
							'value'    => 'review',
						),
					),
				),
			),
			array(
				'key'               => 'field_ak_review_archive_subtitle',
				'label'             => 'زیرعنوان صفحه',
				'name'              => 'review_archive_subtitle',
				'type'              => 'text',
				'default_value'     => 'آخرین بررسی‌های منتشر شده',
				'instructions'      => 'زیرعنوان نمایش‌داده‌شده زیر عنوان در بنر هدر.',
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_ak_archive_panel',
							'operator' => '==',
							'value'    => 'review',
						),
					),
				),
			),
			array(
				'key'           => 'field_ak_video_archive_hero_image',
				'label'         => 'تصویر بنر هدر آرشیو ویدیو',
				'name'          => 'video_archive_hero_image',
				'type'          => 'image',
				'return_format' => 'array',
				'preview_size'  => 'medium',
				'instructions'  => 'تصویر پس‌زمینه بنر بالای صفحه آرشیو ویدیو. در صورت خالی بودن، تصویر پیش‌فرض تم استفاده می‌شود.',
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_ak_archive_panel',
							'operator' => '==',
							'value'    => 'video',
						),
					),
				),
			),
			array(
				'key'               => 'field_ak_video_archive_title',
				'label'             => 'عنوان صفحه',
				'name'              => 'video_archive_title',
				'type'              => 'text',
				'default_value'     => 'ویدئوهای عصر خودرو',
				'instructions'      => 'عنوان اصلی بنر هدر صفحه آرشیو ویدیو. در آرشیو دسته ویدیو، نام همان دسته نمایش داده می‌شود.',
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_ak_archive_panel',
							'operator' => '==',
							'value'    => 'video',
						),
					),
				),
			),
			array(
				'key'               => 'field_ak_video_archive_subtitle',
				'label'             => 'زیرعنوان صفحه',
				'name'              => 'video_archive_subtitle',
				'type'              => 'text',
				'default_value'     => 'آخرین ویدئوهای منتشر شده',
				'instructions'      => 'زیرعنوان نمایش‌داده‌شده زیر عنوان در بنر هدر.',
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_ak_archive_panel',
							'operator' => '==',
							'value'    => 'video',
						),
					),
				),
			),
			array(
				'key'               => 'field_ak_video_archive_posts_per_page',
				'label'             => 'تعداد نمایش ویدیوها در هر صفحه',
				'name'              => 'video_archive_posts_per_page',
				'type'              => 'number',
				'default_value'     => 24,
				'min'               => 1,
				'max'               => 100,
				'step'              => 1,
				'instructions'      => 'صفحه آرشیو ویدیو و دسته‌بندی ویدیو.',
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_ak_archive_panel',
							'operator' => '==',
							'value'    => 'video',
						),
					),
				),
			),
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function contact_option_fields(): array {
		return array(
			array(
				'key'       => 'field_ak_tab_contact',
				'label'     => 'تماس با ما',
				'name'      => '',
				'type'      => 'tab',
				'placement' => 'left',
			),
			array(
				'key'           => 'field_ak_contact_panel',
				'label'         => '',
				'name'          => 'contact_settings_panel',
				'type'          => 'button_group',
				'choices'       => array(
					'map'  => 'نقشه',
					'info' => 'اطلاعات تماس',
					'form' => 'فرم تماس',
				),
				'default_value' => 'map',
				'layout'        => 'horizontal',
				'return_format' => 'value',
				'wrapper'       => array(
					'class' => 'ak-contact-panel-switcher ak-theme-panel-switcher',
				),
			),
			...self::contact_panel_fields(),
		);
	}

	/**
	 * @param string $panel
	 * @return list<list<array<string, string>>>
	 */
	private static function contact_panel_condition( string $panel ): array {
		return array(
			array(
				array(
					'field'    => 'field_ak_contact_panel',
					'operator' => '==',
					'value'    => $panel,
				),
			),
		);
	}

	/**
	 * Contact page fields under Theme Settings → تماس با ما.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function contact_panel_fields(): array {
		$when_map  = self::contact_panel_condition( 'map' );
		$when_info = self::contact_panel_condition( 'info' );
		$when_form = self::contact_panel_condition( 'form' );

		return array(
			array(
				'key'               => 'field_ak_contact_page_title',
				'label'             => 'عنوان صفحه',
				'name'              => 'contact_page_title',
				'type'              => 'text',
				'default_value'     => 'با ما در تماس باشید',
				'instructions'      => 'عنوان مرکزی صفحه تماس (زیر نقشه).',
				'conditional_logic' => $when_map,
			),
			array(
				'key'               => 'field_ak_contact_map_lat',
				'label'             => 'عرض جغرافیایی نقشه',
				'name'              => 'contact_map_lat',
				'type'              => 'number',
				'step'              => 'any',
				'default_value'     => 35.7454587,
				'conditional_logic' => $when_map,
			),
			array(
				'key'               => 'field_ak_contact_map_lng',
				'label'             => 'طول جغرافیایی نقشه',
				'name'              => 'contact_map_lng',
				'type'              => 'number',
				'step'              => 'any',
				'default_value'     => 51.4142579,
				'conditional_logic' => $when_map,
			),
			array(
				'key'               => 'field_ak_contact_info_card_title',
				'label'             => 'عنوان کارت اطلاعات',
				'name'              => 'contact_info_card_title',
				'type'              => 'text',
				'default_value'     => 'اطلاعات تماس',
				'instructions'      => 'عنوان داخل کارت اطلاعات (بالای آدرس). در صورت خالی بودن نمایش داده نمی‌شود.',
				'conditional_logic' => $when_info,
			),
			array(
				'key'               => 'field_ak_contact_info_footer_note',
				'label'             => '',
				'name'              => '',
				'type'              => 'message',
				'message'           => 'نشانی، تلفن و ایمیل از تب <strong>تنظیمات فوتر → تماس</strong> در همین صفحه مدیریت می‌شوند.',
				'new_lines'         => 'wpautop',
				'esc_html'          => 0,
				'conditional_logic' => $when_info,
			),
			array(
				'key'               => 'field_ak_contact_postal_code',
				'label'             => 'کدپستی',
				'name'              => 'contact_postal_code',
				'type'              => 'text',
				'default_value'     => '1998994853',
				'conditional_logic' => $when_info,
			),
			array(
				'key'               => 'field_ak_contact_manager',
				'label'             => 'مدیر مسئول',
				'name'              => 'contact_manager',
				'type'              => 'text',
				'default_value'     => 'شهرام فرمانی',
				'conditional_logic' => $when_info,
			),
			array(
				'key'               => 'field_ak_contact_manager_note',
				'label'             => 'توضیح مدیر مسئول',
				'name'              => 'contact_manager_note',
				'type'              => 'text',
				'default_value'     => 'صاحب امتیاز گروه رسانه‌ای روز نو',
				'conditional_logic' => $when_info,
			),
			array(
				'key'               => 'field_ak_contact_intro',
				'label'             => 'متن راهنمای تماس',
				'name'              => 'contact_intro',
				'type'              => 'textarea',
				'rows'              => 6,
				'instructions'      => 'متن پایین کارت اطلاعات تماس.',
				'conditional_logic' => $when_info,
			),
			array(
				'key'               => 'field_ak_contact_form_card_title',
				'label'             => 'عنوان کارت فرم',
				'name'              => 'contact_form_card_title',
				'type'              => 'text',
				'default_value'     => 'ارسال پیام',
				'instructions'      => 'عنوان داخل کارت فرم (بالای فیلدها).',
				'conditional_logic' => $when_form,
			),
			array(
				'key'               => 'field_ak_contact_form_card_lead',
				'label'             => 'متن راهنمای فرم',
				'name'              => 'contact_form_card_lead',
				'type'              => 'textarea',
				'rows'              => 3,
				'instructions'      => 'متن کوتاه بالای فیلدهای فرم. در صورت خالی بودن نمایش داده نمی‌شود.',
				'conditional_logic' => $when_form,
			),
		);
	}

	/**
	 * Cars encyclopedia settings under Theme Settings.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function carsinfo_option_fields(): array {
		return array(
			array(
				'key'       => 'field_ak_tab_carsinfo',
				'label'     => 'تنظیمات دانشنامه خودرو',
				'name'      => '',
				'type'      => 'tab',
				'placement' => 'left',
			),
			array(
				'key'           => 'field_ak_carsinfo_settings_intro',
				'label'         => '',
				'name'          => '',
				'type'          => 'message',
				'message'       => 'کتگوری‌های وردپرس را برای ساختار دانشنامه خودرو مشخص کنید: یک کتگوری والد برای کشور (مثل ایران) و یک کتگوری برای برند (مثل ایران خودرو).',
				'new_lines'     => 'wpautop',
				'esc_html'      => 0,
			),
			array(
				'key'           => 'field_ak_carsinfo_country_category',
				'label'         => 'کتگوری کشور',
				'name'          => 'carsinfo_country_category',
				'type'          => 'select',
				'choices'       => array(),
				'default_value' => '',
				'allow_null'    => 1,
				'ui'            => 1,
				'ajax'          => 0,
				'return_format' => 'value',
				'instructions'  => '',
			),
			array(
				'key'           => 'field_ak_carsinfo_brand_category',
				'label'         => 'کتگوری برند',
				'name'          => 'carsinfo_brand_category',
				'type'          => 'select',
				'choices'       => array(),
				'default_value' => '',
				'allow_null'    => 1,
				'ui'            => 1,
				'ajax'          => 0,
				'return_format' => 'value',
				'instructions'  => '',
			),
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function category_term_fields(): array {
		return array(
			array(
				'key'           => 'field_ak_category_image',
				'label'         => 'تصویر',
				'name'          => 'ak_category_image',
				'type'          => 'image',
				'return_format' => 'id',
				'preview_size'  => 'thumbnail',
				'library'       => 'all',
				'instructions'  => 'اولویت اول در دانشنامه خودرو: اگر تصویر آپلود کنید، همان روی کارت برند نمایش داده می‌شود.',
			),
			array(
				'key'           => 'field_ak_category_brand_logo',
				'label'         => 'لوگوی آماده برند',
				'name'          => 'ak_category_brand_logo',
				'type'          => 'select',
				'choices'       => array(),
				'allow_null'    => 1,
				'ui'            => 0,
				'ajax'          => 0,
				'return_format' => 'value',
				'instructions'  => 'اولویت دوم در دانشنامه خودرو: فقط وقتی فیلد «تصویر» خالی باشد، لوگوی انتخاب‌شده از car-brands روی کارت برند می‌آید.',
			),
		);
	}

	/**
	 * Populate searchable category dropdowns on Theme Settings.
	 *
	 * @param array<string, mixed> $field
	 * @return array<string, mixed>
	 */
	public static function load_carsinfo_category_select_field( array $field ): array {
		$field['choices'] = self::category_select_choices();

		return $field;
	}

	/**
	 * @return array<string, string>
	 */
	private static function category_select_choices(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		$choices = array();
		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			$choices[ (string) $term->term_id ] = self::format_category_choice_label( $term );
		}

		return $choices;
	}

	private static function format_category_choice_label( \WP_Term $term ): string {
		$ancestors = get_ancestors( $term->term_id, 'category', 'taxonomy' );
		if ( $ancestors === array() ) {
			return $term->name;
		}

		$parts = array();
		foreach ( array_reverse( $ancestors ) as $ancestor_id ) {
			$ancestor = get_term( (int) $ancestor_id, 'category' );
			if ( $ancestor instanceof \WP_Term ) {
				$parts[] = $ancestor->name;
			}
		}

		$parts[] = $term->name;

		return implode( ' › ', $parts );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function footer_option_fields(): array {
		return array(
			array(
				'key'       => 'field_ak_tab_footer',
				'label'     => 'تنظیمات فوتر',
				'name'      => '',
				'type'      => 'tab',
				'placement' => 'left',
			),
			array(
				'key'           => 'field_ak_footer_panel',
				'label'         => '',
				'name'          => 'footer_settings_panel',
				'type'          => 'button_group',
				'choices'       => array(
					'brand'   => 'برند',
					'columns' => 'ستون‌ها',
					'contact' => 'تماس',
					'social'  => 'شبکه‌های اجتماعی',
					'bottom'  => 'پایین فوتر',
				),
				'default_value' => 'brand',
				'layout'        => 'horizontal',
				'return_format' => 'value',
				'wrapper'       => array(
					'class' => 'ak-footer-panel-switcher ak-theme-panel-switcher',
				),
			),
			...self::footer_panel_fields(),
		);
	}

	/**
	 * @param string $panel
	 * @return list<list<array<string, string>>>
	 */
	private static function footer_panel_condition( string $panel ): array {
		return array(
			array(
				array(
					'field'    => 'field_ak_footer_panel',
					'operator' => '==',
					'value'    => $panel,
				),
			),
		);
	}

	/**
	 * Footer fields under Theme Settings → تنظیمات فوتر.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function footer_panel_fields(): array {
		$when_brand   = self::footer_panel_condition( 'brand' );
		$when_columns = self::footer_panel_condition( 'columns' );
		$when_contact = self::footer_panel_condition( 'contact' );
		$when_social  = self::footer_panel_condition( 'social' );
		$when_bottom  = self::footer_panel_condition( 'bottom' );

		return array(
			array(
				'key'               => 'field_ak_footer_show_brand_column',
				'label'             => 'نمایش ستون برند',
				'name'              => 'footer_show_brand_column',
				'type'              => 'true_false',
				'default_value'     => 1,
				'ui'                => 1,
				'conditional_logic' => $when_brand,
			),
			array(
				'key'               => 'field_ak_site_logo_footer',
				'label'             => 'لوگوی فوتر',
				'name'              => 'site_logo_footer',
				'type'              => 'image',
				'return_format'     => 'array',
				'preview_size'      => 'medium',
				'instructions'      => 'لوگوی سفید یا نسخه مخصوص فوتر. در صورت خالی بودن از لوگوی هدر استفاده می‌شود.',
				'conditional_logic' => $when_brand,
			),
			array(
				'key'               => 'field_ak_footer_brand_heading',
				'label'             => 'عنوان برند (بدون تصویر لوگو)',
				'name'              => 'footer_brand_heading',
				'type'              => 'text',
				'default_value'     => 'عصر خودرو',
				'instructions'      => 'وقتی تصویر لوگو تنظیم نشده باشد، به‌جای لوگو نمایش داده می‌شود.',
				'conditional_logic' => $when_brand,
			),
			array(
				'key'               => 'field_ak_footer_logo_tagline',
				'label'             => 'زیرعنوان لوگو',
				'name'              => 'footer_logo_tagline',
				'type'              => 'text',
				'default_value'     => 'Asre Khodro',
				'instructions'      => 'زیر لوگو یا عنوان برند نمایش داده می‌شود.',
				'conditional_logic' => $when_brand,
			),
			array(
				'key'               => 'field_ak_footer_show_brand_text',
				'label'             => 'نمایش متن معرفی',
				'name'              => 'footer_show_brand_text',
				'type'              => 'true_false',
				'default_value'     => 1,
				'ui'                => 1,
				'conditional_logic' => $when_brand,
			),
			array(
				'key'               => 'field_ak_footer_text',
				'label'             => 'متن معرفی برند',
				'name'              => 'footer_text',
				'type'              => 'textarea',
				'rows'              => 4,
				'default_value'     => 'عصر خودرو بزرگ‌ترین پایگاه خبری–تحلیلی حوزه خودرو در ایران است.',
				'instructions'      => 'توضیح کوتاه زیر لوگو در ستون اول فوتر.',
				'conditional_logic' => $when_brand,
			),
			array(
				'key'               => 'field_ak_footer_background_color',
				'label'             => 'رنگ پس‌زمینه فوتر',
				'name'              => 'footer_background_color',
				'type'              => 'color_picker',
				'default_value'     => '#435c70',
				'instructions'      => 'رنگ پس‌زمینه کل فوتر. پیش‌فرض: رنگ «عصر» در لوگو.',
				'conditional_logic' => $when_brand,
			),
			array(
				'key'               => 'field_ak_footer_show_about_column',
				'label'             => 'نمایش ستون اول',
				'name'              => 'footer_show_about_column',
				'type'              => 'true_false',
				'default_value'     => 1,
				'ui'                => 1,
				'conditional_logic' => $when_columns,
			),
			array(
				'key'               => 'field_ak_footer_about_title',
				'label'             => 'عنوان ستون اول',
				'name'              => 'footer_about_title',
				'type'              => 'text',
				'default_value'     => 'درباره ما',
				'conditional_logic' => $when_columns,
			),
			self::footer_links_repeater(
				'field_ak_footer_about_links',
				'لینک‌های ستون اول',
				'footer_about_links',
				$when_columns,
				array(
					array(
						'link_title' => 'تاریخچه',
						'link_url'   => '#',
					),
					array(
						'link_title' => 'تیم تحریریه',
						'link_url'   => '#',
					),
					array(
						'link_title' => 'تماس با ما',
						'link_url'   => '#',
					),
					array(
						'link_title' => 'تبلیغات',
						'link_url'   => '#',
					),
				)
			),
			array(
				'key'               => 'field_ak_footer_show_categories_column',
				'label'             => 'نمایش ستون دوم',
				'name'              => 'footer_show_categories_column',
				'type'              => 'true_false',
				'default_value'     => 1,
				'ui'                => 1,
				'conditional_logic' => $when_columns,
			),
			array(
				'key'               => 'field_ak_footer_categories_title',
				'label'             => 'عنوان ستون دوم',
				'name'              => 'footer_categories_title',
				'type'              => 'text',
				'default_value'     => 'دسته‌بندی‌ها',
				'conditional_logic' => $when_columns,
			),
			self::footer_links_repeater(
				'field_ak_footer_categories_links',
				'لینک‌های ستون دوم',
				'footer_categories_links',
				$when_columns,
				array(
					array(
						'link_title' => 'اخبار خودرو',
						'link_url'   => '#',
					),
					array(
						'link_title' => 'بازار خودرو',
						'link_url'   => '#',
					),
					array(
						'link_title' => 'خودروهای برقی',
						'link_url'   => '#',
					),
					array(
						'link_title' => 'تست و بررسی',
						'link_url'   => '#',
					),
				)
			),
			array(
				'key'               => 'field_ak_footer_contact_title',
				'label'             => 'عنوان ستون سوم',
				'name'              => 'footer_contact_title',
				'type'              => 'text',
				'default_value'     => 'تماس',
				'conditional_logic' => $when_columns,
			),
			array(
				'key'               => 'field_ak_footer_show_contact_column',
				'label'             => 'نمایش ستون سوم',
				'name'              => 'footer_show_contact_column',
				'type'              => 'true_false',
				'default_value'     => 1,
				'ui'                => 1,
				'conditional_logic' => $when_columns,
			),
			array(
				'key'               => 'field_ak_contact_address',
				'label'             => 'نشانی پستی',
				'name'              => 'contact_address',
				'type'              => 'text',
				'default_value'     => 'سعادت آباد، آسمان، بلوار سرو غربی، بلوار شهید پاکنژاد، ساختمان سینا، پلاک 38، طبقه اول واحد 2',
				'instructions'      => 'در فوتر و صفحه تماس با ما استفاده می‌شود.',
				'conditional_logic' => $when_contact,
			),
			array(
				'key'               => 'field_ak_contact_phone',
				'label'             => 'تلفن تماس',
				'name'              => 'contact_phone',
				'type'              => 'text',
				'default_value'     => '021-26745910',
				'instructions'      => 'در فوتر و صفحه تماس با ما استفاده می‌شود.',
				'conditional_logic' => $when_contact,
			),
			array(
				'key'               => 'field_ak_contact_email',
				'label'             => 'ایمیل',
				'name'              => 'contact_email',
				'type'              => 'email',
				'default_value'     => 'info@asrekhodro.com',
				'instructions'      => 'در فوتر و صفحه تماس با ما استفاده می‌شود.',
				'conditional_logic' => $when_contact,
			),
			array(
				'key'               => 'field_ak_footer_certificates_heading',
				'label'             => 'تنظیمات مجوزها',
				'name'              => '',
				'type'              => 'message',
				'message'           => 'نمادها و مجوزهای رسانه‌ای (مثل e-Rasaneh) را اضافه کنید. در فوتر به‌صورت شبکه‌ای نمایش داده می‌شوند.',
				'new_lines'         => 'wpautop',
				'esc_html'          => 0,
				'conditional_logic' => $when_contact,
			),
			array(
				'key'               => 'field_ak_footer_show_certificates',
				'label'             => 'نمایش مجوزها در فوتر',
				'name'              => 'footer_show_certificates',
				'type'              => 'true_false',
				'default_value'     => 1,
				'ui'                => 1,
				'conditional_logic' => $when_contact,
			),
			self::footer_certificates_repeater( $when_contact ),
			array(
				'key'               => 'field_ak_footer_show_social',
				'label'             => 'نمایش شبکه‌های اجتماعی',
				'name'              => 'footer_show_social',
				'type'              => 'true_false',
				'default_value'     => 1,
				'ui'                => 1,
				'conditional_logic' => $when_social,
			),
			self::footer_social_repeater( $when_social ),
			array(
				'key'               => 'field_ak_footer_copyright_text',
				'label'             => 'متن کپی‌رایت',
				'name'              => 'footer_copyright_text',
				'type'              => 'text',
				'default_value'     => '© %year% عصر خودرو — تمامی حقوق محفوظ است.',
				'instructions'      => 'از %year% برای سال جاری و %site_name% برای نام سایت استفاده کنید.',
				'conditional_logic' => $when_bottom,
			),
		);
	}

	/**
	 * Repeater for footer social links (title + URL + SVG icon).
	 *
	 * @param list<list<array<string, string>>> $when
	 * @return array<string, mixed>
	 */
	private static function footer_social_repeater( array $when ): array {
		return array(
			'key'               => 'field_ak_footer_social_links',
			'label'             => 'شبکه‌های اجتماعی',
			'name'              => 'footer_social_links',
			'type'              => 'repeater',
			'layout'            => 'block',
			'button_label'      => 'افزودن شبکه اجتماعی',
			'min'               => 0,
			'max'               => 12,
			'conditional_logic' => $when,
			'sub_fields'        => array(
				array(
					'key'           => 'field_ak_footer_social_title',
					'label'         => 'عنوان',
					'name'          => 'social_title',
					'type'          => 'text',
					'required'      => 1,
					'placeholder'   => 'مثلاً اینستاگرام',
				),
				array(
					'key'         => 'field_ak_footer_social_url',
					'label'       => 'نشانی',
					'name'        => 'social_url',
					'type'        => 'url',
					'required'    => 1,
					'placeholder' => 'https://',
				),
				array(
					'key'          => 'field_ak_footer_social_svg',
					'label'        => 'آیکن SVG',
					'name'         => 'social_svg',
					'type'         => 'textarea',
					'rows'         => 6,
					'new_lines'    => '',
					'instructions' => 'کد SVG آیکن. از fill="currentColor" برای هماهنگی با رنگ فوتر استفاده کنید.',
					'wrapper'      => array(
						'class' => 'ak-social-svg-field',
					),
				),
			),
		);
	}

	/**
	 * Certificate / license badges repeater for footer.
	 *
	 * @param list<list<array<string, string>>> $when
	 * @return array<string, mixed>
	 */
	private static function footer_certificates_repeater( array $when ): array {
		return array(
			'key'               => 'field_ak_footer_certificates',
			'label'             => 'مجوزها',
			'name'              => 'footer_certificates',
			'type'              => 'repeater',
			'layout'            => 'block',
			'button_label'      => 'افزودن مجوز',
			'min'               => 0,
			'max'               => 12,
			'conditional_logic' => $when,
			'default_value'     => array(
				array(
					'cert_title'     => 'e-Rasaneh',
					'cert_alt'       => 'e-Rasaneh',
					'cert_url'       => 'https://e-rasaneh.ir',
					'cert_image_url' => 'https://e-rasaneh.ir/static/img/logo.jpg',
				),
			),
			'sub_fields'        => array(
				array(
					'key'           => 'field_ak_footer_cert_title',
					'label'         => 'نام مجوز',
					'name'          => 'cert_title',
					'type'          => 'text',
					'required'      => 1,
					'placeholder'   => 'e-Rasaneh',
				),
				array(
					'key'           => 'field_ak_footer_cert_image',
					'label'         => 'تصویر مجوز',
					'name'          => 'cert_image',
					'type'          => 'image',
					'return_format' => 'array',
					'preview_size'  => 'thumbnail',
					'instructions'  => 'در صورت خالی بودن از «آدرس تصویر» استفاده می‌شود.',
				),
				array(
					'key'          => 'field_ak_footer_cert_image_url',
					'label'        => 'آدرس تصویر (اختیاری)',
					'name'         => 'cert_image_url',
					'type'         => 'url',
					'instructions' => 'برای تصاویر خارج از کتابخانه رسانه، مثل لوگوی e-Rasaneh.',
				),
				array(
					'key'         => 'field_ak_footer_cert_url',
					'label'       => 'لینک',
					'name'        => 'cert_url',
					'type'        => 'url',
					'placeholder' => 'https://',
				),
				array(
					'key'          => 'field_ak_footer_cert_alt',
					'label'        => 'متن جایگزین تصویر',
					'name'         => 'cert_alt',
					'type'         => 'text',
					'instructions' => 'در صورت خالی بودن از «نام مجوز» استفاده می‌شود.',
				),
			),
		);
	}

	/**
	 * Repeater for footer column links (title + URL).
	 *
	 * @param string                   $key
	 * @param string                   $label
	 * @param string                   $name
	 * @param list<list<array<string, string>>> $when
	 * @param list<array<string, string>>       $defaults
	 * @return array<string, mixed>
	 */
	private static function footer_links_repeater(
		string $key,
		string $label,
		string $name,
		array $when,
		array $defaults
	): array {
		return array(
			'key'               => $key,
			'label'             => $label,
			'name'              => $name,
			'type'              => 'repeater',
			'layout'            => 'table',
			'button_label'      => 'افزودن لینک',
			'min'               => 0,
			'max'               => 12,
			'default_value'     => $defaults,
			'conditional_logic' => $when,
			'sub_fields'        => array(
				array(
					'key'           => $key . '_title',
					'label'         => 'عنوان',
					'name'          => 'link_title',
					'type'          => 'text',
					'required'      => 1,
					'wrapper'       => array(
						'width' => '40',
					),
				),
				array(
					'key'           => $key . '_url',
					'label'         => 'نشانی',
					'name'          => 'link_url',
					'type'          => 'text',
					'required'      => 1,
					'placeholder'   => 'https:// یا /مسیر',
					'wrapper'       => array(
						'width' => '60',
					),
				),
			),
		);
	}

	/**
	 * Appearance select choices from each appearance manifest (id => label).
	 *
	 * @return array<string, string>
	 */
	private static function appearance_choices(): array {
		$choices = Appearance::choices_for_acf();

		if ( $choices !== array() ) {
			return $choices;
		}

		return array( Appearance::DEFAULT_ID => 'کلاسیک' );
	}

	private static function appearance_default(): string {
		$choices = self::appearance_choices();

		if ( array_key_exists( Appearance::DEFAULT_ID, $choices ) ) {
			return Appearance::DEFAULT_ID;
		}

		$first = array_key_first( $choices );

		return is_string( $first ) ? $first : Appearance::DEFAULT_ID;
	}
}
