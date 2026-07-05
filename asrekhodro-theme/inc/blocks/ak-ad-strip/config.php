<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'name'         => 'ak-ad-strip',
	'type'         => 'layout',
	'label'        => 'نوار تبلیغ بالا',
	'source'       => 'query',
	'context_key'  => 'menu_strip_ads',
	'ad_position'  => 'menu_strip',
	'image_size'   => 'ak-ad-strip',
	'defaults'     => array(
		'post_type' => 'ad_slot',
		'count'     => 3,
		'strategy'  => 'latest',
	),
	'partial'      => 'ak-ad-strip/partial.twig',
);
