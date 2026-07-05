<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'name'          => 'cinfo-facts',
	'type'          => 'acf',
	'title'         => 'اطلاعات خودرو — مشخصات کلیدی',
	'icon'          => 'list-view',
	'keywords'      => array( 'car', 'cinfo', 'facts', 'خودرو', 'مشخصات' ),
	'mode'          => 'edit',
	'supports'      => array(
		'align'  => false,
		'anchor' => true,
	),
	'seed_carsinfo' => true,
	'seed_order'    => 3,
	'manifest'      => array(
		'label'  => 'اطلاعات خودرو — مشخصات کلیدی',
		'source' => 'acf',
	),
);
