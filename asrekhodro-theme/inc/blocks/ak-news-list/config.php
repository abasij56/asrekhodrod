<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array (
  'name' => 'ak-news-list',
  'type' => 'layout',
  'label' => 'فهرست اخبار',
  'default_title' => 'آخرین اخبار خودرو',
  'source' => 'query',
  'section' => 'news_list',
  'data' => 
  array (
    0 => 'news_list',
    1 => 'news_archive_url',
  ),
  'defaults' => 
  array (
    'post_type' => 'post',
    'count' => 40,
    'strategy' => 'latest',
  ),
  'context_key' => 'news_list',
  'partial' => 'ak-news-list/partial.twig',
  'template' => 'ak-news-list/template.twig',
);
