<?php
/**
 * Template Name: Contact
 * Description: Contact page with map, CF7 form, and address info.
 */

use AsreKhodro\Theme\ContactPage;
use AsreKhodro\Theme\PageLayout;

$context         = Timber\Timber::context();
$context['post'] = Timber\Timber::get_post();

add_filter(
	'body_class',
	static function ( array $classes ): array {
		$classes[] = 'contact-page';
		return $classes;
	}
);

$context = array_merge( $context, ContactPage::get_context() );
$context = array_merge( $context, PageLayout::for_page( 'page_contact' ) );

Timber\Timber::render( 'page-contact.twig', $context );
