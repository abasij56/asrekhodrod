<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array (
  'name' => 'ak-reviews',
  'type' => 'layout',
  'label' => 'بررسی خودرو',
  'default_title' => 'تست و بررسی خودرو',
  'source' => 'query',
  'section' => 'reviews',
  'data' => 
  array (
    0 => 'reviews',
  ),
  'defaults' => 
  array (
    'post_type' => 'ak_review',
    'count' => 3,
    'strategy' => 'latest',
  ),
  'context_key' => 'reviews',
  'partial' => 'ak-reviews/partial.twig',
  'template' => 'ak-reviews/template.twig',
);
