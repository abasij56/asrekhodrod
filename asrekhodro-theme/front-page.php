<?php

use AsreKhodro\Theme\Appearance;
use AsreKhodro\Theme\Homepage;
use AsreKhodro\Theme\RfPage;

$context = Timber\Timber::context();

if ( Appearance::id() === 'rfarda' ) {
	$context = array_merge( $context, RfPage::get_context() );
} else {
	$context = array_merge( $context, Homepage::get_context() );
}

Timber\Timber::render( Appearance::resolve_template( 'front-page.twig' ), $context );
