<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'id'    => 'modern-blue',
	'label' => 'Modern Blue',
	'pages' => array(
		'front_page' => array(
			'label'    => 'صفحه اصلی',
			'sections' => array(
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
			'zones'    => array(
				'before_main' => array(
					'label'    => 'بالای صفحه',
					'blocks'   => array( 'ak-ad-strip', 'ak-ticker' ),
					'multiple' => true,
				),
				'main'        => array(
					'label'    => 'محتوای اصلی',
					'blocks'   => array(
						'ak-hero',
						'ak-featured-grid',
						'ak-news-list',
						'ak-magazines',
						'ak-videos-2',
						'ak-videos',
						'ak-reviews',
						'ak-newsletter',
					),
					'multiple' => true,
				),
				'sidebar'     => array(
					'label'    => 'سایدبار',
					'blocks'   => array( '*' ),
					'multiple' => true,
				),
				'after_main'  => array(
					'label'    => 'بعد از محتوا',
					'blocks'   => array( 'ak-picture-frame' ),
					'multiple' => false,
				),
			),
			'defaults' => array(
				array( 'zone' => 'before_main', 'block' => 'ak-ad-strip' ),
				array( 'zone' => 'before_main', 'block' => 'ak-ticker' ),
				array( 'zone' => 'main', 'block' => 'ak-hero', 'wrapper' => 'mb-home__section mb-home__section--hero' ),
				array( 'zone' => 'main', 'block' => 'ak-featured-grid', 'wrapper' => 'mb-home__section mb-home__section--featured' ),
				array( 'zone' => 'main', 'block' => 'ak-news-list', 'wrapper' => 'mb-home__section mb-home__section--news' ),
				array( 'zone' => 'main', 'block' => 'ak-magazines', 'wrapper' => 'mb-home__section mb-home__section--magazines' ),
				array( 'zone' => 'main', 'block' => 'ak-videos', 'wrapper' => 'mb-home__section mb-home__section--videos' ),
				array( 'zone' => 'main', 'block' => 'ak-reviews', 'wrapper' => 'mb-home__section mb-home__section--reviews' ),
				array( 'zone' => 'main', 'block' => 'ak-newsletter', 'wrapper' => 'mb-home__section mb-home__section--newsletter' ),
				array( 'zone' => 'sidebar', 'block' => 'ak-sidebar-ads' ),
				array( 'zone' => 'after_main', 'block' => 'ak-picture-frame', 'wrapper' => 'mb-home__section mb-home__section--picture-frame' ),
			),
		),
		'archive'     => array(
			'label'    => 'آرشیو',
			'zones'    => array(
				'before_main' => array( 'label' => 'بالای صفحه', 'blocks' => array( 'ak-ad-strip' ) ),
				'main'        => array( 'label' => 'محتوای اصلی', 'blocks' => array( '*' ), 'multiple' => true ),
				'sidebar'     => array( 'label' => 'سایدبار', 'blocks' => array( '*' ), 'multiple' => true ),
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
				'before_main' => array( 'label' => 'بالای صفحه', 'blocks' => array( 'ak-ad-strip' ) ),
				'main'        => array( 'label' => 'محتوای اصلی', 'blocks' => array( '*' ), 'multiple' => true ),
				'sidebar'     => array( 'label' => 'سایدبار', 'blocks' => array( '*' ), 'multiple' => true ),
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
