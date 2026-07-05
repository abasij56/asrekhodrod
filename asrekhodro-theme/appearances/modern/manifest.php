<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'id'    => 'modern',
	'label' => 'Modern',
	'pages' => array(
		'front_page' => array(
			'label'    => 'صفحه اصلی',
			'sections' => array(
				'ticker',
				'hero',
				'news_list',
				'videos',
				'news_archive_url',
			),
			'zones'    => array(
				'before_main' => array(
					'label'    => 'بالای صفحه',
					'blocks'   => array( 'ak-ad-strip', 'ak-ticker' ),
					'multiple' => true,
				),
				'main'        => array(
					'label'    => 'محتوای اصلی',
					'blocks'   => array( 'ak-hero', 'ak-news-list', 'ak-videos', 'ak-newsletter' ),
					'multiple' => true,
				),
				'sidebar'     => array(
					'label'    => 'سایدبار',
					'blocks'   => array( '*' ),
					'multiple' => true,
				),
			),
			'defaults' => array(
				array( 'zone' => 'before_main', 'block' => 'ak-ad-strip' ),
				array( 'zone' => 'before_main', 'block' => 'ak-ticker' ),
				array( 'zone' => 'main', 'block' => 'ak-hero' ),
				array( 'zone' => 'main', 'block' => 'ak-news-list' ),
				array( 'zone' => 'main', 'block' => 'ak-videos' ),
				array( 'zone' => 'main', 'block' => 'ak-newsletter' ),
				array( 'zone' => 'sidebar', 'block' => 'ak-sidebar-ads' ),
			),
		),
		'archive'     => array(
			'label'    => 'آرشیو',
			'zones'    => array(
				'before_main' => array(
					'label'  => 'بالای صفحه',
					'blocks' => array( 'ak-ad-strip' ),
				),
				'main'        => array(
					'label'    => 'محتوای اصلی',
					'blocks'   => array( '*' ),
					'multiple' => true,
				),
				'sidebar'     => array(
					'label'  => 'سایدبار',
					'blocks' => array( '*' ),
				),
			),
			'defaults' => array(
				array( 'zone' => 'before_main', 'block' => 'ak-ad-strip' ),
				array( 'zone' => 'sidebar', 'block' => 'ak-sidebar-popular-2day' ),
				array( 'zone' => 'sidebar', 'block' => 'ak-sidebar-videos' ),
				array( 'zone' => 'sidebar', 'block' => 'ak-sidebar-latest-news' ),
				array( 'zone' => 'sidebar', 'block' => 'ak-sidebar-ads' ),
			),
		),
		'single_post' => array(
			'label'    => 'تک‌نوشته',
			'zones'    => array(
				'before_main' => array(
					'label'  => 'بالای صفحه',
					'blocks' => array( 'ak-ad-strip' ),
				),
				'main'        => array(
					'label'    => 'محتوای اصلی',
					'blocks'   => array( '*' ),
					'multiple' => true,
				),
				'sidebar'     => array(
					'label'  => 'سایدبار',
					'blocks' => array( '*' ),
				),
			),
			'defaults' => array(
				array( 'zone' => 'before_main', 'block' => 'ak-ad-strip' ),
				array( 'zone' => 'sidebar', 'block' => 'ak-sidebar-popular-2day' ),
				array( 'zone' => 'sidebar', 'block' => 'ak-sidebar-videos' ),
				array( 'zone' => 'sidebar', 'block' => 'ak-sidebar-latest-news' ),
				array( 'zone' => 'sidebar', 'block' => 'ak-sidebar-ads' ),
			),
		),
	),
	'blocks' => array(),
);
