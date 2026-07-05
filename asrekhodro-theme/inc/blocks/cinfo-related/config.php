<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'name'            => 'cinfo-related',
	'type'            => 'acf',
	'title'           => 'اطلاعات خودرو — مدل‌های مرتبط',
	'icon'            => 'images-alt2',
	'keywords'        => array( 'car', 'cinfo', 'related', 'خودرو', 'مدل' ),
	'mode'            => 'edit',
	'default_anchor'  => 'related',
	'supports'        => array(
		'align'  => false,
		'anchor' => true,
	),
	'seed_carsinfo'   => true,
	'seed_order'      => 9,
	'manifest'        => array(
		'label'  => 'اطلاعات خودرو — مدل‌های مرتبط',
		'source' => 'acf',
	),
);
