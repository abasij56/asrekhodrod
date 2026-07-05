<?php

use AsreKhodro\Theme\PageLayout;

$context = Timber\Timber::context();
$context = array_merge( $context, PageLayout::for_page( 'not_found' ) );
Timber\Timber::render( '404.twig', $context );
