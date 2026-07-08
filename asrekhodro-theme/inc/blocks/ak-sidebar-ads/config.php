<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'name'         => 'ak-sidebar-ads',
	'type'         => 'layout',
	'label'        => 'تبلیغات سایدبار',
	'source'       => 'query',
	'context_key'  => 'sidebar_ads',
	'ad_position'  => 'sidebar_left',
	'image_size'   => 'medium',
	'defaults'     => array(
		'post_type' => 'ad_slot',
		'count'     => 6,
		'strategy'  => 'latest',
	),
	'partial'      => 'ak-sidebar-ads/partial.twig',
	'gutenberg'    => true,
	'icon'         => 'megaphone',
	'keywords'     => array( 'تبلیغ', 'ad', 'sidebar', 'سایدبار' ),
);
