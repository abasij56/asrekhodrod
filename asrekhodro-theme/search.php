<?php

use AsreKhodro\Theme\PageLayout;

$context          = Timber\Timber::context();
$context['posts'] = Timber\Timber::get_posts();
$context['query'] = get_search_query();
$context          = array_merge( $context, PageLayout::search_context() );

Timber\Timber::render( 'search.twig', $context );
