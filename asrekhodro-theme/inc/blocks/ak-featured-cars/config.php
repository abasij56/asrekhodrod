<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'name'          => 'ak-featured-cars',
	'type'          => 'layout',
	'label'         => 'ماشین‌های منتخب',
	'default_title' => 'ماشین‌های منتخب',
	'source'        => 'query',
	'context_key'   => 'featured_cars',
	'defaults'      => array(
		'post_type' => 'carsinfo',
		'count'     => 8,
		'strategy'  => 'latest',
	),
	'partial'       => 'ak-featured-cars/partial.twig',
	'gutenberg'     => true,
	'icon'          => 'car',
	'mode'          => 'preview',
	'keywords'      => array( 'خودرو', 'ماشین', 'منتخب', 'featured', 'cars' ),
	'supports'      => array(
		'align'  => array( 'wide', 'full' ),
		'anchor' => true,
	),
);
