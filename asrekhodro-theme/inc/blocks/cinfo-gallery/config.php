<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'name'            => 'cinfo-gallery',
	'type'            => 'acf',
	'title'           => 'اطلاعات خودرو — گالری تصاویر',
	'icon'            => 'format-gallery',
	'keywords'        => array( 'car', 'cinfo', 'gallery', 'خودرو', 'گالری' ),
	'mode'            => 'edit',
	'default_anchor'  => 'gallery',
	'supports'        => array(
		'align'  => false,
		'anchor' => true,
	),
	'seed_carsinfo'   => true,
	'seed_order'      => 7,
	'manifest'        => array(
		'label'  => 'اطلاعات خودرو — گالری تصاویر',
		'source' => 'acf',
	),
);
