<?php
/**
 * Template Name: Car Info 3D2
 * Template Post Type: carsinfo
 * Description: 3D intro + normal scroll content panel with footer (semi-transparent body over fixed 3D scene).
 */

use AsreKhodro\Theme\PageLayout;

$context         = Timber\Timber::context();
$context['post'] = Timber\Timber::get_post();
$context         = array_merge( $context, PageLayout::for_page( 'carsinfo_3d2' ) );

Timber\Timber::render( 'page-carinfo3d2.twig', $context );
