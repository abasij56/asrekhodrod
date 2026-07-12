<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$zone_sidebar = array(
	'label'    => 'سایدبار',
	'blocks'   => array( '*' ),
	'multiple' => true,
);

$zone_after_main = array(
	'label'    => 'قبل از فوتر',
	'blocks'   => array( '*' ),
	'multiple' => true,
);

$zone_fixed_bottom = array(
	'label'    => 'پایین صفحه (چسبان)',
	'blocks'   => array( 'ak-sticky-bottom-ad' ),
	'multiple' => false,
);

$zone_before_main = array(
	'label'    => 'بالای صفحه',
	'blocks'   => array( '*' ),
	'multiple' => true,
);

$system_page_zones = array(
	'before_main' => $zone_before_main,
	'main'        => array(
		'label'    => 'بالای محتوای سیستمی',
		'blocks'   => array( '*' ),
		'multiple' => true,
	),
	'main_after'  => array(
		'label'    => 'زیر محتوای سیستمی',
		'blocks'   => array( '*' ),
		'multiple' => true,
	),
	'sidebar'      => $zone_sidebar,
	'after_main'   => $zone_after_main,
	'fixed_bottom' => $zone_fixed_bottom,
);

$block_page_zones = array(
	'before_main' => $zone_before_main,
	'main'        => array(
		'label'    => 'محتوای اصلی',
		'blocks'   => array( '*' ),
		'multiple' => true,
	),
	'sidebar'      => $zone_sidebar,
	'after_main'   => $zone_after_main,
	'fixed_bottom' => $zone_fixed_bottom,
);

$archive_sidebar_defaults = array(
	array( 'zone' => 'sidebar', 'block' => 'ak-sidebar-popular-2day' ),
	array( 'zone' => 'sidebar', 'block' => 'ak-sidebar-videos' ),
	array( 'zone' => 'sidebar', 'block' => 'ak-sidebar-latest-news' ),
	array( 'zone' => 'sidebar', 'block' => 'ak-sidebar-ads' ),
);

return array(
	'id'    => 'classic',
	'label' => 'Classic',
	'pages' => array(
		'front_page'  => array(
			'label'       => 'صفحه اصلی',
			'layout_mode' => 'blocks',
			'sections'    => array(
				'ticker',
				'hero',
				'featured',
				'news_list',
				'picture_frame',
				'magazines',
				'videos_2',
				'videos',
				'reviews',
				'content_row_ad',
				'news_archive_url',
			),
			'zones'       => array(
				'before_main' => array(
					'label'    => 'بالای صفحه',
					'blocks'   => array( 'ak-ad-strip', 'ak-ticker' ),
					'multiple' => true,
				),
				'main'        => array(
					'label'    => 'محتوای اصلی',
					'blocks'   => array(
						'ak-hero',
						'ak-asrekhodro-featured',
						'ak-featured-grid',
						'ak-featured-cars',
						'ak-news-list',
						'ak-magazines',
						'ak-videos-2',
						'ak-videos',
						'ak-reviews',
						'ak-newsletter',
					),
					'multiple' => true,
				),
				'sidebar'     => $zone_sidebar,
				'after_main'   => array(
					'label'    => 'قبل از فوتر',
					'blocks'   => array( 'ak-picture-frame' ),
					'multiple' => false,
				),
				'fixed_bottom' => $zone_fixed_bottom,
			),
			'defaults'    => array(
				array( 'zone' => 'before_main', 'block' => 'ak-ad-strip' ),
				array( 'zone' => 'before_main', 'block' => 'ak-ticker' ),
				array( 'zone' => 'main', 'block' => 'ak-hero' ),
				array( 'zone' => 'main', 'block' => 'ak-asrekhodro-featured' ),
				array( 'zone' => 'main', 'block' => 'ak-news-list' ),
				array( 'zone' => 'main', 'block' => 'ak-magazines' ),
				array( 'zone' => 'main', 'block' => 'ak-videos' ),
				array( 'zone' => 'main', 'block' => 'ak-reviews' ),
				array( 'zone' => 'main', 'block' => 'ak-newsletter' ),
				array( 'zone' => 'sidebar', 'block' => 'ak-sidebar-ads' ),
				array( 'zone' => 'after_main', 'block' => 'ak-picture-frame' ),
				array( 'zone' => 'fixed_bottom', 'block' => 'ak-sticky-bottom-ad' ),
			),
		),
		'archive'     => array(
			'label'       => 'آرشیو',
			'layout_mode' => 'system',
			'zones'       => $system_page_zones,
			'defaults'    => array_merge(
				array(
					array( 'zone' => 'before_main', 'block' => 'ak-ad-strip' ),
					array( 'zone' => 'fixed_bottom', 'block' => 'ak-sticky-bottom-ad' ),
				),
				$archive_sidebar_defaults
			),
		),
		'single_post' => array(
			'label'       => 'تک‌نوشته',
			'layout_mode' => 'system',
			'zones'       => $system_page_zones,
			'defaults'    => array_merge(
				array(
					array( 'zone' => 'before_main', 'block' => 'ak-ad-strip' ),
					array( 'zone' => 'fixed_bottom', 'block' => 'ak-sticky-bottom-ad' ),
				),
				$archive_sidebar_defaults
			),
		),
		'page_about'  => array(
			'label'       => 'درباره ما',
			'layout_mode' => 'blocks',
			'zones'       => $block_page_zones,
			'defaults'    => array(
				array( 'zone' => 'main', 'block' => 'ak-page-content' ),
			),
		),
		'page_contact'=> array(
			'label'       => 'تماس با ما',
			'layout_mode' => 'blocks',
			'zones'       => $block_page_zones,
			'defaults'    => array(
				array( 'zone' => 'main', 'block' => 'ak-contact-page' ),
			),
		),
		'page_car'    => array(
			'label'       => 'صفحه خودرو',
			'layout_mode' => 'blocks',
			'zones'       => $block_page_zones,
			'defaults'    => array(
				array( 'zone' => 'main', 'block' => 'ak-page-content' ),
			),
		),
		'page_carsinfo_directory' => array(
			'label'       => 'دانشنامه خودرو',
			'layout_mode' => 'blocks',
			'zones'       => $block_page_zones,
			'defaults'    => array(
				array( 'zone' => 'main', 'block' => 'ak-page-content' ),
			),
		),
		'carsinfo_3d2' => array(
			'label'       => 'اطلاعات خودرو — 3D2',
			'layout_mode' => 'system',
			'zones'       => array(
				'before_main' => $zone_before_main,
				'main'        => array(
					'label'    => 'قبل از محتوای اصلی',
					'blocks'   => array( '*' ),
					'multiple' => true,
				),
				'main_after'  => array(
					'label'    => 'بعد از محتوای اصلی',
					'blocks'   => array( '*' ),
					'multiple' => true,
				),
				'sidebar'     => $zone_sidebar,
				'after_main'  => $zone_after_main,
			),
			'defaults'    => array(),
		),
		'not_found'   => array(
			'label'       => 'صفحه ۴۰۴',
			'layout_mode' => 'blocks',
			'zones'       => $block_page_zones,
			'defaults'    => array(
				array( 'zone' => 'main', 'block' => 'ak-not-found' ),
			),
		),
	),
	'blocks' => array(),
);
