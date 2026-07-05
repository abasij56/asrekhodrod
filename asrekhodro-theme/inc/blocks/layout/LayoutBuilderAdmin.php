<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Visual admin page for editing layout placements per zone.
 */
final class LayoutBuilderAdmin {

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'register_menu' ), 100 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
		add_action( 'wp_ajax_ak_save_layout', array( self::class, 'ajax_save' ) );
		add_action( 'wp_ajax_ak_search_posts_for_layout', array( self::class, 'ajax_search_posts' ) );
		add_action( 'wp_ajax_ak_resolve_layout_manual_posts', array( self::class, 'ajax_resolve_manual_posts' ) );
	}

	public static function register_menu(): void {
		add_submenu_page(
			'asrekhodro-settings',
			__( 'چیدمان صفحات', 'asrekhodro' ),
			__( 'چیدمان صفحات', 'asrekhodro' ),
			'edit_theme_options',
			'asrekhodro-layout-builder',
			array( self::class, 'render_page' )
		);
	}

	public static function enqueue_assets( string $hook ): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( (string) $_GET['page'] ) : '';
		if ( $page !== 'asrekhodro-layout-builder' ) {
			return;
		}

		$js_path  = ASREKHODRO_THEME_DIR . '/assets/admin/layout-builder.js';
		$css_path = ASREKHODRO_THEME_DIR . '/assets/admin/layout-builder.css';
		$base     = ASREKHODRO_THEME_URI . '/assets/admin';
		$ver      = file_exists( $js_path ) ? (string) filemtime( $js_path ) : ASREKHODRO_THEME_VERSION;

		wp_enqueue_style(
			'ak-layout-builder',
			$base . '/layout-builder.css',
			array(),
			file_exists( $css_path ) ? (string) filemtime( $css_path ) : $ver
		);

		wp_enqueue_script(
			'ak-layout-builder',
			$base . '/layout-builder.js',
			array(),
			$ver,
			true
		);

		wp_localize_script( 'ak-layout-builder', 'akLayoutBuilder', self::client_config() );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function client_config(): array {
		$appearance_id = Appearance::id();
		$pages         = array();

		foreach ( LayoutSchema::page_choices( $appearance_id ) as $page_key => $page_label ) {
			$zones_def = Appearance::page_zones( $page_key, $appearance_id );
			$zones     = array();

			foreach ( $zones_def as $zone_key => $zone_config ) {
				if ( ! is_array( $zone_config ) ) {
					continue;
				}
				$allowed = LayoutSchema::zone_block_names( $zone_config, $appearance_id ) ?? array();
				$blocks  = array();
				foreach ( $allowed as $block_name ) {
					$meta = LayoutSchema::block_meta( $block_name );
					$blocks[ $block_name ] = self::block_client_meta( $block_name, $meta );
				}

				$zones[ $zone_key ] = array(
					'label'    => (string) ( $zone_config['label'] ?? LayoutSchema::ZONE_LABELS[ $zone_key ] ?? $zone_key ),
					'multiple' => (bool) ( $zone_config['multiple'] ?? true ),
					'blocks'   => $blocks,
				);
			}

			$page_config = Appearance::load_manifest( $appearance_id )['pages'][ $page_key ] ?? array();

			$pages[ $page_key ] = array(
				'label'       => $page_label,
				'layout_mode' => (string) ( $page_config['layout_mode'] ?? 'blocks' ),
				'zones'       => $zones,
				'defaults'    => self::defaults_as_rows( $page_key, $appearance_id ),
			);
		}

		$categories = array();
		$terms      = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'number'     => 200,
			)
		);
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( $term instanceof \WP_Term ) {
					$categories[] = array(
						'id'   => (int) $term->term_id,
						'name' => $term->name,
					);
				}
			}
		}

		return array(
			'appearanceId'   => $appearance_id,
			'appearanceLabel'=> Appearance::choices_for_acf()[ $appearance_id ] ?? $appearance_id,
			'pages'          => $pages,
			'placements'     => self::builder_placements_grouped(),
			'hasCustom'      => LayoutStorage::has_custom(),
			'postTypes'      => LayoutSchema::post_type_choices(),
			'strategies'     => LayoutSchema::STRATEGY_LABELS,
			'categories'     => $categories,
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( 'ak_layout_builder' ),
			'settingsUrl'    => admin_url( 'admin.php?page=asrekhodro-settings' ),
			'i18n'           => array(
				'save'           => __( 'ذخیره چیدمان', 'asrekhodro' ),
				'saved'          => __( 'چیدمان ذخیره شد.', 'asrekhodro' ),
				'error'          => __( 'خطا در ذخیره.', 'asrekhodro' ),
				'addBlock'       => __( 'افزودن بلاک', 'asrekhodro' ),
				'editBlock'      => __( 'ویرایش بلاک', 'asrekhodro' ),
				'moveUp'         => __( 'بالا', 'asrekhodro' ),
				'moveDown'       => __( 'پایین', 'asrekhodro' ),
				'dragHandle'     => __( 'کشیدن برای جابجایی', 'asrekhodro' ),
				'removeBlock'    => __( 'حذف', 'asrekhodro' ),
				'resetPage'      => __( 'بازگشت به پیش‌فرض این صفحه', 'asrekhodro' ),
				'resetAll'       => __( 'پاک کردن همه تنظیمات سفارشی', 'asrekhodro' ),
				'usingDefaults'  => __( 'در حال نمایش پیش‌فرض manifest — با ذخیره، چیدمان سفارشی ثبت می‌شود.', 'asrekhodro' ),
				'customActive'   => __( 'چیدمان سفارشی فعال است.', 'asrekhodro' ),
				'selectBlock'    => __( 'انتخاب بلاک', 'asrekhodro' ),
				'cancel'         => __( 'انصراف', 'asrekhodro' ),
				'confirmRemove'  => __( 'این بلاک حذف شود؟', 'asrekhodro' ),
				'confirmResetAll'=> __( 'همه چیدمان‌های سفارشی پاک شود و به پیش‌فرض برگردید؟', 'asrekhodro' ),
				'emptyZone'      => __( 'بلاکی اضافه نشده — دکمه زیر را بزنید.', 'asrekhodro' ),
				'manualPosts'    => __( 'جستجوی نوشته برای افزودن…', 'asrekhodro' ),
				'dataSettings'   => __( 'تنظیمات داده', 'asrekhodro' ),
				'postType'       => __( 'نوع پست', 'asrekhodro' ),
				'category'       => __( 'دسته‌بندی', 'asrekhodro' ),
				'categoryAll'    => __( '— همه —', 'asrekhodro' ),
				'count'          => __( 'تعداد', 'asrekhodro' ),
				'strategy'       => __( 'نحوه گزینش', 'asrekhodro' ),
				'blockLabel'     => __( 'بلاک', 'asrekhodro' ),
				'blockTitle'     => __( 'عنوان بخش', 'asrekhodro' ),
				'blockTitleHint' => __( 'خالی بگذارید تا عنوان نمایش داده نشود.', 'asrekhodro' ),
				'nothingToSave'  => __( 'تغییری برای ذخیره نیست.', 'asrekhodro' ),
				'unsavedChanges' => __( 'تغییرات ذخیره نشده — دکمه ذخیره را بزنید.', 'asrekhodro' ),
				'systemContent'  => __( 'محتوای سیستمی (ثابت در قالب)', 'asrekhodro' ),
			),
		);
	}

	/**
	 * @return array<string, list<array<string, mixed>>>
	 */
	private static function builder_placements_grouped(): array {
		$grouped  = LayoutStorage::grouped_by_page();
		$expanded = array();

		foreach ( $grouped as $page_key => $rows ) {
			$expanded[ $page_key ] = LayoutSchema::expand_sidebar_rail_rows( is_array( $rows ) ? $rows : array() );
		}

		return $expanded;
	}

	/**
	 * @param array<string, mixed> $meta
	 * @return array<string, mixed>
	 */
	private static function block_client_meta( string $block_name, array $meta ): array {
		$source   = (string) ( $meta['source'] ?? 'legacy' );
		$defaults = is_array( $meta['defaults'] ?? null ) ? $meta['defaults'] : array();

		return array(
			'label'            => (string) ( $meta['label'] ?? $block_name ),
			'source'           => $source,
			'defaultTitle'     => (string) ( $meta['default_title'] ?? '' ),
			'titleConfigurable'=> true,
			'defaults'         => array(
				'post_type' => (string) ( $defaults['post_type'] ?? 'post' ),
				'count'     => (int) ( $defaults['count'] ?? 10 ),
				'strategy'  => (string) ( $defaults['strategy'] ?? 'latest' ),
				'category'  => (int) ( $defaults['category'] ?? 0 ),
			),
			'dataConfigurable' => (bool) ( $meta['data_configurable'] ?? true ),
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function defaults_as_rows( string $page_key, string $appearance_id ): array {
		$defaults = Appearance::page_defaults( $page_key, $appearance_id );
		$rows     = array();

		foreach ( $defaults as $placement ) {
			if ( ! is_array( $placement ) ) {
				continue;
			}
			$rows[] = array_merge(
				array( 'placement_page' => $page_key ),
				array(
					'placement_zone'  => (string) ( $placement['zone'] ?? '' ),
					'placement_block' => (string) ( $placement['block'] ?? '' ),
				),
				self::default_data_fields( (string) ( $placement['block'] ?? '' ) )
			);
		}

		return $rows;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function default_data_fields( string $block_name ): array {
		$meta     = LayoutSchema::block_meta( $block_name );
		$defaults = is_array( $meta['defaults'] ?? null ) ? $meta['defaults'] : array();
		$fields   = array(
			'data_post_type' => (string) ( $defaults['post_type'] ?? 'post' ),
			'data_count'     => (int) ( $defaults['count'] ?? 10 ),
			'data_strategy'  => (string) ( $defaults['strategy'] ?? 'latest' ),
		);

		if ( ! empty( $defaults['category'] ) ) {
			$fields['data_category'] = (int) $defaults['category'];
		}

		return $fields;
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}

		?>
		<div class="wrap ak-lb-wrap">
			<h1><?php esc_html_e( 'چیدمان صفحات', 'asrekhodro' ); ?></h1>
			<p class="ak-lb-intro">
				<?php esc_html_e( 'صفحه را انتخاب کنید و در هر موقعیت بلاک اضافه یا مرتب کنید — شبیه ساختار واقعی سایت.', 'asrekhodro' ); ?>
			</p>
			<div id="ak-layout-builder-app" class="ak-lb-app" aria-live="polite"></div>
		</div>
		<?php
	}

	public static function ajax_save(): void {
		check_ajax_referer( 'ak_layout_builder', 'nonce' );

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		$raw = isset( $_POST['placements'] ) ? wp_unslash( $_POST['placements'] ) : '[]';
		$decoded = json_decode( is_string( $raw ) ? $raw : '[]', true );

		if ( ! is_array( $decoded ) ) {
			wp_send_json_error( array( 'message' => 'invalid_json' ), 400 );
		}

		$clear_raw = isset( $_POST['clear_pages'] ) ? wp_unslash( $_POST['clear_pages'] ) : '[]';
		$clear     = json_decode( is_string( $clear_raw ) ? $clear_raw : '[]', true );
		$clear     = is_array( $clear ) ? $clear : array();

		if ( $decoded === array() && $clear === array() ) {
			LayoutStorage::clear();
		} else {
			LayoutStorage::save( $decoded, $clear );
		}

		wp_send_json_success( array( 'saved' => true ) );
	}

	public static function ajax_search_posts(): void {
		check_ajax_referer( 'ak_layout_builder', 'nonce' );

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		$search    = sanitize_text_field( (string) ( $_GET['q'] ?? '' ) );
		$post_type = sanitize_key( (string) ( $_GET['post_type'] ?? 'post' ) );
		$allowed   = array_keys( LayoutSchema::post_type_choices() );

		if ( ! in_array( $post_type, $allowed, true ) ) {
			$post_type = 'post';
		}

		$query = new \WP_Query(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				's'              => $search,
				'posts_per_page' => 15,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$items[] = array(
				'id'    => (int) $post->ID,
				'title' => get_the_title( $post ),
			);
		}

		wp_send_json_success( array( 'items' => $items ) );
	}

	public static function ajax_resolve_manual_posts(): void {
		check_ajax_referer( 'ak_layout_builder', 'nonce' );

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		$raw = sanitize_text_field( (string) ( $_GET['ids'] ?? '' ) );
		$ids = array_values(
			array_filter(
				array_map( 'intval', explode( ',', $raw ) )
			)
		);

		$titles = array();
		foreach ( $ids as $id ) {
			if ( $id <= 0 ) {
				continue;
			}

			$title = get_the_title( $id );
			if ( $title !== '' ) {
				$titles[ (string) $id ] = $title;
			}
		}

		wp_send_json_success( array( 'titles' => $titles ) );
	}
}
