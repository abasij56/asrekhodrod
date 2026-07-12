<?php

use AsreKhodro\Theme\CarInfo3d;
use AsreKhodro\Theme\CinfoToc;
use AsreKhodro\Theme\PageLayout;

$post = Timber\Timber::get_post();
if ( ! $post instanceof \Timber\Post ) {
	return;
}

$template = CarInfo3d::template_slug( (int) $post->ID );

if ( $template === CarInfo3d::TEMPLATE_2D ) {
	Timber\Timber::render( 'page-carinfo2d.twig', CarInfo3d::context_for_2d( $post ) );
	return;
}

if ( $template === CarInfo3d::TEMPLATE_3D2 ) {
	$context         = Timber\Timber::context();
	$context['post'] = $post;
	$context         = array_merge( $context, PageLayout::for_page( 'carsinfo_3d2' ) );
	Timber\Timber::render( 'page-carinfo3d2.twig', $context );
	return;
}

if ( $template === CarInfo3d::TEMPLATE ) {
	$context         = Timber\Timber::context();
	$context['post'] = $post;
	Timber\Timber::render( 'page-carinfo3d.twig', $context );
	return;
}

$context                      = Timber\Timber::context();
$context['post']              = $post;
$context['carsinfo_show_toc'] = CinfoToc::show_for_post( (int) $post->ID );

Timber\Timber::render( 'single-carsinfo.twig', $context );
