<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array (
  'name' => 'ak-sidebar-rail',
  'type' => 'layout',
  'label' => 'سایدبار (پربازدید / کیوسک) — قدیمی',
  'source' => 'sidebar_rail',
  'hidden' => true,
  'partial' => 'ak-sidebar-rail/partial.twig',
);
