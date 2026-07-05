<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'name'             => 'cinfo-3dmodel',
	'type'             => 'acf',
	'title'            => 'اطلاعات خودرو — مدل ۳D',
	'icon'             => 'admin-site-alt3',
	'keywords'         => array( 'car', 'cinfo', '3d', 'model', 'خودرو', 'مدل' ),
	'mode'             => 'edit',
	'default_anchor'   => 'model-3d',
	'supports'         => array(
		'align'  => false,
		'anchor' => true,
	),
	'seed_carsinfo'    => true,
	'seed_order'       => 1,
	'manifest'         => array(
		'label'  => 'اطلاعات خودرو — مدل ۳D',
		'source' => 'acf',
	),
);
