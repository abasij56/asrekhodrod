<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LoginPage {

	public static function init(): void {
		add_action( 'login_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
		add_filter( 'login_headerurl', array( self::class, 'header_url' ) );
		add_filter( 'login_headertext', array( self::class, 'header_text' ) );
		add_filter( 'login_body_class', array( self::class, 'body_class' ) );
		add_action( 'login_header', array( self::class, 'render_shell_open' ), 5 );
		add_action( 'login_footer', array( self::class, 'render_shell_close' ), 5 );
		add_filter( 'login_message', array( self::class, 'login_heading' ) );
		add_filter( 'gettext', array( self::class, 'translate_login_strings' ), 10, 3 );
		add_filter( 'login_display_language_dropdown', '__return_false' );
	}

	public static function enqueue_assets(): void {
		wp_enqueue_style(
			'asrekhodro-fonts',
			Appearance::asset_url( 'css/fonts.css' ),
			array(),
			ASREKHODRO_THEME_VERSION
		);

		wp_enqueue_style(
			'asrekhodro-login',
			Appearance::asset_url( 'css/login.css' ),
			array( 'login', 'asrekhodro-fonts' ),
			ASREKHODRO_THEME_VERSION
		);

		$logo = self::get_site_logo();
		if ( $logo ) {
			wp_add_inline_style(
				'asrekhodro-login',
				sprintf(
					'.login .wp-login-logo a{background-image:url(%s)!important;}',
					esc_url( $logo )
				)
			);
		}
	}

	public static function header_url( string $url ): string {
		return home_url( '/' );
	}

	public static function header_text( string $text ): string {
		return get_bloginfo( 'name', 'display' );
	}

	/**
	 * @param string[] $classes
	 * @return string[]
	 */
	public static function body_class( array $classes ): array {
		$classes[] = 'ak-login';

		if ( self::get_site_logo() ) {
			$classes[] = 'ak-login--has-logo';
		}

		return $classes;
	}

	public static function render_shell_open(): void {
		$hero_url  = self::get_hero_image_url();
		$options   = function_exists( 'get_fields' ) ? ( get_fields( 'option' ) ?: array() ) : array();
		$title     = $options['login_hero_title'] ?? 'مرجع تخصصی اخبار خودرو';
		$subtitle  = $options['login_hero_subtitle'] ?? 'آخرین اخبار، بررسی و تحلیل صنعت خودرو در ایران';
		?>
		<div class="ak-login-page">
			<aside
				class="ak-login-page__visual"
				aria-hidden="true"
				style="background-image: url('<?php echo esc_url( $hero_url ); ?>')"
			>
				<div class="ak-login-page__visual-overlay">
					<p class="ak-login-page__visual-title"><?php echo esc_html( (string) $title ); ?></p>
					<p class="ak-login-page__visual-subtitle"><?php echo esc_html( (string) $subtitle ); ?></p>
				</div>
			</aside>
			<div class="ak-login-page__panel">
		<?php
	}

	public static function render_shell_close(): void {
		?>
			</div>
		</div>
		<?php
	}

	public static function login_heading( string $message ): string {
		if ( $message !== '' ) {
			return $message;
		}

		return '<p class="ak-login-page__heading">ورود به پنل مدیریت</p>';
	}

	public static function translate_login_strings( string $translated, string $text, string $domain ): string {
		if ( 'default' !== $domain || ! self::is_login_page() ) {
			return $translated;
		}

		$map = array(
			'Log In'                         => 'ورود',
			'Username or Email Address'      => 'نام کاربری یا ایمیل',
			'Password'                       => 'رمز عبور',
			'Remember Me'                    => 'مرا به خاطر بسپار',
			'Lost your password?'            => 'رمز عبور را فراموش کرده‌اید؟',
			'Register'                       => 'ثبت‌نام',
			'← Go to %s'                     => '← بازگشت به %s',
			'Powered by WordPress'           => get_bloginfo( 'name', 'display' ),
		);

		return $map[ $text ] ?? $translated;
	}

	private static function is_login_page(): bool {
		global $pagenow;

		return isset( $pagenow ) && $pagenow === 'wp-login.php';
	}

	private static function get_site_logo(): ?string {
		if ( ! function_exists( 'get_field' ) ) {
			return null;
		}

		$logo = get_field( 'site_logo', 'option' );
		if ( is_array( $logo ) && ! empty( $logo['url'] ) ) {
			return (string) $logo['url'];
		}

		return null;
	}

	private static function get_hero_image_url(): string {
		if ( function_exists( 'get_field' ) ) {
			$hero = get_field( 'login_hero_image', 'option' );
			if ( is_array( $hero ) && ! empty( $hero['url'] ) ) {
				return (string) $hero['url'];
			}
		}

		return Appearance::asset_url( 'images/login-hero.jpg' );
	}
}
