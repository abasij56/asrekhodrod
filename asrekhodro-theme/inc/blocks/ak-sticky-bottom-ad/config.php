<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'name'         => 'ak-sticky-bottom-ad',
	'type'         => 'layout',
	'label'        => 'تبلیغ چسبان پایین',
	'source'       => 'query',
	'context_key'  => 'sticky_bottom_ads',
	'ad_position'  => 'sticky_bottom',
	'image_size'   => 'ak-sticky-bottom-ad',
	'defaults'     => array(
		'post_type' => 'ad_slot',
		'count'     => 1,
		'strategy'  => 'latest',
	),
	'partial'      => 'ak-sticky-bottom-ad/partial.twig',
	'full_bleed'   => true,
);
