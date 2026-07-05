<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register CPTs/taxonomies required for import (works even when theme is inactive during cron).
 */
final class AsreKhodro_Importer_Post_Types {

	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ), 5 );
	}

	public static function register(): void {
		if ( ! post_type_exists( 'ad_slot' ) ) {
			register_post_type(
				'ad_slot',
				array(
					'labels'       => array(
						'name'          => __( 'Ads', 'asrekhodro' ),
						'singular_name' => __( 'Ad', 'asrekhodro' ),
					),
					'public'       => false,
					'show_ui'      => true,
					'show_in_menu' => true,
					'supports'     => array( 'title' ),
					'has_archive'  => false,
				)
			);
		}

		if ( ! taxonomy_exists( 'ad_position' ) ) {
			register_taxonomy(
				'ad_position',
				'ad_slot',
				array(
					'labels'       => array(
						'name' => __( 'Ad positions', 'asrekhodro' ),
					),
					'public'       => false,
					'show_ui'      => true,
					'hierarchical' => true,
				)
			);
		}

		if ( ! post_type_exists( 'ak_video' ) ) {
			register_post_type(
				'ak_video',
				array(
					'public'       => true,
					'has_archive'  => true,
					'rewrite'      => array( 'slug' => 'video' ),
					'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
					'show_in_rest' => true,
				)
			);
		}

		if ( ! taxonomy_exists( 'video_category' ) ) {
			register_taxonomy(
				'video_category',
				'ak_video',
				array(
					'public'       => true,
					'hierarchical' => true,
					'rewrite'      => array( 'slug' => 'video-category' ),
					'show_in_rest' => true,
				)
			);
		}

		if ( ! post_type_exists( 'ak_magazine' ) ) {
			register_post_type(
				'ak_magazine',
				array(
					'public'       => true,
					'has_archive'  => 'Home/Kiosk',
					'rewrite'      => array(
						'slug'       => 'Home/Kiosk',
						'with_front' => false,
					),
					'supports'     => array( 'title', 'thumbnail', 'excerpt' ),
					'show_in_rest' => true,
				)
			);
		}

		if ( ! post_type_exists( 'ak_review' ) ) {
			register_post_type(
				'ak_review',
				array(
					'public'       => true,
					'has_archive'  => true,
					'rewrite'      => array( 'slug' => 'review' ),
					'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
					'show_in_rest' => true,
				)
			);
		}
	}
}
