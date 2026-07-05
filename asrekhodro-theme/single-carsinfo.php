<?php

use AsreKhodro\Theme\CinfoToc;

$context         = Timber\Timber::context();
$context['post'] = Timber\Timber::get_post();

$post = $context['post'];
if ( $post ) {
	$context['carsinfo_show_toc'] = CinfoToc::show_for_post( (int) $post->ID );
}

Timber\Timber::render( 'single-carsinfo.twig', $context );
