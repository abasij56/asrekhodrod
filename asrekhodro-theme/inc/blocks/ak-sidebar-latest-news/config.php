<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array (
  'name' => 'ak-sidebar-latest-news',
  'type' => 'layout',
  'label' => 'آخرین اخبار (سایدبار)',
  'default_title' => 'آخرین اخبار',
  'source' => 'sidebar_widget',
  'partial' => 'ak-sidebar-latest-news/partial.twig',
);
