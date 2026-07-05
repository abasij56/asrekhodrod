<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ContactPage {

	public static function get_context(): array {
		$options = function_exists( 'get_fields' ) ? ( get_fields( 'option' ) ?: array() ) : array();

		$lat = isset( $options['contact_map_lat'] ) ? (float) $options['contact_map_lat'] : 35.7454587;
		$lng = isset( $options['contact_map_lng'] ) ? (float) $options['contact_map_lng'] : 51.4142579;

		return array(
			'contact_page_title' => $options['contact_page_title'] ?? 'با ما در تماس باشید',
			'contact_form_html'  => ContactForm::render_shortcode(),
			'contact'            => array(
				'address'         => $options['contact_address'] ?? 'سعادت آباد، آسمان، بلوار سرو غربی، بلوار شهید پاکنژاد، ساختمان سینا، پلاک 38، طبقه اول واحد 2',
				'postal_code'     => $options['contact_postal_code'] ?? '1998994853',
				'phone'           => $options['contact_phone'] ?? '021-26745910',
				'email'           => $options['contact_email'] ?? 'info@asrekhodro.com',
				'manager'         => $options['contact_manager'] ?? 'شهرام فرمانی',
				'manager_note'    => $options['contact_manager_note'] ?? 'صاحب امتیاز گروه رسانه‌ای روز نو',
				'intro'           => $options['contact_intro'] ?? self::default_intro( $options['contact_email'] ?? 'info@asrekhodro.com' ),
				'info_card_title' => $options['contact_info_card_title'] ?? 'اطلاعات تماس',
				'form_card_title' => $options['contact_form_card_title'] ?? 'ارسال پیام',
				'form_card_lead'  => trim( (string) ( $options['contact_form_card_lead'] ?? '' ) ),
				'map_lat'         => $lat,
				'map_lng'         => $lng,
				'map_embed_url'   => sprintf(
					'https://maps.google.com/maps?q=%s,%s&hl=fa&z=15&output=embed',
					rawurlencode( (string) $lat ),
					rawurlencode( (string) $lng )
				),
			),
		);
	}

	private static function default_intro( string $email ): string {
		return 'پست الکترونیکی ما به نشانی ' . $email . ' چندین بار در طول روز بازبینی می‌شود، بنابراین بهترین و سریع‌ترین راه تماس با ما از این طریق است. از طریق فرم زیر می‌توانید به‌طور مستقیم پیام‌ها، پیشنهادها و گزارش‌های خود را ارسال نمایید. پیام شما به پست الکترونیکی ما ارسال خواهد شد و از همین طریق پاسخگوی سؤالات شما نیز خواهیم بود.' . "\n\n"
			. 'موارد ستاره‌دار را حتماً تکمیل کنید. مواردی که ستاره ندارند را می‌توانید به دلخواه تکمیل کرده و یا خالی گذاشته و پیام را ارسال کنید.' . "\n"
			. 'در وارد کردن آدرس ایمیل‌تان دقت کنید؛ چون پاسخ به پیام شما از طریق این آدرس ایمیل که درج می‌کنید انجام می‌شود.';
	}
}
