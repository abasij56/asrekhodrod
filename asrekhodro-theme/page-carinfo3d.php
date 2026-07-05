<?php
/**
 * Template Name: Car Info 3D
 * Template Post Type: carsinfo
 * Description: Immersive car intro — glass site header and fixed 3D background.
 */

$context         = Timber\Timber::context();
$context['post'] = Timber\Timber::get_post();

Timber\Timber::render( 'page-carinfo3d.twig', $context );
