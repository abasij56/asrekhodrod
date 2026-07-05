<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'name'          => 'ak-hero',
	'type'          => 'layout',
	'label'         => 'اسلایدر اصلی',
	'default_title' => 'اسلایدر اصلی',
	'source'        => 'query',
	'defaults'      => array(
		'post_type' => 'post',
		'count'     => 5,
		'strategy'  => 'latest',
	),
	'partial'       => 'ak-hero/partial.twig',
);
