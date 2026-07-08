<?php

namespace AsreKhodro\Theme\CdnServer\Admin;

use AsreKhodro\Theme\CdnServer\Config;
use AsreKhodro\Theme\ExternalMedia;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Media-library UI: the "Add External Media" panel plus the optional
 * "upload to CDN" alternative. Enqueues assets from the module folder.
 */
final class UploadUi {

	private const ASSETS_REL = '/inc/cdn-server/assets';

	/** @var bool */
	private static bool $upload_ui_rendered = false;

	public static function init(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_admin_assets' ) );
		add_action( 'admin_menu', array( self::class, 'register_submenu' ) );
		add_action( 'post-plupload-upload-ui', array( self::class, 'render_upload_ui' ) );
		add_action( 'post-html-upload-ui', array( self::class, 'render_upload_ui' ) );
	}

	public static function enqueue_admin_assets( string $hook_suffix ): void {
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		if ( ! self::should_enqueue_on_hook( $hook_suffix ) ) {
			return;
		}

		self::register_assets();
	}

	private static function should_enqueue_on_hook( string $hook_suffix ): bool {
		$allowed_hooks = array(
			'upload.php',
			'media-new.php',
			'post.php',
			'post-new.php',
		);

		return in_array( $hook_suffix, $allowed_hooks, true )
			|| str_starts_with( $hook_suffix, 'media_page_' );
	}

	private static function register_assets(): void {
		if ( ! wp_style_is( 'ak-external-media', 'registered' ) ) {
			wp_register_style(
				'ak-external-media',
				self::asset_url( 'css/external-media.css' ),
				array(),
				self::asset_version( 'css/external-media.css' )
			);
		}

		if ( ! wp_script_is( 'ak-external-media', 'registered' ) ) {
			wp_register_script(
				'ak-external-media',
				self::asset_url( 'js/external-media.js' ),
				array( 'jquery', 'media-views' ),
				self::asset_version( 'js/external-media.js' ),
				true
			);

			wp_localize_script(
				'ak-external-media',
				'akExternalMedia',
				array(
					'action'        => ExternalMedia::AJAX_ACTION,
					'nonce'         => wp_create_nonce( ExternalMedia::NONCE_ACTION ),
					'uploadAction'  => Ajax::UPLOAD_ACTION,
					'uploadNonce'   => wp_create_nonce( Ajax::UPLOAD_NONCE ),
					'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
					'cdnEnabled'    => Config::is_configured(),
					'maxSize'       => Config::max_upload_bytes(),
					'accept'        => self::accept_attribute(),
					'uploadBaseUrl' => admin_url( 'upload.php' ),
				'libraryUrl'    => admin_url( 'upload.php' ),
					'i18n'          => array(
						'uploading'     => __( 'در حال آپلود…', 'asrekhodro' ),
						'uploadSuccess' => __( 'فایل با موفقیت روی CDN آپلود و در کتابخانه رسانه ثبت شد.', 'asrekhodro' ),
						'debugTitle'    => __( 'جزئیات مسیر آپلود روی FTP', 'asrekhodro' ),
						'networkFail'   => __( 'خطای شبکه هنگام آپلود.', 'asrekhodro' ),
					'pickFile'      => __( 'ابتدا یک فایل انتخاب کنید.', 'asrekhodro' ),
					'tooLarge'      => __( 'حجم فایل بیش از حد مجاز است.', 'asrekhodro' ),
					'urlsRequired'  => __( 'لطفاً حداقل یک URL وارد کنید.', 'asrekhodro' ),
					),
				)
			);
		}

		wp_enqueue_media();
		wp_enqueue_style( 'ak-external-media' );
		wp_enqueue_script( 'ak-external-media' );
	}

	public static function register_submenu(): void {
		add_submenu_page(
			'upload.php',
			__( 'Add External Media', 'asrekhodro' ),
			__( 'Add External Media', 'asrekhodro' ),
			'upload_files',
			'add-external-media',
			array( self::class, 'render_submenu_page' )
		);
	}

	public static function render_upload_ui(): void {
		if ( ! current_user_can( 'upload_files' ) || self::$upload_ui_rendered ) {
			return;
		}

		self::$upload_ui_rendered = true;
		self::register_assets();
		?>
		<div id="emwi-in-upload-ui">
			<div class="row1"><?php esc_html_e( 'or', 'asrekhodro' ); ?></div>
			<div class="row2 emwi-upload-actions">
				<?php self::render_cdn_section( 'inline' ); ?>
				<button type="button" id="emwi-show" class="button button-large">
					<?php esc_html_e( 'افزودن رسانه خارجی از URL', 'asrekhodro' ); ?>
				</button>
				<?php self::render_url_panel( true, true ); ?>
			</div>
		</div>
		<?php
	}

	public static function render_submenu_page(): void {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'You do not have permission to upload files.', 'asrekhodro' ) );
		}

		self::register_assets();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Add External Media without Import', 'asrekhodro' ); ?></h1>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<?php wp_nonce_field( ExternalMedia::NONCE_ACTION ); ?>
				<?php self::render_panel( false ); ?>
			</form>
		</div>
		<?php
	}

	private static function render_panel( bool $in_upload_ui ): void {
		self::render_url_panel( $in_upload_ui, $in_upload_ui );
		if ( ! $in_upload_ui ) {
			self::render_cdn_section( 'panel' );
		}
	}

	/**
	 * @param bool $in_upload_ui Whether this panel is rendered inside the media modal.
	 * @param bool $hidden       Hide the panel until the user opens it (modal only).
	 */
	private static function render_url_panel( bool $in_upload_ui, bool $hidden = false ): void {
		$urls      = isset( $_GET['urls'] ) ? sanitize_textarea_field( wp_unslash( (string) $_GET['urls'] ) ) : '';
		$error     = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['error'] ) ) : '';
		$width     = isset( $_GET['width'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['width'] ) ) : '';
		$height    = isset( $_GET['height'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['height'] ) ) : '';
		$mime_type = isset( $_GET['mime-type'] ) ? sanitize_mime_type( wp_unslash( (string) $_GET['mime-type'] ) ) : '';
		$show_meta = ! $in_upload_ui && $error !== '';
		?>
		<div id="emwi-media-new-panel" <?php echo $hidden ? 'style="display:none"' : ''; ?>>
			<label id="emwi-urls-label" for="emwi-urls"><?php esc_html_e( 'Add medias from URLs', 'asrekhodro' ); ?></label>
			<textarea
				id="emwi-urls"
				name="urls"
				rows="<?php echo $in_upload_ui ? 3 : 10; ?>"
				placeholder="<?php echo esc_attr__( "Please fill in the media URLs.\nMultiple URLs are supported with each URL specified in one line.", 'asrekhodro' ); ?>"
			><?php echo esc_textarea( $urls ); ?></textarea>
			<div id="emwi-hidden" <?php echo $show_meta ? '' : 'style="display:none"'; ?>>
				<div>
					<span id="emwi-error"><?php echo esc_html( $error ); ?></span>
					<?php esc_html_e( 'Please fill in the following properties manually. If you leave the fields blank (or 0 for width/height), the plugin will try to resolve them automatically.', 'asrekhodro' ); ?>
				</div>
				<div id="emwi-properties">
					<label for="emwi-width"><?php esc_html_e( 'Width', 'asrekhodro' ); ?></label>
					<input id="emwi-width" name="width" type="number" min="0" value="<?php echo esc_attr( $width ); ?>">
					<label for="emwi-height"><?php esc_html_e( 'Height', 'asrekhodro' ); ?></label>
					<input id="emwi-height" name="height" type="number" min="0" value="<?php echo esc_attr( $height ); ?>">
					<label for="emwi-mime-type"><?php esc_html_e( 'MIME Type', 'asrekhodro' ); ?></label>
					<input id="emwi-mime-type" name="mime-type" type="text" value="<?php echo esc_attr( $mime_type ); ?>">
				</div>
			</div>
			<div id="emwi-buttons-row">
				<input type="hidden" name="action" value="<?php echo esc_attr( ExternalMedia::AJAX_ACTION ); ?>">
				<span class="spinner"></span>
				<input type="button" id="emwi-clear" class="button" value="<?php esc_attr_e( 'Clear', 'asrekhodro' ); ?>">
				<input type="button" id="emwi-add" class="button button-primary emwi-url-add" value="<?php esc_attr_e( 'Add', 'asrekhodro' ); ?>">
				<?php if ( $in_upload_ui ) : ?>
					<input type="button" id="emwi-cancel" class="button" value="<?php esc_attr_e( 'Cancel', 'asrekhodro' ); ?>">
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * @param 'inline'|'panel' $context inline = visible in media modal upload tab; panel = inside full page form.
	 */
	private static function render_cdn_section( string $context = 'panel' ): void {
		$cdn_ready  = Config::is_connection_ready();
		$cdn_active = Config::is_configured();
		$missing    = $cdn_ready ? array() : Config::missing_requirements();
		$is_inline  = $context === 'inline';
		$section_id = $is_inline ? 'emwi-cdn-section-inline' : 'emwi-cdn-section';
		?>
		<?php if ( $cdn_ready ) : ?>
			<div id="<?php echo esc_attr( $section_id ); ?>" class="emwi-cdn-section<?php echo $is_inline ? ' emwi-cdn-section--inline' : ''; ?>">
				<?php if ( ! $is_inline ) : ?>
					<div class="emwi-cdn-sep"><span><?php esc_html_e( 'یا', 'asrekhodro' ); ?></span></div>
				<?php endif; ?>
				<label class="emwi-cdn-label" for="<?php echo esc_attr( $section_id ); ?>-file"><?php esc_html_e( 'آپلود فایل روی سرور CDN', 'asrekhodro' ); ?></label>
				<?php if ( ! $cdn_active ) : ?>
					<p class="emwi-cdn-notice emwi-cdn-notice--warn">
						<?php esc_html_e( 'اتصال آماده است اما «فعال‌سازی آپلود CDN» در تنظیمات تم خاموش است. آن را روشن کنید و ذخیره کنید.', 'asrekhodro' ); ?>
					</p>
				<?php endif; ?>
				<div class="emwi-cdn-row">
					<input type="file" id="<?php echo esc_attr( $section_id ); ?>-file" class="emwi-cdn-file" accept="<?php echo esc_attr( self::accept_attribute() ); ?>" <?php echo $cdn_active ? '' : 'disabled'; ?>>
					<button type="button" class="button button-primary button-large emwi-upload-cdn" <?php disabled( ! $cdn_active ); ?>><?php esc_html_e( 'آپلود به CDN', 'asrekhodro' ); ?></button>
					<span class="spinner emwi-cdn-spinner"></span>
				</div>
				<div class="emwi-cdn-error"></div>
				<div class="emwi-cdn-debug" style="display:none;"></div>
			</div>
		<?php elseif ( current_user_can( 'edit_theme_options' ) && $missing !== array() ) : ?>
			<div id="<?php echo esc_attr( $section_id ); ?>" class="emwi-cdn-section emwi-cdn-section--inactive<?php echo $is_inline ? ' emwi-cdn-section--inline' : ''; ?>">
				<p class="emwi-cdn-notice">
					<strong><?php esc_html_e( 'آپلود CDN غیرفعال است:', 'asrekhodro' ); ?></strong>
				</p>
				<ul class="emwi-cdn-missing">
					<?php foreach ( $missing as $item ) : ?>
						<li><?php echo esc_html( $item ); ?></li>
					<?php endforeach; ?>
				</ul>
				<p class="description">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=asrekhodro-settings' ) ); ?>">
						<?php esc_html_e( 'رفتن به تنظیمات سرور CDN', 'asrekhodro' ); ?>
					</a>
				</p>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Build an accept="" attribute from the allowed MIME list.
	 */
	private static function accept_attribute(): string {
		$types = Config::allowed_types();

		return $types === array() ? '' : implode( ',', $types );
	}

	private static function asset_url( string $relative ): string {
		return ASREKHODRO_THEME_URI . self::ASSETS_REL . '/' . ltrim( $relative, '/' );
	}

	private static function asset_version( string $relative ): string {
		$path = ASREKHODRO_THEME_DIR . self::ASSETS_REL . '/' . ltrim( $relative, '/' );

		return file_exists( $path ) ? (string) filemtime( $path ) : ASREKHODRO_THEME_VERSION;
	}
}
