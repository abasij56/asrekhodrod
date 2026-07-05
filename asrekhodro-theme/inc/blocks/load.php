<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$blocks_dir = ASREKHODRO_THEME_DIR . '/inc/blocks';

require_once $blocks_dir . '/BlockRegistry.php';
require_once $blocks_dir . '/layout/LayoutSchema.php';
require_once $blocks_dir . '/layout/LayoutStorage.php';
require_once $blocks_dir . '/layout/LayoutResolver.php';
require_once $blocks_dir . '/BlockDataResolver.php';
require_once $blocks_dir . '/layout/LayoutEngine.php';
require_once $blocks_dir . '/layout/PageLayout.php';
require_once $blocks_dir . '/layout/LayoutBuilderAdmin.php';
require_once $blocks_dir . '/layout/Homepage.php';
require_once $blocks_dir . '/BlockRenderer.php';
require_once $blocks_dir . '/Support/RateFormatter.php';
require_once $blocks_dir . '/Support/EmbedSanitizer.php';
require_once $blocks_dir . '/CinfoBlocks.php';

\AsreKhodro\Theme\BlockRegistry::boot();
