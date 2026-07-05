<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'name'        => 'ak-ticker',
	'type'        => 'layout',
	'label'       => 'خبر فوری',
	'source'      => 'query',
	'context_key' => 'ticker_items',
	'defaults'    => array(
		'post_type' => 'post',
		'count'     => 5,
		'strategy'  => 'latest',
	),
	'partial'     => 'ak-ticker/partial.twig',
);
