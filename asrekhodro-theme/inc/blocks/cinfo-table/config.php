<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'name'            => 'cinfo-table',
	'type'            => 'acf',
	'title'           => 'اطلاعات خودرو — جدول',
	'icon'            => 'editor-table',
	'keywords'        => array( 'car', 'cinfo', 'table', 'specs', 'خودرو', 'جدول' ),
	'mode'            => 'edit',
	'default_anchor'  => 'specs',
	'supports'        => array(
		'align'  => false,
		'anchor' => true,
	),
	'seed_carsinfo'   => true,
	'seed_order'      => 6,
	'manifest'        => array(
		'label'  => 'اطلاعات خودرو — جدول',
		'source' => 'acf',
	),
);
