<?php
/**
 * Template Name: About
 * Description: About us page — title and content from the page editor.
 */

use AsreKhodro\Theme\AboutPage;
use AsreKhodro\Theme\PageLayout;

$context                  = Timber\Timber::context();
$context['post']          = Timber\Timber::get_post();
$context['is_about_page'] = true;
$context                  = array_merge( $context, PageLayout::for_page( 'page_about' ) );
$context['body_class']    = implode( ' ', get_body_class() );

Timber\Timber::render( 'page-about.twig', $context );
