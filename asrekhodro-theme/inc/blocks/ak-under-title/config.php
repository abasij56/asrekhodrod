<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'name'       => 'ak-under-title',
	'type'       => 'acf',
	'title'      => 'تیتر دوم',
	'icon'       => 'editor-textcolor',
	'keywords'   => array( 'undertitle', 'subtitle', 'زیرتیتر', 'تیتر دوم', 'خبر' ),
	'mode'       => 'preview',
	'post_types' => array( 'post' ),
	'supports'   => array(
		'align'  => false,
		'anchor' => false,
		'mode'   => false,
	),
);
