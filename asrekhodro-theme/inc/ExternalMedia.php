<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add external image URLs to the media library without importing files.
 *
 * Ported from External Media without Import (GPLv3) with security hardening.
 */
final class ExternalMedia {

	private const META_FLAG = '_ak_external_media';
	private const AJAX_ACTION = 'ak_add_external_media';
	private const NONCE_ACTION = 'ak_external_media';
	private const DEFAULT_FALLBACK_WIDTH = 1200;
	private const DEFAULT_FALLBACK_HEIGHT = 800;

	public static function init(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_admin_assets' ) );
		add_action( 'admin_menu', array( self::class, 'register_submenu' ) );
		add_action( 'post-plupload-upload-ui', array( self::class, 'render_upload_ui' ) );
		add_action( 'post-html-upload-ui', array( self::class, 'render_upload_ui' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( self::class, 'handle_ajax' ) );
		add_action( 'admin_post_' . self::AJAX_ACTION, array( self::class, 'handle_admin_post' ) );
		add_filter( 'get_attached_file', array( self::class, 'filter_attached_file' ), 10, 2 );
	}

	public static function enqueue_admin_assets( string $hook_suffix ): void {
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		$allowed_hooks = array( 'upload.php', 'post.php', 'post-new.php' );
		$is_media_page = str_starts_with( $hook_suffix, 'media_page_' );

		if ( ! in_array( $hook_suffix, $allowed_hooks, true ) && ! $is_media_page ) {
			return;
		}

		wp_enqueue_style(
			'ak-external-media',
			Appearance::asset_url( 'admin/css/external-media.css' ),
			array(),
			ASREKHODRO_THEME_VERSION
		);

		wp_enqueue_script(
			'ak-external-media',
			Appearance::asset_url( 'admin/js/external-media.js' ),
			array( 'jquery' ),
			ASREKHODRO_THEME_VERSION,
			true
		);

		wp_localize_script(
			'ak-external-media',
			'akExternalMedia',
			array(
				'action' => self::AJAX_ACTION,
				'nonce'  => wp_create_nonce( self::NONCE_ACTION ),
			)
		);
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
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		$media_library_mode = get_user_option( 'media_library_mode', get_current_user_id() );
		?>
		<div id="emwi-in-upload-ui">
			<div class="row1"><?php esc_html_e( 'or', 'asrekhodro' ); ?></div>
			<div class="row2">
				<?php if ( 'grid' === $media_library_mode || $media_library_mode === false ) : ?>
					<button type="button" id="emwi-show" class="button button-large">
						<?php esc_html_e( 'Add External Media without Import', 'asrekhodro' ); ?>
					</button>
					<?php self::render_panel( true ); ?>
				<?php else : ?>
					<a class="button button-large" href="<?php echo esc_url( admin_url( 'upload.php?page=add-external-media' ) ); ?>">
						<?php esc_html_e( 'Add External Media without Import', 'asrekhodro' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	public static function render_submenu_page(): void {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'You do not have permission to upload files.', 'asrekhodro' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Add External Media without Import', 'asrekhodro' ); ?></h1>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<?php self::render_panel( false ); ?>
			</form>
		</div>
		<?php
	}

	private static function render_panel( bool $in_upload_ui ): void {
		$urls      = isset( $_GET['urls'] ) ? sanitize_textarea_field( wp_unslash( (string) $_GET['urls'] ) ) : '';
		$error     = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['error'] ) ) : '';
		$width     = isset( $_GET['width'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['width'] ) ) : '';
		$height    = isset( $_GET['height'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['height'] ) ) : '';
		$mime_type = isset( $_GET['mime-type'] ) ? sanitize_mime_type( wp_unslash( (string) $_GET['mime-type'] ) ) : '';
		$show_meta = ! $in_upload_ui && $error !== '';
		?>
		<div id="emwi-media-new-panel" <?php echo $in_upload_ui ? 'style="display:none"' : ''; ?>>
			<label id="emwi-urls-label" for="emwi-urls"><?php esc_html_e( 'Add medias from URLs', 'asrekhodro' ); ?></label>
			<textarea
				id="emwi-urls"
				name="urls"
				rows="<?php echo $in_upload_ui ? 3 : 10; ?>"
				required
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
				<input type="hidden" name="action" value="<?php echo esc_attr( self::AJAX_ACTION ); ?>">
				<span class="spinner"></span>
				<input type="button" id="emwi-clear" class="button" value="<?php esc_attr_e( 'Clear', 'asrekhodro' ); ?>">
				<input type="submit" id="emwi-add" class="button button-primary" value="<?php esc_attr_e( 'Add', 'asrekhodro' ); ?>">
				<?php if ( $in_upload_ui ) : ?>
					<input type="button" id="emwi-cancel" class="button" value="<?php esc_attr_e( 'Cancel', 'asrekhodro' ); ?>">
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	public static function handle_ajax(): void {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'asrekhodro' ) ), 403 );
		}

		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$info = self::process_submission();
		$attachments = array();

		foreach ( $info['attachment_ids'] as $attachment_id ) {
			$attachment = wp_prepare_attachment_for_js( $attachment_id );
			if ( $attachment ) {
				$attachments[] = $attachment;
			} elseif ( ! isset( $info['error'] ) ) {
				$info['error'] = __( 'An attachment was created but could not be loaded for display.', 'asrekhodro' );
			}
		}

		$info['attachments'] = $attachments;
		wp_send_json_success( $info );
	}

	public static function handle_admin_post(): void {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'You do not have permission to upload files.', 'asrekhodro' ) );
		}

		check_admin_referer( self::NONCE_ACTION );

		$info          = self::process_submission();
		$redirect_url  = 'upload.php?page=add-external-media';
		$failed_urls   = (string) ( $info['urls'] ?? '' );

		if ( $failed_urls !== '' || ! empty( $info['error'] ) ) {
			$redirect_url = add_query_arg(
				array(
					'urls'      => $failed_urls,
					'error'     => (string) ( $info['error'] ?? '' ),
					'width'     => (string) ( $info['width'] ?? '' ),
					'height'    => (string) ( $info['height'] ?? '' ),
					'mime-type' => (string) ( $info['mime-type'] ?? '' ),
				),
				$redirect_url
			);
		}

		wp_safe_redirect( admin_url( $redirect_url ) );
		exit;
	}

	/**
	 * Register a remote image URL as a media-library attachment (no file upload).
	 *
	 * @param array<string, mixed> $args {
	 *     @type int    $width            Optional width in pixels.
	 *     @type int    $height           Optional height in pixels.
	 *     @type string $mime             Optional MIME type.
	 *     @type int    $post_parent      Optional parent post ID.
	 *     @type bool   $allow_fallback   Use guessed dimensions when remote probe fails.
	 *     @type int    $request_timeout  HTTP timeout in seconds (default 8).
	 * }
	 */
	public static function register_url( string $url, array $args = array() ): int {
		$url = esc_url_raw( trim( $url ) );
		if ( $url === '' ) {
			return 0;
		}

		return self::create_external_attachment(
			$url,
			max( 0, (int) ( $args['width'] ?? 0 ) ),
			max( 0, (int) ( $args['height'] ?? 0 ) ),
			(string) ( $args['mime'] ?? '' ),
			(int) ( $args['post_parent'] ?? 0 ),
			(bool) ( $args['allow_fallback'] ?? false ),
			max( 1, (int) ( $args['request_timeout'] ?? 8 ) ),
			(bool) ( $args['skip_remote_probe'] ?? false )
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function process_submission(): array {
		$input = self::sanitize_input();

		if ( isset( $input['error'] ) ) {
			return $input;
		}

		$attachment_ids = array();
		$failed_urls    = array();

		foreach ( $input['urls'] as $url ) {
			if ( $url === '' ) {
				continue;
			}

			$attachment_id = self::create_external_attachment(
				$url,
				(int) $input['width'],
				(int) $input['height'],
				(string) $input['mime-type'],
				0,
				false,
				8
			);

			if ( $attachment_id > 0 ) {
				$attachment_ids[] = $attachment_id;
				continue;
			}

			$failed_urls[] = $url;
		}

		$input['attachment_ids'] = $attachment_ids;
		$input['urls']           = implode( "\n", $failed_urls );

		if ( $failed_urls !== array() ) {
			$input['error'] = __( 'Failed to get info of the image(s).', 'asrekhodro' );
		}

		return $input;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function sanitize_input(): array {
		$raw_urls = isset( $_POST['urls'] ) ? (string) wp_unslash( $_POST['urls'] ) : '';
		$urls     = array();

		foreach ( preg_split( '/\R/', $raw_urls ) ?: array() as $raw_url ) {
			$urls[] = esc_url_raw( trim( $raw_url ) );
		}

		$input = array(
			'urls'      => $urls,
			'width'     => isset( $_POST['width'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['width'] ) ) : '',
			'height'    => isset( $_POST['height'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['height'] ) ) : '',
			'mime-type' => isset( $_POST['mime-type'] ) ? sanitize_mime_type( wp_unslash( (string) $_POST['mime-type'] ) ) : '',
		);

		$width_int = (int) $input['width'];
		if ( $input['width'] !== '' && $width_int <= 0 ) {
			$input['error'] = __( 'Width and height must be non-negative integers.', 'asrekhodro' );
			return $input;
		}

		$height_int = (int) $input['height'];
		if ( $input['height'] !== '' && $height_int <= 0 ) {
			$input['error'] = __( 'Width and height must be non-negative integers.', 'asrekhodro' );
			return $input;
		}

		$input['width']  = $width_int;
		$input['height'] = $height_int;

		return $input;
	}

	private static function create_external_attachment(
		string $url,
		int $width,
		int $height,
		string $mime_type,
		int $post_parent = 0,
		bool $allow_fallback = false,
		int $request_timeout = 8,
		bool $skip_remote_probe = false
	): int {
		if ( ! self::is_allowed_media_url( $url ) ) {
			return 0;
		}

		$existing = self::find_attachment_by_url( $url );
		if ( $existing > 0 ) {
			if ( $post_parent > 0 ) {
				wp_update_post(
					array(
						'ID'          => $existing,
						'post_parent' => $post_parent,
					)
				);
			}

			return $existing;
		}

		$resolved = $skip_remote_probe
			? self::fallback_image_properties( $url )
			: self::resolve_image_properties( $url, $width, $height, $mime_type, $request_timeout );
		if ( $resolved === null && $allow_fallback ) {
			$resolved = self::fallback_image_properties( $url );
		}

		if ( $resolved === null ) {
			return 0;
		}

		$filename = sanitize_file_name( wp_basename( wp_parse_url( $url, PHP_URL_PATH ) ?: 'external-image' ) );
		if ( $filename === '' ) {
			$filename = 'external-image';
		}

		$attachment_data = array(
			'guid'           => $url,
			'post_mime_type' => $resolved['mime'],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', $filename ) ?: $filename,
			'post_status'    => 'inherit',
		);

		if ( $post_parent > 0 ) {
			$attachment_data['post_parent'] = $post_parent;
		}

		$attachment_id = wp_insert_attachment( $attachment_data, false, $post_parent > 0 ? $post_parent : 0, true );

		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return 0;
		}

		$metadata = array(
			'width'  => $resolved['width'],
			'height' => $resolved['height'],
			'file'   => $filename,
			'sizes'  => array(
				'full' => array(
					'file'      => $filename,
					'width'     => $resolved['width'],
					'height'    => $resolved['height'],
					'mime-type' => $resolved['mime'],
				),
			),
		);

		wp_update_attachment_metadata( (int) $attachment_id, $metadata );
		update_post_meta( (int) $attachment_id, self::META_FLAG, '1' );

		return (int) $attachment_id;
	}

	/**
	 * @return array{width:int,height:int,mime:string}|null
	 */
	private static function resolve_image_properties(
		string $url,
		int $width,
		int $height,
		string $mime_type,
		int $request_timeout = 8
	): ?array {
		if ( $width > 0 && $height > 0 && $mime_type !== '' ) {
			return array(
				'width'  => $width,
				'height' => $height,
				'mime'   => $mime_type,
			);
		}

		if ( $width <= 0 || $height <= 0 ) {
			$image_size = self::fetch_remote_image_size( $url, $request_timeout );
			if ( $image_size === null ) {
				return null;
			}

			return array(
				'width'  => $width > 0 ? $width : $image_size['width'],
				'height' => $height > 0 ? $height : $image_size['height'],
				'mime'   => $mime_type !== '' ? $mime_type : $image_size['mime'],
			);
		}

		$response = wp_safe_remote_head(
			$url,
			array(
				'timeout'     => $request_timeout,
				'redirection' => 3,
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$content_type = wp_remote_retrieve_header( $response, 'content-type' );
		if ( ! is_string( $content_type ) || $content_type === '' ) {
			return null;
		}

		$resolved_mime = sanitize_mime_type( strtok( $content_type, ';' ) ?: '' );
		if ( $resolved_mime === '' ) {
			return null;
		}

		return array(
			'width'  => $width,
			'height' => $height,
			'mime'   => $mime_type !== '' ? $mime_type : $resolved_mime,
		);
	}

	/**
	 * @return array{width:int,height:int,mime:string}|null
	 */
	private static function fetch_remote_image_size( string $url, int $request_timeout = 8 ): ?array {
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'     => $request_timeout,
				'redirection' => 3,
				'headers'     => array(
					'Range' => 'bytes=0-262143',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( $body === '' ) {
			return null;
		}

		$image_size = @getimagesizefromstring( $body );
		if ( ! is_array( $image_size ) || empty( $image_size[0] ) || empty( $image_size[1] ) ) {
			return null;
		}

		$mime = isset( $image_size['mime'] ) ? sanitize_mime_type( (string) $image_size['mime'] ) : '';
		if ( $mime === '' ) {
			return null;
		}

		return array(
			'width'  => (int) $image_size[0],
			'height' => (int) $image_size[1],
			'mime'   => $mime,
		);
	}

	/**
	 * @return array{width:int,height:int,mime:string}
	 */
	private static function fallback_image_properties( string $url ): array {
		return array(
			'width'  => self::DEFAULT_FALLBACK_WIDTH,
			'height' => self::DEFAULT_FALLBACK_HEIGHT,
			'mime'   => self::guess_mime_from_url( $url ),
		);
	}

	private static function guess_mime_from_url( string $url ): string {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		$ext  = is_string( $path ) ? strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) : '';

		return match ( $ext ) {
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
			'svg'  => 'image/svg+xml',
			default => 'image/jpeg',
		};
	}

	public static function is_allowed_media_url( string $url ): bool {
		$url = esc_url_raw( trim( $url ) );
		if ( $url === '' || ! wp_http_validate_url( $url ) ) {
			return false;
		}

		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return false;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) || $host === '' ) {
			return false;
		}

		$blocked_hosts = array( 'localhost', '127.0.0.1', '0.0.0.0', '[::1]' );
		if ( in_array( strtolower( $host ), $blocked_hosts, true ) ) {
			return false;
		}

		/**
		 * Filter whether a remote media URL may be registered as an attachment.
		 *
		 * @param bool   $allowed Default true when URL passes built-in checks.
		 * @param string $url     Requested media URL.
		 */
		return (bool) apply_filters( 'ak_external_media_is_allowed_url', true, $url );
	}

	private static function find_attachment_by_url( string $url ): int {
		global $wpdb;

		$attachment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid = %s LIMIT 1",
				$url
			)
		);

		return $attachment_id ? (int) $attachment_id : 0;
	}

	public static function filter_attached_file( string $file, int $attachment_id ): string {
		if ( $file !== '' ) {
			return $file;
		}

		if ( ! self::is_external_attachment( $attachment_id ) && ! self::is_legacy_external_attachment( $attachment_id ) ) {
			return $file;
		}

		$post = get_post( $attachment_id );
		if ( ! $post instanceof \WP_Post ) {
			return $file;
		}

		return is_string( $post->guid ) ? $post->guid : $file;
	}

	public static function is_external_attachment( int $attachment_id ): bool {
		return get_post_meta( $attachment_id, self::META_FLAG, true ) === '1';
	}

	/**
	 * Full remote URL for an external attachment.
	 */
	public static function get_attachment_url( int $attachment_id ): string {
		if ( $attachment_id <= 0 ) {
			return '';
		}

		if ( self::is_external_attachment( $attachment_id ) || self::is_legacy_external_attachment( $attachment_id ) ) {
			$url = wp_get_attachment_url( $attachment_id );
			return is_string( $url ) && $url !== '' ? esc_url( $url ) : '';
		}

		return '';
	}

	private static function is_legacy_external_attachment( int $attachment_id ): bool {
		$post = get_post( $attachment_id );
		if ( ! $post instanceof \WP_Post || $post->post_type !== 'attachment' ) {
			return false;
		}

		return is_string( $post->guid ) && (bool) preg_match( '#^https?://#i', $post->guid );
	}
}
