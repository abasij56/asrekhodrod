<?php

namespace AsreKhodro\Theme\CdnServer;

use AsreKhodro\Theme\CdnServer\Admin\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ACF "CDN Server" tab on the theme settings page, plus the test-connection
 * button assets. All field definitions live here (not inline in AcfFields.php).
 */
final class Settings {

	private const OPTIONS_PAGE = 'asrekhodro-settings';
	private const ASSETS_REL   = '/inc/cdn-server/assets';

	/**
	 * Append the CDN Server tab + fields to the main theme-options group so it
	 * appears as another vertical tab (registered on `ak_theme_options_fields`).
	 *
	 * @param array<int, array<string, mixed>> $fields
	 * @return array<int, array<string, mixed>>
	 */
	public static function append_option_fields( array $fields ): array {
		return array_merge( $fields, self::fields() );
	}

	/**
	 * Enqueue the test-connection script on the theme settings page.
	 */
	public static function enqueue( string $hook_suffix ): void {
		if ( ! \AsreKhodro\Theme\AuthorAccess::can_manage_theme_settings() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_settings_page = ( $screen && $screen->id === 'toplevel_page_' . self::OPTIONS_PAGE )
			|| str_contains( $hook_suffix, self::OPTIONS_PAGE );

		if ( ! $is_settings_page ) {
			return;
		}

		$rel  = self::ASSETS_REL . '/js/settings-test.js';
		$path = ASREKHODRO_THEME_DIR . $rel;

		if ( ! file_exists( $path ) ) {
			return;
		}

		wp_enqueue_script(
			'ak-cdn-settings-test',
			ASREKHODRO_THEME_URI . $rel,
			array( 'jquery' ),
			(string) filemtime( $path ),
			true
		);

		wp_localize_script(
			'ak-cdn-settings-test',
			'akCdnSettings',
			array(
				'testAction' => Ajax::TEST_ACTION,
				'testNonce'  => wp_create_nonce( Ajax::TEST_NONCE ),
				'i18n'       => array(
					'testing'    => __( 'در حال بررسی اتصال…', 'asrekhodro' ),
					'failed'     => __( 'اتصال ناموفق بود.', 'asrekhodro' ),
					'debugTitle' => __( 'مشخصات اتصال استفاده‌شده', 'asrekhodro' ),
				),
			)
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function fields(): array {
		$defaults = Config::defaults();

		return array_merge(
			self::watermark_fields(),
			self::image_optimize_fields(),
			self::cdn_fields( $defaults )
		);
	}

	/**
	 * Watermark tab — placed above CDN Server.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function watermark_fields(): array {
		$when_enabled = array(
			array(
				array(
					'field'    => 'field_ak_site_watermark_enabled',
					'operator' => '==',
					'value'    => '1',
				),
			),
		);

		return array(
			array(
				'key'       => 'field_ak_watermark_tab',
				'label'     => __( 'واترمارک', 'asrekhodro' ),
				'name'      => '',
				'type'      => 'tab',
				'placement' => 'left',
			),
			array(
				'key'           => 'field_ak_site_watermark_enabled',
				'label'         => __( 'افزودن واترمارک به تصاویر', 'asrekhodro' ),
				'name'          => 'site_watermark_enabled',
				'type'          => 'true_false',
				'ui'            => 1,
				'ui_on_text'    => __( 'فعال', 'asrekhodro' ),
				'ui_off_text'   => __( 'غیرفعال', 'asrekhodro' ),
				'default_value' => 0,
				'instructions'  => __( 'با فعال شدن، هنگام آپلود تصویر به CDN واترمارک روی عکس ادغام می‌شود.', 'asrekhodro' ),
			),
			array(
				'key'               => 'field_ak_site_watermark',
				'label'             => __( 'تصویر واترمارک', 'asrekhodro' ),
				'name'              => 'site_watermark',
				'type'              => 'image',
				'return_format'     => 'id',
				'preview_size'      => 'medium',
				'mime_types'        => 'png,webp',
				'instructions'      => __( 'PNG با پس‌زمینه شفاف توصیه می‌شود.', 'asrekhodro' ),
				'conditional_logic' => $when_enabled,
			),
			array(
				'key'               => 'field_ak_site_watermark_position',
				'label'             => __( 'موقعیت واترمارک', 'asrekhodro' ),
				'name'              => 'site_watermark_position',
				'type'              => 'select',
				'choices'           => array(
					'bottom_left'  => __( 'پایین چپ', 'asrekhodro' ),
					'bottom_right' => __( 'پایین راست', 'asrekhodro' ),
					'top_left'     => __( 'بالا چپ', 'asrekhodro' ),
					'top_right'    => __( 'بالا راست', 'asrekhodro' ),
				),
				'default_value'     => 'bottom_left',
				'ui'                => 1,
				'conditional_logic' => $when_enabled,
			),
			array(
				'key'               => 'field_ak_site_watermark_opacity',
				'label'             => __( 'شفافیت واترمارک (٪)', 'asrekhodro' ),
				'name'              => 'site_watermark_opacity',
				'type'              => 'number',
				'default_value'     => 70,
				'min'               => 10,
				'max'               => 100,
				'step'              => 5,
				'conditional_logic' => $when_enabled,
			),
			array(
				'key'               => 'field_ak_site_watermark_margin',
				'label'             => __( 'فاصله واترمارک از لبه (پیکسل)', 'asrekhodro' ),
				'name'              => 'site_watermark_margin',
				'type'              => 'number',
				'default_value'     => 16,
				'min'               => 0,
				'max'               => 120,
				'step'              => 1,
				'conditional_logic' => $when_enabled,
			),
		);
	}

	/**
	 * Image optimize tab — resize + compress before watermark/upload.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function image_optimize_fields(): array {
		$when_enabled = array(
			array(
				array(
					'field'    => 'field_ak_cdn_image_optimize_enabled',
					'operator' => '==',
					'value'    => '1',
				),
			),
		);

		return array(
			array(
				'key'       => 'field_ak_cdn_image_optimize_tab',
				'label'     => __( 'بهینه‌سازی تصویر', 'asrekhodro' ),
				'name'      => '',
				'type'      => 'tab',
				'placement' => 'left',
			),
			array(
				'key'           => 'field_ak_cdn_image_optimize_enabled',
				'label'         => __( 'فعال‌سازی بهینه‌سازی قبل از آپلود CDN', 'asrekhodro' ),
				'name'          => 'cdn_image_optimize_enabled',
				'type'          => 'true_false',
				'ui'            => 1,
				'ui_on_text'    => __( 'فعال', 'asrekhodro' ),
				'ui_off_text'   => __( 'غیرفعال', 'asrekhodro' ),
				'default_value' => 1,
				'instructions'  => __( 'برای آپلود رسانه به CDN و انتقال رسانه به CDN: ابتدا در صورت نیاز پهنا کوچک می‌شود، سپس فشرده‌سازی، بعد واترمارک و در نهایت آپلود.', 'asrekhodro' ),
			),
			array(
				'key'               => 'field_ak_cdn_image_max_width',
				'label'             => __( 'حداکثر پهنای تصویر (پیکسل)', 'asrekhodro' ),
				'name'              => 'cdn_image_max_width',
				'type'              => 'number',
				'default_value'     => 1200,
				'min'               => 100,
				'max'               => 5000,
				'step'              => 50,
				'instructions'      => __( 'اگر پهنای تصویر بیشتر از این مقدار باشد، با حفظ نسبت کوچک می‌شود. تصاویر باریک‌تر دست‌نخورده می‌مانند (فقط فشرده می‌شوند).', 'asrekhodro' ),
				'conditional_logic' => $when_enabled,
			),
			array(
				'key'               => 'field_ak_cdn_image_jpeg_quality',
				'label'             => __( 'کیفیت JPEG', 'asrekhodro' ),
				'name'              => 'cdn_image_jpeg_quality',
				'type'              => 'number',
				'default_value'     => 88,
				'min'               => 60,
				'max'               => 95,
				'step'              => 1,
				'instructions'      => __( '۸۸ پیشنهاد می‌شود؛ بالاتر = حجم بیشتر، پایین‌تر = فشرده‌تر.', 'asrekhodro' ),
				'conditional_logic' => $when_enabled,
			),
			array(
				'key'               => 'field_ak_cdn_image_webp_quality',
				'label'             => __( 'کیفیت WebP', 'asrekhodro' ),
				'name'              => 'cdn_image_webp_quality',
				'type'              => 'number',
				'default_value'     => 85,
				'min'               => 60,
				'max'               => 95,
				'step'              => 1,
				'conditional_logic' => $when_enabled,
			),
			array(
				'key'               => 'field_ak_cdn_image_png_compression',
				'label'             => __( 'فشرده‌سازی PNG (۰–۹)', 'asrekhodro' ),
				'name'              => 'cdn_image_png_compression',
				'type'              => 'number',
				'default_value'     => 6,
				'min'               => 0,
				'max'               => 9,
				'step'              => 1,
				'instructions'      => __( 'عدد بالاتر = فایل کوچک‌تر و کندتر. ۶ معمولاً تعادل خوبی است.', 'asrekhodro' ),
				'conditional_logic' => $when_enabled,
			),
		);
	}

	/**
	 * @param array<string, mixed> $defaults
	 * @return array<int, array<string, mixed>>
	 */
	private static function cdn_fields( array $defaults ): array {
		return array(
			array(
				'key'       => 'field_ak_cdn_tab',
				'label'     => __( 'سرور CDN', 'asrekhodro' ),
				'name'      => '',
				'type'      => 'tab',
				'placement' => 'left',
			),
			array(
				'key'           => 'field_ak_cdn_enabled',
				'label'         => __( 'فعال‌سازی آپلود CDN', 'asrekhodro' ),
				'name'          => 'cdn_enabled',
				'type'          => 'true_false',
				'ui'            => 1,
				'default_value' => (bool) ( $defaults['enabled'] ?? false ) ? 1 : 0,
				'instructions'  => __( 'با فعال شدن و تکمیل تنظیمات زیر، دکمه «آپلود به CDN» در بخش افزودن رسانه خارجی نمایش داده می‌شود.', 'asrekhodro' ),
			),
			array(
				'key'          => 'field_ak_cdn_public_base_url',
				'label'        => __( 'نشانی پایه عمومی', 'asrekhodro' ),
				'name'         => 'cdn_public_base_url',
				'type'         => 'url',
				'placeholder'  => 'http://media.asrekhodro.com/AsreKhodro',
				'instructions' => __( 'آدرس عمومی فایل‌ها؛ آدرس نهایی فایل = این نشانی + مسیر آپلود.', 'asrekhodro' ),
			),
			array(
				'key'           => 'field_ak_cdn_protocol',
				'label'         => __( 'پروتکل', 'asrekhodro' ),
				'name'          => 'cdn_protocol',
				'type'          => 'select',
				'choices'       => array(
					'ftp'   => 'FTP (ساده)',
					'ftps'  => 'FTPS (FTP روی TLS)',
					'sftp'  => 'SFTP (نیازمند ext-ssh2)',
					'local' => 'کپی محلی (همان سرور — بدون FTP)',
				),
				'default_value' => (string) ( $defaults['protocol'] ?? 'ftp' ),
				'ui'            => 1,
				'instructions'  => __( 'اگر وردپرس و پوشه رسانه روی یک هاست هستند و FTP از داخل سرور Connection refused می‌دهد، «کپی محلی» را انتخاب کنید.', 'asrekhodro' ),
			),
			array(
				'key'          => 'field_ak_cdn_host',
				'label'        => __( 'میزبان (Host)', 'asrekhodro' ),
				'name'         => 'cdn_host',
				'type'         => 'text',
				'placeholder'  => 'ftp.example.com',
				'instructions' => __( 'برای FTP همان Host پنل هاست/FileZilla را بگذارید (نه لزوماً دامنه رسانه). برای کپی محلی لازم نیست.', 'asrekhodro' ),
			),
			array(
				'key'           => 'field_ak_cdn_port',
				'label'         => __( 'پورت', 'asrekhodro' ),
				'name'          => 'cdn_port',
				'type'          => 'number',
				'default_value' => (int) ( $defaults['port'] ?? 21 ),
				'min'           => 1,
			),
			array(
				'key'   => 'field_ak_cdn_user',
				'label' => __( 'نام کاربری', 'asrekhodro' ),
				'name'  => 'cdn_user',
				'type'  => 'text',
			),
			array(
				'key'          => 'field_ak_cdn_pass',
				'label'        => __( 'رمز عبور', 'asrekhodro' ),
				'name'         => 'cdn_pass',
				'type'         => 'password',
				'instructions' => __( 'رمز در دیتابیس ذخیره می‌شود. فقط مدیرانِ دارای دسترسی تنظیمات تم به این بخش دسترسی دارند.', 'asrekhodro' ),
			),
			array(
				'key'           => 'field_ak_cdn_remote_base_path',
				'label'         => __( 'مسیر پایه روی سرور', 'asrekhodro' ),
				'name'          => 'cdn_remote_base_path',
				'type'          => 'text',
				'default_value' => (string) ( $defaults['remote_base_path'] ?? '/' ),
				'placeholder'   => '/domains/.../public_html/AsreKhodro/Uploaded',
				'instructions'  => __( 'مسیر واقعی روی دیسک/FTP. مثال ParsPack: /domains/xxx.parspack.net/public_html/AsreKhodro/Uploaded — اگر این مسیر روی همان سرور وردپرس وجود داشته باشد، حتی با پروتکل FTP هم کپی محلی استفاده می‌شود.', 'asrekhodro' ),
			),
			array(
				'key'           => 'field_ak_cdn_max_upload_mb',
				'label'         => __( 'حداکثر حجم فایل (مگابایت)', 'asrekhodro' ),
				'name'          => 'cdn_max_upload_mb',
				'type'          => 'number',
				'default_value' => (int) ( $defaults['max_upload_mb'] ?? 50 ),
				'min'           => 1,
			),
			array(
				'key'           => 'field_ak_cdn_allowed_types',
				'label'         => __( 'انواع مجاز فایل (MIME)', 'asrekhodro' ),
				'name'          => 'cdn_allowed_types',
				'type'          => 'textarea',
				'rows'          => 6,
				'default_value' => implode( "\n", (array) ( $defaults['allowed_types'] ?? array() ) ),
				'instructions'  => __( 'هر نوع در یک خط. مثال: image/jpeg یا image/* برای همه تصاویر.', 'asrekhodro' ),
			),
			array(
				'key'     => 'field_ak_cdn_test',
				'label'   => __( 'تست اتصال', 'asrekhodro' ),
				'name'    => '',
				'type'    => 'message',
				'message' => self::test_button_html(),
				'esc_html' => 0,
			),
		);
	}

	private static function test_button_html(): string {
		return '<button type="button" class="button" id="ak-cdn-test-btn">'
			. esc_html__( 'بررسی اتصال به سرور', 'asrekhodro' )
			. '</button> <span id="ak-cdn-test-result" style="margin-inline-start:8px;"></span>'
			. '<div id="ak-cdn-test-details" class="ak-cdn-test-details" style="display:none;"></div>'
			. '<p class="description">' . esc_html__( 'مقادیر همین فرم برای تست استفاده می‌شود (نیازی به ذخیره نیست). اگر رمز را خالی بگذارید، رمز ذخیره‌شده قبلی استفاده می‌شود.', 'asrekhodro' ) . '</p>';
	}
}
