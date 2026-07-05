<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'name'            => 'cinfo-video',
	'type'            => 'acf',
	'title'           => 'اطلاعات خودرو — ویدیو بررسی',
	'icon'            => 'video-alt3',
	'keywords'        => array( 'car', 'cinfo', 'video', 'خودرو', 'ویدیو' ),
	'mode'            => 'edit',
	'default_anchor'  => 'video',
	'supports'        => array(
		'align'  => false,
		'anchor' => true,
	),
	'seed_carsinfo'   => true,
	'seed_order'      => 5,
	'manifest'        => array(
		'label'  => 'اطلاعات خودرو — ویدیو بررسی',
		'source' => 'acf',
	),
);
