<?php

use AsreKhodro\Theme\NewsArchive;
use AsreKhodro\Theme\PageLayout;

$context          = Timber\Timber::context();
$context['posts'] = Timber\Timber::get_posts();
$context['title'] = __( 'اخبار خودرو', 'asrekhodro' );

$context = array_merge( $context, NewsArchive::get_archive_page_context( $context['title'] ) );
$context = array_merge( $context, PageLayout::archive_context() );

Timber\Timber::render( array( 'archive.twig', 'index.twig' ), $context );
