<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'name'          => 'ak-sidebar-videos',
	'type'          => 'layout',
	'label'         => 'ویدیوها (سایدبار)',
	'default_title' => 'ویدیوها',
	'source'        => 'sidebar_widget',
	'partial'       => 'ak-sidebar-videos/partial.twig',
);
