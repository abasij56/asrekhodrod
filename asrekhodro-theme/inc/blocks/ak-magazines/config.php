<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'name'          => 'ak-magazines',
	'type'          => 'layout',
	'label'         => 'مجلات',
	'default_title' => 'مجلات روز',
	'source'        => 'query',
	'context_key'   => 'magazines',
	'defaults'      => array(
		'post_type' => 'ak_magazine',
		'count'     => 5,
		'strategy'  => 'latest',
	),
	'full_bleed'    => true,
	'partial'       => 'ak-magazines/partial.twig',
);
