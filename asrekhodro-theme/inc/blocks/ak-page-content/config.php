<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array (
  'name' => 'ak-page-content',
  'type' => 'layout',
  'label' => 'محتوای صفحه (ویرایشگر)',
  'source' => 'static',
  'partial' => 'ak-page-content/partial.twig',
);
