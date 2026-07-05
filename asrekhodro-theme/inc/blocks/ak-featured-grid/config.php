<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array (
  'name' => 'ak-featured-grid',
  'type' => 'layout',
  'label' => 'اخبار ویژه',
  'default_title' => 'اخبار ویژه',
  'source' => 'query',
  'section' => 'featured',
  'data' => 
  array (
    0 => 'featured',
  ),
  'defaults' => 
  array (
    'post_type' => 'post',
    'count' => 14,
    'strategy' => 'latest',
  ),
  'context_key' => 'featured_posts',
  'partial' => 'ak-featured-grid/partial.twig',
  'template' => 'ak-featured-grid/template.twig',
);
