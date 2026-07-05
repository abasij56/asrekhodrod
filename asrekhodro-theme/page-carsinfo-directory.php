<?php
/**
 * Template Name: Car Encyclopedia
 * Description: Carsinfo directory — add the «دانشنامه خودرو — آرشیو» block in the page editor.
 */

use AsreKhodro\Theme\PageLayout;

$context         = Timber\Timber::context();
$context['post'] = Timber\Timber::get_post();

add_filter(
	'body_class',
	static function ( array $classes ): array {
		$classes[] = 'carsinfo-directory-page';
		return $classes;
	}
);

$context                  = array_merge( $context, PageLayout::for_page( 'page_carsinfo_directory' ) );
$context['body_class']    = implode( ' ', get_body_class() );

Timber\Timber::render( 'page-carsinfo-directory.twig', $context );
