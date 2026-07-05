<?php

use AsreKhodro\Theme\PageLayout;
use AsreKhodro\Theme\SinglePost;
use AsreKhodro\Theme\VideoSingle;

$context         = Timber\Timber::context();
$context['post'] = Timber\Timber::get_post();

if ( ! $context['post'] ) {
	wp_safe_redirect( home_url( '/' ) );
	exit;
}

if ( $context['post']->post_type === 'ak_video' ) {
	$context = VideoSingle::extend_context( $context );
} else {
	$context = SinglePost::extend_context( $context );
}

$context = array_merge(
	$context,
	PageLayout::single_context( (int) $context['post']->ID )
);

$templates = array( 'single-' . $context['post']->post_type . '.twig', 'single-post.twig' );
Timber\Timber::render( $templates, $context );
