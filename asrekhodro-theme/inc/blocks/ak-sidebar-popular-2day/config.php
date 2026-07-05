<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array (
  'name' => 'ak-sidebar-popular-2day',
  'type' => 'layout',
  'label' => 'پربازدید های هفته',
  'default_title' => 'پربازدید های هفته',
  'source' => 'sidebar_widget',
  'partial' => 'ak-sidebar-popular-2day/partial.twig',
);
