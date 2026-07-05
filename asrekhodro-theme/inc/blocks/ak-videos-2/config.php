<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'name'          => 'ak-videos-2',
	'type'          => 'layout',
	'label'         => 'ویدیوها 2',
	'default_title' => 'ویدیوها 2',
	'source'        => 'query',
	'context_key'   => 'videos_2',
	'defaults'      => array(
		'post_type' => 'ak_video',
		'count'     => 5,
		'strategy'  => 'latest',
	),
	'full_bleed'    => true,
	'partial'       => 'ak-videos-2/partial.twig',
);
