<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array (
  'name' => 'ak-newsletter',
  'type' => 'layout',
  'label' => 'خبرنامه',
  'default_title' => 'خبرنامه عصر خودرو',
  'source' => 'static',
  'data_configurable' => false,
  'data' => 
  array (
  ),
  'partial' => 'ak-newsletter/partial.twig',
  'template' => 'ak-newsletter/template.twig',
);
