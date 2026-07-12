<?php
/**
 * Template Name: Car Info 2D
 * Template Post Type: carsinfo
 * Description: 2D layout based on 3D2 — simple background, no 3D scene, no model section.
 */

use AsreKhodro\Theme\CarInfo3d;

$context = CarInfo3d::context_for_2d();

Timber\Timber::render( 'page-carinfo2d.twig', $context );
