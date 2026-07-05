<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'name'          => 'ak-picture-frame',
	'type'          => 'layout',
	'label'         => 'در قاب تصویر',
	'default_title' => 'در قاب تصویر',
	'source'        => 'query',
	'defaults'      => array(
		'post_type' => 'post',
		'count'     => 10,
		'strategy'  => 'latest',
	),
	'partial'       => 'ak-picture-frame/partial.twig',
);
