<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array (
  'name' => 'ak-sidebar-kiosk',
  'type' => 'layout',
  'label' => 'کیوسک',
  'default_title' => 'دکه مطبوعات',
  'source' => 'sidebar_widget',
  'partial' => 'ak-sidebar-kiosk/partial.twig',
);
