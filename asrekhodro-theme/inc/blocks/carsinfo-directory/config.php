<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'name'     => 'carsinfo-directory',
	'type'     => 'acf',
	'title'    => 'دانشنامه خودرو — آرشیو',
	'icon'     => 'car',
	'keywords' => array( 'carsinfo', 'directory', 'archive', 'دانشنامه', 'برند', 'خودرو' ),
	'mode'     => 'edit',
	'supports' => array(
		'align'  => false,
		'anchor' => true,
	),
	'manifest' => array(
		'label'    => 'دانشنامه خودرو — آرشیو',
		'source'   => 'acf',
		'template' => 'carsinfo-directory/template.twig',
	),
);
