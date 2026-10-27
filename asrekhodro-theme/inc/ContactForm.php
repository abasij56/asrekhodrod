<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ContactForm {

	private const FORM_TITLE   = 'Asre Khodro — Contact';
	private const FORM_ID_KEY  = 'ak_cf7_contact_form_id';
	private const FORM_VERSION = '5';

	public static function init(): void {
		add_action( 'init', array( self::class, 'ensure_form' ), 25 );
		add_filter( 'wpcf7_autop_or_not', array( self::class, 'disable_autop' ), 10, 2 );
		add_filter( 'wpcf7_load_js', array( self::class, 'allow_assets' ) );
		add_filter( 'wpcf7_load_css', array( self::class, 'allow_assets' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'dequeue_unused_assets' ), 20 );
	}

	/**
	 * Keep CF7 CSS/JS off pages that do not render a form (homepage included).
	 *
	 * @param bool $load
	 */
	public static function allow_assets( $load ): bool {
		if ( ! $load ) {
			return false;
		}

		if ( is_admin() ) {
			return true;
		}

		return self::page_needs_form();
	}

	public static function dequeue_unused_assets(): void {
		if ( self::page_needs_form() ) {
			return;
		}

		wp_dequeue_style( 'contact-form-7' );
		wp_dequeue_style( 'contact-form-7-rtl' );
		wp_dequeue_script( 'contact-form-7' );
		wp_dequeue_script( 'swv' );
	}

	private static function page_needs_form(): bool {
		if ( is_page_template( 'page-contact.php' ) ) {
			return true;
		}

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof \WP_Post ) {
				$content = (string) $post->post_content;
				if ( has_shortcode( $content, 'contact-form-7' ) ) {
					return true;
				}

				if ( function_exists( 'has_block' ) && has_block( 'contact-form-7/contact-form-selector', $post ) ) {
					return true;
				}
			}
		}

		return self::layout_has_contact_block();
	}

	private static function layout_has_contact_block(): bool {
		$page_key = '';

		if ( is_front_page() ) {
			$page_key = 'front_page';
		} elseif ( is_page_template( 'page-contact.php' ) ) {
			$page_key = 'page_contact';
		}

		if ( $page_key === '' ) {
			return false;
		}

		foreach ( LayoutResolver::flat_placements( $page_key ) as $placement ) {
			if ( ( $placement['block'] ?? '' ) === 'ak-contact-page' ) {
				return true;
			}
		}

		return false;
	}

	public static function ensure_form(): void {
		if ( ! class_exists( \WPCF7_ContactForm::class ) ) {
			return;
		}

		$form_id = (int) get_option( self::FORM_ID_KEY, 0 );
		$form    = $form_id ? wpcf7_contact_form( $form_id ) : null;

		if ( ! $form ) {
			foreach ( \WPCF7_ContactForm::find() as $candidate ) {
				if ( self::FORM_TITLE === $candidate->title() ) {
					$form = $candidate;
					break;
				}
			}
		}

		$stored_version = (string) get_option( 'ak_cf7_contact_form_version', '' );

		if ( $form && self::FORM_VERSION === $stored_version ) {
			update_option( self::FORM_ID_KEY, (int) $form->id() );
			return;
		}

		if ( ! $form ) {
			$form = \WPCF7_ContactForm::get_template(
				array(
					'title'  => self::FORM_TITLE,
					'locale' => 'fa_IR',
				)
			);
		}

		$form->set_properties( self::form_properties() );
		$form->save();

		update_option( self::FORM_ID_KEY, (int) $form->id() );
		update_option( 'ak_cf7_contact_form_version', self::FORM_VERSION );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function form_properties(): array {
		$default_email = function_exists( 'get_field' )
			? (string) ( get_field( 'contact_email', 'option' ) ?: 'info@asrekhodro.com' )
			: 'info@asrekhodro.com';

		$sender = '[_site_title] <wordpress@example.com>';
		if ( class_exists( \WPCF7_ContactFormTemplate::class ) ) {
			$sender = sprintf(
				'%s <%s>',
				'[_site_title]',
				\WPCF7_ContactFormTemplate::from_email()
			);
		}

		return array(
			'form'     => trim(
				'
<div class="ak-contact-form__grid">
<div class="ak-contact-form__field">[text* full_name class:ak-contact-form__control placeholder "نام *"]</div>
<div class="ak-contact-form__field">[email* email class:ak-contact-form__control placeholder "ایمیل *"]</div>
<div class="ak-contact-form__field">[text* subject class:ak-contact-form__control placeholder "موضوع *"]</div>
<div class="ak-contact-form__field">[select* department class:ak-contact-form__control "دپارتمان روابط عمومی|' . $default_email . '"]</div>
<div class="ak-contact-form__field ak-contact-form__field--full">[textarea* message class:ak-contact-form__control rows:6 placeholder "پیام شما ..."]</div>
</div>
' . RecaptchaBranding::notice_html( 'ak-contact-form__recaptcha-note' ) . '
<p class="ak-contact-form__submit">[submit class:ak-contact-form__button "ارسال پیام"]</p>'
			),
			'mail'     => array(
				'active'             => true,
				'subject'            => 'عصر خودرو — [subject]',
				'sender'             => $sender,
				'recipient'          => '[department]',
				'body'               =>
					"نام: [full_name]\n"
					. "ایمیل: [email]\n"
					. "موضوع: [subject]\n"
					. "دپارتمان: [department]\n\n"
					. "پیام:\n[message]\n\n"
					. "--\n"
					. 'ارسال‌شده از [_site_title] ([_site_url])',
				'additional_headers' => 'Reply-To: [email]',
				'attachments'        => '',
				'use_html'           => 0,
				'exclude_blank'      => 0,
			),
			'mail_2'   => array(
				'active' => false,
			),
			'messages' => array(
				'mail_sent_ok'       => 'پیام شما با موفقیت ارسال شد. به‌زودی پاسخ خواهیم داد.',
				'mail_sent_ng'       => 'ارسال پیام با خطا مواجه شد. لطفاً دوباره تلاش کنید.',
				'validation_error'   => 'لطفاً فیلدهای الزامی را تکمیل کنید.',
				'spam'               => 'ارسال پیام با خطا مواجه شد. لطفاً دوباره تلاش کنید.',
				'accept_terms'       => 'لطفاً شرایط را بپذیرید.',
				'invalid_required'   => 'تکمیل این فیلد الزامی است.',
				'invalid_too_long'   => 'متن وارد شده بیش از حد مجاز است.',
				'invalid_too_short'  => 'متن وارد شده کوتاه‌تر از حد مجاز است.',
				'invalid_email'      => 'آدرس ایمیل معتبر نیست.',
				'invalid_url'        => 'آدرس URL معتبر نیست.',
				'invalid_tel'        => 'شماره تلفن معتبر نیست.',
			),
		);
	}

	public static function get_form_id(): int {
		return (int) get_option( self::FORM_ID_KEY, 0 );
	}

	public static function render_shortcode(): string {
		$form_id = self::get_form_id();

		if ( ! $form_id || ! function_exists( 'wpcf7_contact_form' ) ) {
			return '';
		}

		return do_shortcode( '[contact-form-7 id="' . $form_id . '" html_class="ak-contact-form"]' );
	}

	/**
	 * @param bool $autop
	 */
	public static function disable_autop( $autop, $contact_form ): bool {
		if ( $contact_form instanceof \WPCF7_ContactForm && (int) $contact_form->id() === self::get_form_id() ) {
			return false;
		}

		return $autop;
	}
}
