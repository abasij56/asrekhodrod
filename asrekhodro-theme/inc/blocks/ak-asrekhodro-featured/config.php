<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'name'          => 'ak-asrekhodro-featured',
	'type'          => 'layout',
	'label'         => 'اخبار ویژه عصر خودرو',
	'default_title' => 'اخبار ویژه عصر خودرو',
	'source'        => 'query',
	'defaults'      => array(
		'post_type' => 'post',
		'count'     => 6,
		'strategy'  => 'latest',
	),
	'partial'       => 'ak-asrekhodro-featured/partial.twig',
);
