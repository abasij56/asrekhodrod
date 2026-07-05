<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'name'            => 'cinfo-overview',
	'type'            => 'acf',
	'title'           => 'اطلاعات خودرو — اطلاعات کلی',
	'icon'            => 'editor-alignleft',
	'keywords'        => array( 'car', 'cinfo', 'overview', 'خودرو', 'متن' ),
	'mode'            => 'edit',
	'default_anchor'  => 'overview',
	'supports'        => array(
		'align'  => false,
		'anchor' => true,
	),
	'seed_carsinfo'   => true,
	'seed_order'      => 4,
	'manifest'        => array(
		'label'  => 'اطلاعات خودرو — اطلاعات کلی',
		'source' => 'acf',
	),
);
