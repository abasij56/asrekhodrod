<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'name'            => 'cinfo-comments',
	'type'            => 'acf',
	'title'           => 'اطلاعات خودرو — نظرات',
	'icon'            => 'admin-comments',
	'keywords'        => array( 'car', 'cinfo', 'comments', 'خودرو', 'نظرات' ),
	'mode'            => 'edit',
	'default_anchor'  => 'comments',
	'supports'        => array(
		'align'  => false,
		'anchor' => true,
	),
	'seed_carsinfo'   => true,
	'seed_order'      => 10,
	'manifest'        => array(
		'label'  => 'اطلاعات خودرو — نظرات',
		'source' => 'acf',
	),
);
