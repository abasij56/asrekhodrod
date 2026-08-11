<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hide Google reCAPTCHA v3 badge and show required branding near forms.
 *
 * @see https://developers.google.com/recaptcha/docs/faq#id-like-to-hide-the-recaptcha-badge.-what-is-allowed
 */
final class RecaptchaBranding {

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_styles' ), 40 );
		add_action( 'login_enqueue_scripts', array( self::class, 'enqueue_styles' ), 40 );
		add_action( 'login_form', array( self::class, 'print_login_notice' ) );
		add_action( 'register_form', array( self::class, 'print_login_notice' ) );
		add_action( 'lostpassword_form', array( self::class, 'print_login_notice' ) );
	}

	public static function enqueue_styles(): void {
		$handle = wp_style_is( 'asrekhodro-style', 'enqueued' )
			? 'asrekhodro-style'
			: ( wp_style_is( 'asrekhodro-login', 'enqueued' ) ? 'asrekhodro-login' : '' );

		$css = self::css();

		if ( $handle !== '' ) {
			wp_add_inline_style( $handle, $css );
			return;
		}

		wp_register_style( 'asrekhodro-recaptcha-branding', false, array(), ASREKHODRO_THEME_VERSION );
		wp_enqueue_style( 'asrekhodro-recaptcha-branding' );
		wp_add_inline_style( 'asrekhodro-recaptcha-branding', $css );
	}

	public static function print_login_notice(): void {
		echo self::notice_html( 'ak-login-page__recaptcha-note' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in notice_html()
	}

	/**
	 * Google-required branding copy (Persian), for use next to protected forms.
	 */
	public static function notice_html( string $extra_class = '' ): string {
		$classes = trim( 'ak-recaptcha-note ' . $extra_class );

		return sprintf(
			'<p class="%1$s">این فرم توسط reCAPTCHA محافظت می‌شود. <a href="%2$s" target="_blank" rel="noopener noreferrer">حریم خصوصی</a> و <a href="%3$s" target="_blank" rel="noopener noreferrer">شرایط استفاده</a> گوگل اعمال می‌شود.</p>',
			esc_attr( $classes ),
			esc_url( 'https://policies.google.com/privacy' ),
			esc_url( 'https://policies.google.com/terms' )
		);
	}

	private static function css(): string {
		return <<<'CSS'
.grecaptcha-badge {
  visibility: hidden !important;
  pointer-events: none !important;
}

.ak-recaptcha-note {
  margin: 0;
  padding: 0;
  border: 0;
  background: transparent;
  font-size: 0.7rem;
  line-height: 1.5;
  color: var(--ink-muted, #6b6b6b);
  text-align: right;
}

.ak-recaptcha-note a {
  color: inherit;
  text-decoration: underline;
  text-underline-offset: 2px;
  text-decoration-thickness: 1px;
}

.ak-recaptcha-note a:hover {
  color: var(--brand, #e10600);
}

.ak-comments-form > .ak-recaptcha-note,
.ak-comments-form > .ak-comments-form__recaptcha-note {
  grid-column: 1 / -1;
  margin-top: 0.15rem;
}

.ak-contact-form__recaptcha-note {
  margin: 0.15rem 0 0.65rem;
}

.ak-login-page__recaptcha-note {
  margin: 0 0 1rem;
  max-width: 100%;
}
CSS;
	}
}
