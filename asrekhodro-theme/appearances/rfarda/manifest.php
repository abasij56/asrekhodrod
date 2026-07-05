<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'id'    => 'rfarda',
	'label' => 'رادیو فردا',
	'pages' => array(
		'front_page' => array(
			'label'    => 'صفحه اصلی',
			'sections' => array(
				'hero',
				'videos',
				'reviews',
				'news_archive_url',
			),
			'zones'    => array(
				'main' => array(
					'label'    => 'محتوای اصلی',
					'blocks'   => array( 'ak-hero', 'ak-videos', 'ak-reviews' ),
					'multiple' => true,
				),
			),
			'defaults' => array(
				array( 'zone' => 'main', 'block' => 'ak-hero' ),
				array( 'zone' => 'main', 'block' => 'ak-videos' ),
				array( 'zone' => 'main', 'block' => 'ak-reviews' ),
			),
		),
		'archive'     => array(
			'label'    => 'آرشیو',
			'zones'    => array(
				'before_main' => array(
					'label'    => 'بالای صفحه',
					'blocks'   => array( 'ak-ad-strip' ),
					'multiple' => false,
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
					'label'    => 'بالای صفحه',
					'blocks'   => array( 'ak-ad-strip' ),
					'multiple' => false,
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
		'carsinfo_3d2' => array(
			'label'    => 'اطلاعات خودرو — 3D2',
			'zones'    => array(
				'before_main' => array(
					'label'    => 'بالای صفحه',
					'blocks'   => array( 'ak-ad-strip' ),
					'multiple' => false,
				),
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
				'sidebar'     => array(
					'label'  => 'سایدبار',
					'blocks' => array( '*' ),
				),
				'after_main'  => array(
					'label'    => 'قبل از فوتر',
					'blocks'   => array( '*' ),
					'multiple' => true,
				),
			),
			'defaults' => array(),
		),
	),
	'blocks' => array(),
);
