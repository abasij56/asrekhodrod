<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'name'            => 'cinfo-hero',
	'type'            => 'acf',
	'title'           => 'اطلاعات خودرو — هیرو',
	'icon'            => 'car',
	'keywords'        => array( 'car', 'cinfo', 'hero', 'خودرو', 'امتیاز' ),
	'mode'            => 'edit',
	'supports'        => array(
		'align'  => false,
		'anchor' => true,
	),
	'seed_carsinfo'   => true,
	'seed_order'      => 2,
	'manifest'        => array(
		'label'  => 'اطلاعات خودرو — هیرو',
		'source' => 'acf',
	),
);
