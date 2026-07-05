<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'name'            => 'cinfo-faq',
	'type'            => 'acf',
	'title'           => 'اطلاعات خودرو — سوالات متداول',
	'icon'            => 'editor-help',
	'keywords'        => array( 'car', 'cinfo', 'faq', 'خودرو', 'سوالات' ),
	'mode'            => 'edit',
	'default_anchor'  => 'faq',
	'supports'        => array(
		'align'  => false,
		'anchor' => true,
	),
	'seed_carsinfo'   => true,
	'seed_order'      => 8,
	'manifest'        => array(
		'label'  => 'اطلاعات خودرو — سوالات متداول',
		'source' => 'acf',
	),
);
