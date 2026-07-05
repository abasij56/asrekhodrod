<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array (
  'name' => 'ak-videos',
  'type' => 'layout',
  'label' => 'ویدیوها',
  'default_title' => 'ویدئوهای عصر خودرو',
  'source' => 'query',
  'section' => 'videos',
  'data' => 
  array (
    0 => 'videos',
  ),
  'defaults' => 
  array (
    'post_type' => 'ak_video',
    'count' => 4,
    'strategy' => 'latest',
  ),
  'context_key' => 'videos',
  'partial' => 'ak-videos/partial.twig',
  'template' => 'ak-videos/template.twig',
);
