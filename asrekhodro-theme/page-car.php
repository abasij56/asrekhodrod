<?php
/**
 * Template Name: Car page
 * Description: Car review page — content from Cars Gutenberg blocks (stored in post body).
 */

use AsreKhodro\Theme\PageLayout;

$context         = Timber\Timber::context();
$context['post'] = Timber\Timber::get_post();
$context         = array_merge( $context, PageLayout::for_page( 'page_car' ) );

add_filter(
	'body_class',
	static function ( array $classes ): array {
		$classes[] = 'car-page';
		return $classes;
	}
);

Timber\Timber::render( 'page-car.twig', $context );
