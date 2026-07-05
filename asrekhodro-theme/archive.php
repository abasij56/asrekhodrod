<?php

use AsreKhodro\Theme\Magazines;
use AsreKhodro\Theme\NewsArchive;
use AsreKhodro\Theme\PageLayout;
use AsreKhodro\Theme\ReviewsArchive;
use AsreKhodro\Theme\VideosArchive;

$context          = Timber\Timber::context();
$context['posts'] = Timber\Timber::get_posts();
$context['title'] = get_the_archive_title();

$layout_extra = array();
if ( is_post_type_archive( 'ak_magazine' ) ) {
	$layout_extra['show_kiosk'] = false;
	$context                    = array_merge( $context, Magazines::get_archive_page_context() );
	$context['title']           = $context['kiosk_archive_title'];
} elseif ( is_post_type_archive( 'ak_review' ) ) {
	$context          = array_merge( $context, ReviewsArchive::get_archive_page_context() );
	$context['title'] = $context['review_archive_title'];
} elseif ( VideosArchive::is_archive_request() ) {
	$context          = array_merge( $context, VideosArchive::get_archive_page_context( (string) $context['title'] ) );
	$context['title'] = $context['archive_hero_title'];
} elseif ( NewsArchive::is_archive_request() ) {
	$context = array_merge( $context, NewsArchive::get_archive_page_context( (string) $context['title'] ) );
}

$context = array_merge( $context, PageLayout::archive_context( $layout_extra ) );

$templates = array( 'archive.twig' );
if ( is_post_type_archive( 'ak_video' ) || is_tax( 'video_category' ) ) {
	$templates = array( 'archive-ak_video.twig', 'archive.twig' );
} elseif ( get_post_type() ) {
	array_unshift( $templates, 'archive-' . get_post_type() . '.twig' );
}

Timber\Timber::render( $templates, $context );
