<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Comment form markup hooks for themed styling (visual only).
 */
final class Comments {

	public const PER_PAGE = 7;

	private const AJAX_ACTION = 'ak_load_comments';

	private static bool $themed_form = false;

	public static function init(): void {
		add_filter( 'timber/twig/functions', array( self::class, 'register_twig_functions' ) );
		add_action( 'comment_form_before', array( self::class, 'begin_themed_form' ) );
		add_action( 'comment_form_after', array( self::class, 'end_themed_form' ) );
		add_filter( 'comment_form_defaults', array( self::class, 'filter_form_defaults' ) );
		add_filter( 'comment_form_fields', array( self::class, 'filter_form_fields' ), 20 );
		add_filter( 'comment_form_field_comment', array( self::class, 'filter_comment_field' ) );
		add_filter( 'comment_form_field_cookies', array( self::class, 'filter_cookies_field' ) );
		add_filter( 'comment_form_logged_in', array( self::class, 'filter_logged_in_message' ) );
		add_filter( 'comment_form_field_url', array( self::class, 'filter_url_field' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( self::class, 'ajax_load_more' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( self::class, 'ajax_load_more' ) );
	}

	/**
	 * @param array<string, mixed> $functions
	 * @return array<string, mixed>
	 */
	public static function register_twig_functions( array $functions ): array {
		$functions['ak_comment_form_args'] = array(
			'callable' => array( self::class, 'form_args' ),
		);
		$functions['ak_comment_author_initial'] = array(
			'callable' => array( self::class, 'author_initial' ),
		);
		$functions['ak_post_comments'] = array(
			'callable' => array( self::class, 'twig_post_comments' ),
		);
		$functions['ak_comments_per_page'] = array(
			'callable' => array( self::class, 'per_page' ),
		);
		$functions['ak_post_comments_count'] = array(
			'callable' => array( self::class, 'twig_post_comments_count' ),
		);
		$functions['ak_post_visible_comments_count'] = array(
			'callable' => array( self::class, 'twig_visible_comments_count' ),
		);
		$functions['ak_comment_submission_notice'] = array(
			'callable' => array( self::class, 'twig_submission_notice' ),
		);

		return $functions;
	}

	public static function per_page(): int {
		return self::PER_PAGE;
	}

	/**
	 * @param \Timber\Post|\WP_Post|int|mixed $post
	 * @return list<\Timber\Comment>
	 */
	public static function twig_post_comments( $post, int $offset = 0 ): array {
		$post_id = self::resolve_post_id( $post );
		if ( $post_id <= 0 ) {
			return array();
		}

		return self::get_timber_comments( $post_id, max( 0, $offset ), self::PER_PAGE );
	}

	/**
	 * @param \Timber\Post|\WP_Post|int|mixed $post
	 */
	public static function twig_post_comments_count( $post ): int {
		$post_id = self::resolve_post_id( $post );

		return $post_id > 0 ? self::approved_comment_count( $post_id ) : 0;
	}

	/**
	 * Approved comments plus the current visitor's pending comment(s).
	 *
	 * @param \Timber\Post|\WP_Post|int|mixed $post
	 */
	public static function twig_visible_comments_count( $post ): int {
		$post_id = self::resolve_post_id( $post );
		if ( $post_id <= 0 ) {
			return 0;
		}

		$args = self::comment_query_args( $post_id, 0, 0 );
		$args['count'] = true;
		unset( $args['number'], $args['offset'] );

		return (int) get_comments( $args );
	}

	/**
	 * Notice shown after redirect from wp-comments-post.php (moderation preview URL).
	 *
	 * @param \Timber\Post|\WP_Post|int|mixed $post
	 * @return array{type: string, message: string, comment_id: int}|null
	 */
	public static function twig_submission_notice( $post ): ?array {
		return self::get_submission_notice( $post );
	}

	public static function ajax_load_more(): void {
		check_ajax_referer( 'ak_comments', 'nonce' );

		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		$offset  = isset( $_GET['offset'] ) ? absint( $_GET['offset'] ) : 0;

		if ( $post_id <= 0 || ! self::post_allows_public_comments( $post_id ) ) {
			wp_send_json_error( array( 'message' => 'invalid_post' ), 400 );
		}

		$comments   = self::get_timber_comments( $post_id, $offset, self::PER_PAGE );
		$new_offset = $offset + count( $comments );
		$total      = self::approved_comment_count( $post_id );

		wp_send_json_success(
			array(
				'html'    => self::render_items_html( $comments ),
				'offset'  => $new_offset,
				'hasMore' => $new_offset < $total,
			)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function script_config(): array {
		return array(
			'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
			'nonce'                => wp_create_nonce( 'ak_comments' ),
			'action'               => self::AJAX_ACTION,
			'perPage'              => self::PER_PAGE,
			'loadingText'          => 'در حال بارگذاری نظرات…',
			'errorText'            => 'بارگذاری نظرات ناموفق بود.',
			'successMessage'       => 'نظر شما با موفقیت ثبت شد.',
			'moderationMessage'    => self::moderation_message(),
			'commentsPostPath'     => wp_parse_url( site_url( 'wp-comments-post.php' ), PHP_URL_PATH ) ?: '/wp-comments-post.php',
		);
	}

	/**
	 * @param list<\Timber\Comment> $comments
	 */
	public static function render_items_html( array $comments ): string {
		if ( $comments === array() ) {
			return '';
		}

		$html     = '';
		$template = Appearance::resolve_template( 'partials/comment-item.twig' );

		foreach ( $comments as $comment ) {
			$html .= \Timber\Timber::compile(
				$template,
				array(
					'comment' => $comment,
				)
			);
		}

		return $html;
	}

	public static function needs_assets(): bool {
		if ( is_singular( 'post' ) ) {
			return true;
		}

		return is_singular( 'carsinfo' ) || CinfoBlocks::page_has_cinfo_blocks();
	}

	public static function begin_themed_form(): void {
		self::$themed_form = true;
	}

	public static function end_themed_form(): void {
		self::$themed_form = false;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function form_args(): array {
		self::$themed_form = true;

		$login_url = wp_login_url( get_permalink() ?: home_url( '/' ) );

		return array(
			'title_reply'          => '',
			'title_reply_to'       => '',
			'title_reply_before'   => '',
			'title_reply_after'    => '',
			'cancel_reply_link'    => 'لغو پاسخ',
			'cancel_reply_before'  => '',
			'cancel_reply_after'   => '',
			'label_submit'         => 'ارسال نظر',
			'class_form'           => 'ak-comments-form',
			'class_submit'         => 'ak-comments-form__submit-btn',
			'submit_button'        => '<button type="submit" name="%1$s" id="%2$s" class="%3$s"><span class="ak-comments-form__submit-icon" aria-hidden="true"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg></span><span>%4$s</span></button>',
			'submit_field'         => '<div class="ak-comments-form__submit-wrap form-submit">%1$s %2$s</div>',
			'comment_notes_before' => '',
			'comment_notes_after'  => '',
			'must_log_in'          => sprintf(
				'<p class="ak-comments-form__must-log-in must-log-in">برای ارسال نظر باید <a href="%s">وارد حساب کاربری</a> شوید.</p>',
				esc_url( $login_url )
			),
		);
	}

	/**
	 * @param array<string, mixed> $defaults
	 * @return array<string, mixed>
	 */
	public static function filter_form_defaults( array $defaults ): array {
		if ( ! self::$themed_form ) {
			return $defaults;
		}

		return array_merge( $defaults, self::form_args() );
	}

	/**
	 * Runs after comment_form_before — replaces default author/email/url markup.
	 *
	 * @param array<string, string> $fields
	 * @return array<string, string>
	 */
	public static function filter_form_fields( array $fields ): array {
		if ( ! self::$themed_form ) {
			return $fields;
		}

		if ( is_user_logged_in() ) {
			return $fields;
		}

		$comment_markup = isset( $fields['comment'] )
			? self::comment_field_markup( $fields['comment'] )
			: '';

		$out = array();

		foreach ( $fields as $name => $field ) {
			if ( 'comment' === $name || in_array( $name, array( 'author', 'email', 'url', 'ak_identity' ), true ) ) {
				continue;
			}

			$out[ $name ] = $field;
		}

		$out = array(
			'ak_composer' => self::composer_box( $comment_markup, self::identity_fields_inner() ),
		) + $out;

		return $out;
	}

	public static function filter_comment_field( string $field ): string {
		if ( ! self::$themed_form ) {
			return $field;
		}

		if ( ! is_user_logged_in() ) {
			return $field;
		}

		return '<div class="comment-form-comment ak-comments-form__field ak-comments-form__field--full">' . self::composer_box( self::comment_field_markup( $field ) ) . '</div>';
	}

	public static function filter_url_field( string $field ): string {
		if ( ! self::$themed_form || $field === '' ) {
			return $field;
		}

		return self::composer_box( '', self::identity_fields_inner( true ) );
	}

	public static function filter_cookies_field( string $field ): string {
		if ( ! self::$themed_form || $field === '' ) {
			return $field;
		}

		$field = preg_replace(
			'/<label for="wp-comment-cookies-consent">.*?<\/label>/',
			'<label for="wp-comment-cookies-consent" class="ak-comments-form__cookies-label">نام، ایمیل و وب‌سایت من را در مرورگر ذخیره کن تا دفعه بعد نیاز به وارد کردن نداشته باشم.</label>',
			$field,
			1
		);

		return str_replace(
			'class="comment-form-cookies-consent"',
			'class="comment-form-cookies-consent ak-comments-form__cookies"',
			$field
		);
	}

	public static function filter_logged_in_message( string $message ): string {
		if ( ! self::$themed_form || $message === '' ) {
			return $message;
		}

		$user = wp_get_current_user();
		if ( ! $user instanceof \WP_User || ! $user->exists() ) {
			return '<p class="ak-comments-form__logged-in">' . wp_kses_post( $message ) . '</p>';
		}

		$profile = get_edit_user_link( $user->ID );
		$logout  = wp_logout_url( get_permalink() ?: home_url( '/' ) );

		return sprintf(
			'<p class="ak-comments-form__logged-in">وارد شده‌اید به نام <strong>%1$s</strong>. <a href="%2$s">ویرایش پروفایل</a> · <a href="%3$s">خروج</a></p>',
			esc_html( $user->display_name ),
			esc_url( $profile ?: '' ),
			esc_url( $logout )
		);
	}

	public static function author_initial( string $name ): string {
		$name = trim( $name );
		if ( $name === '' ) {
			return '؟';
		}

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $name, 0, 1 );
		}

		return substr( $name, 0, 1 );
	}

	/**
	 * @param \Timber\Post|\WP_Post|int|mixed $post
	 */
	private static function resolve_post_id( $post ): int {
		if ( is_numeric( $post ) ) {
			return absint( $post );
		}

		if ( $post instanceof \Timber\Post ) {
			return (int) $post->ID;
		}

		if ( $post instanceof \WP_Post ) {
			return (int) $post->ID;
		}

		if ( is_object( $post ) && isset( $post->ID ) ) {
			return absint( $post->ID );
		}

		return 0;
	}

	private static function post_allows_public_comments( int $post_id ): bool {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || $post->post_status !== 'publish' ) {
			return false;
		}

		return in_array( $post->post_type, array( 'post', 'carsinfo' ), true );
	}

	private static function moderation_message(): string {
		return 'دیدگاه شما ثبت شد و پس از بررسی توسط تحریریه منتشر می‌شود.';
	}

	/**
	 * @param \Timber\Post|\WP_Post|int|mixed $post
	 * @return array{type: string, message: string, comment_id: int}|null
	 */
	private static function get_submission_notice( $post ): ?array {
		if ( empty( $_GET['unapproved'] ) || empty( $_GET['moderation-hash'] ) ) {
			return null;
		}

		$post_id = self::resolve_post_id( $post );
		if ( $post_id <= 0 ) {
			return null;
		}

		$comment_id = absint( wp_unslash( (string) $_GET['unapproved'] ) );
		if ( $comment_id <= 0 ) {
			return null;
		}

		$comment = get_comment( $comment_id );
		if ( ! $comment instanceof \WP_Comment || (int) $comment->comment_post_ID !== $post_id ) {
			return null;
		}

		$hash = sanitize_text_field( wp_unslash( (string) $_GET['moderation-hash'] ) );
		if ( ! hash_equals( wp_hash( $comment->comment_date_gmt ), $hash ) ) {
			return null;
		}

		$preview_expires = strtotime( $comment->comment_date_gmt . '+10 minutes' );
		if ( time() >= $preview_expires ) {
			return null;
		}

		return array(
			'type'       => 'moderation',
			'message'    => self::moderation_message(),
			'comment_id' => $comment_id,
		);
	}

	private static function approved_comment_count( int $post_id ): int {
		return (int) get_comments(
			array(
				'post_id' => $post_id,
				'status'  => 'approve',
				'type'    => 'comment',
				'parent'  => 0,
				'count'   => true,
			)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function comment_query_args( int $post_id, int $offset, int $number ): array {
		global $user_ID;

		$args = array(
			'post_id' => $post_id,
			'status'  => 'approve',
			'type'    => 'comment',
			'parent'  => 0,
			'number'  => $number,
			'offset'  => $offset,
			'orderby' => 'comment_date_gmt',
			'order'   => strtoupper( (string) get_option( 'comment_order', 'asc' ) ) === 'DESC' ? 'DESC' : 'ASC',
		);

		$commenter = wp_get_current_commenter();
		if ( $user_ID ) {
			$args['include_unapproved'] = array( $user_ID );
		} elseif ( ! empty( $commenter['comment_author_email'] ) ) {
			$args['include_unapproved'] = array( $commenter['comment_author_email'] );
		} elseif ( function_exists( 'wp_get_unapproved_comment_author_email' ) ) {
			$email = wp_get_unapproved_comment_author_email();
			if ( $email ) {
				$args['include_unapproved'] = array( $email );
			}
		}

		return $args;
	}

	/**
	 * @return list<\Timber\Comment>
	 */
	private static function get_timber_comments( int $post_id, int $offset, int $number ): array {
		$wp_comments = get_comments( self::comment_query_args( $post_id, $offset, $number ) );
		$comments    = array();

		foreach ( $wp_comments as $wp_comment ) {
			if ( ! $wp_comment instanceof \WP_Comment ) {
				continue;
			}

			$comment = \Timber\Timber::get_comment( $wp_comment );
			if ( $comment ) {
				$comments[] = $comment;
			}
		}

		return $comments;
	}

	private static function comment_field_markup( string $field ): string {
		$field = preg_replace( '/<label for="comment">.*?<\/label>/', '', $field, 1 ) ?? $field;
		$field = preg_replace( '/<\/?p[^>]*>/', '', $field ) ?? $field;
		$field = str_replace(
			'id="comment"',
			'id="comment" class="ak-comments-form__control ak-comments-form__control--textarea ak-comments-form__textarea"',
			trim( $field )
		);

		return self::compact_field(
			'comment',
			'متن نظر',
			$field,
			false,
			'نظر خود را بنویسید. لطفاً محترمانه و مرتبط با مطلب باشد.'
		);
	}

	private static function composer_box( string $comment_section, string $identity_section = '' ): string {
		$html  = '<div class="ak-comments-form__composer comment-form-composer">';
		$html .= '<div class="ak-comments-form__input-block ak-comments-form__input-block--composer">';

		if ( $comment_section !== '' ) {
			$html .= '<div class="ak-comments-form__composer-section ak-comments-form__composer-section--comment">';
			$html .= $comment_section;
			$html .= '</div>';
		}

		if ( $identity_section !== '' ) {
			if ( $comment_section !== '' ) {
				$html .= '<div class="ak-comments-form__composer-divider" aria-hidden="true"></div>';
			}

			$html .= '<div class="ak-comments-form__composer-section ak-comments-form__composer-section--identity">';
			$html .= $identity_section;
			$html .= '</div>';
		}

		$html .= '</div></div>';

		return $html;
	}

	private static function identity_fields_inner( bool $url_only = false ): string {
		$commenter = wp_get_current_commenter();
		$required  = (bool) get_option( 'require_name_email' );
		$aria_req  = $required ? ' aria-required="true" required' : '';
		$author    = esc_attr( $commenter['comment_author'] ?? '' );
		$email     = esc_attr( $commenter['comment_author_email'] ?? '' );
		$url       = esc_attr( $commenter['comment_author_url'] ?? '' );

		$url_field = self::compact_field(
			'url',
			'وب‌سایت',
			sprintf(
				'<input id="url" name="url" type="url" class="ak-comments-form__control" value="%1$s" size="30" maxlength="200" autocomplete="url" dir="ltr" placeholder="https://example.com" />',
				$url
			),
			true
		);

		if ( $url_only ) {
			return sprintf(
				'<div class="ak-comments-form__identity comment-form-identity"><div class="ak-comments-form__identity-grid ak-comments-form__identity-grid--url-only"><div class="comment-form-url ak-comments-form__identity-field ak-comments-form__identity-field--url">%s</div></div></div>',
				$url_field
			);
		}

		$author_field = self::compact_field(
			'author',
			'نام و نام خانوادگی',
			sprintf(
				'<input id="author" name="author" type="text" class="ak-comments-form__control" value="%1$s" size="30" maxlength="245" autocomplete="name"%2$s placeholder="نام خود را وارد کنید" />',
				$author,
				$aria_req
			)
		);

		$email_field = self::compact_field(
			'email',
			'پست الکترونیکی (ایمیل)',
			sprintf(
				'<input id="email" name="email" type="email" class="ak-comments-form__control" value="%1$s" size="30" maxlength="100" autocomplete="email" dir="ltr"%2$s placeholder="email@example.com" />',
				$email,
				$aria_req
			),
			true
		);

		return sprintf(
			'<div class="ak-comments-form__identity comment-form-identity"><div class="ak-comments-form__identity-grid"><div class="comment-form-author ak-comments-form__identity-field ak-comments-form__identity-field--author">%1$s</div><div class="comment-form-email ak-comments-form__identity-field ak-comments-form__identity-field--email">%2$s</div><div class="comment-form-url ak-comments-form__identity-field ak-comments-form__identity-field--url">%3$s</div></div></div>',
			$author_field,
			$email_field,
			$url_field
		);
	}

	private static function compact_field(
		string $id,
		string $label,
		string $control,
		bool $ltr = false,
		string $hint = ''
	): string {
		$modifier = $ltr ? ' ak-comments-form__compact-field--ltr' : '';

		$html  = sprintf(
			'<div class="ak-comments-form__compact-field%s">',
			$modifier
		);
		$html .= sprintf(
			'<label class="ak-comments-form__label ak-comments-form__label--field" for="%s">%s</label>',
			esc_attr( $id ),
			esc_html( $label )
		);
		$html .= $control;

		if ( $hint !== '' ) {
			$html .= sprintf(
				'<span class="ak-comments-form__hint ak-comments-form__hint--field">%s</span>',
				esc_html( $hint )
			);
		}

		$html .= '</div>';

		return $html;
	}
}
