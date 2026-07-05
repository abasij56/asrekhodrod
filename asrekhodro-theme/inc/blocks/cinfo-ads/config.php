<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'name'            => 'cinfo-ads',
	'type'            => 'acf',
	'title'           => 'اطلاعات خودرو — تبلیغات دیوار ۳D',
	'icon'            => 'megaphone',
	'keywords'        => array( 'car', 'cinfo', 'ads', 'تبلیغ', '۳D' ),
	'mode'            => 'edit',
	'supports'        => array(
		'align'  => false,
		'anchor' => false,
	),
	'seed_carsinfo'   => true,
	'seed_order'      => 11,
	'manifest'        => array(
		'label'  => 'اطلاعات خودرو — تبلیغات دیوار ۳D',
		'source' => 'acf',
	),
);
