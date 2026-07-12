<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bundled SVG icons for car-info spec fields (assets/cinfo-icons/).
 */
final class CarSpecIcons {

	private const DIR = ASREKHODRO_THEME_DIR . '/assets/cinfo-icons';

	private const SPRITE_FILE = 'custom/car-spec-icons.svg';

	public const ADMIN_INITIAL_LIMIT = 10;

	public const ADMIN_PAGE_SIZE = 20;

	/** @var list<array{id: string, set: string, name: string, title: string, url: string, sprite: bool}>|null */
	private static ?array $catalog = null;

	/**
	 * @return list<array{id: string, set: string, name: string, title: string, url: string, sprite: bool}>
	 */
	public static function catalog(): array {
		if ( self::$catalog !== null ) {
			return self::$catalog;
		}

		$cache_key = self::catalog_cache_key();
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			self::$catalog = $cached;

			return self::$catalog;
		}

		self::$catalog = self::build_catalog();
		set_transient( $cache_key, self::$catalog, DAY_IN_SECONDS );

		return self::$catalog;
	}

	/**
	 * @return list<array{id: string, set: string, name: string, title: string, url: string, sprite: bool}>
	 */
	private static function build_catalog(): array {
		$catalog = array();

		foreach ( self::custom_icons() as $item ) {
			$catalog[] = $item;
		}

		foreach ( array( 'tabler', 'lucide', 'tabler-filled' ) as $set ) {
			$dir = self::DIR . '/' . $set;
			if ( ! is_dir( $dir ) ) {
				continue;
			}

			$files = glob( $dir . '/*.svg' ) ?: array();
			sort( $files, SORT_NATURAL | SORT_FLAG_CASE );

			foreach ( $files as $path ) {
				if ( ! is_readable( $path ) ) {
					continue;
				}

				$name = (string) pathinfo( $path, PATHINFO_FILENAME );
				if ( $name === '' ) {
					continue;
				}

				$catalog[] = array(
					'id'     => $set . '/' . $name,
					'set'    => $set,
					'name'   => $name,
					'title'  => self::suggested_title( $name, $set ),
					'url'    => self::file_url( $set, $name ),
					'sprite' => false,
				);
			}
		}

		return $catalog;
	}

	private static function catalog_cache_key(): string {
		$parts = array( ASREKHODRO_THEME_VERSION );

		foreach ( array( 'custom', 'tabler', 'lucide', 'tabler-filled' ) as $set ) {
			$dir = self::DIR . '/' . $set;
			if ( ! is_dir( $dir ) ) {
				continue;
			}

			$parts[] = $set . ':' . (string) filemtime( $dir );
			$files   = glob( $dir . '/*.svg' ) ?: array();
			$parts[] = (string) count( $files );
		}

		return 'ak_car_spec_icons_' . md5( implode( '|', $parts ) );
	}

	/**
	 * Fast picker bootstrap — custom icons only (no SVG directory scan).
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function quick_initial_admin_items( int $limit = self::ADMIN_INITIAL_LIMIT ): array {
		$items = array();

		foreach ( self::custom_icons() as $item ) {
			$items[] = self::format_admin_item( $item );
			if ( count( $items ) >= $limit ) {
				break;
			}
		}

		return $items;
	}
	/**
	 * @return array{items: list<array<string, mixed>>, total: int, has_more: bool, next_offset: int}
	 */
	public static function query_admin_items( string $query = '', int $offset = 0, int $limit = self::ADMIN_PAGE_SIZE, string $include_id = '' ): array {
		$offset = max( 0, $offset );
		$limit  = max( 1, min( 50, $limit ) );
		$query  = mb_strtolower( trim( $query ) );

		$matches = array();
		foreach ( self::catalog() as $item ) {
			if ( $query === '' || self::item_matches_query( $item, $query ) ) {
				$matches[] = $item;
			}
		}

		$include_item = null;
		$include_id   = sanitize_text_field( $include_id );
		if ( $include_id !== '' ) {
			$include_item = self::item_for_id( $include_id );
		}

		$total = count( $matches );
		$slice = array_slice( $matches, $offset, $limit );
		$items = array();

		if ( $offset === 0 && $include_item !== null ) {
			$items[] = self::format_admin_item( $include_item );
		}

		foreach ( $slice as $item ) {
			if ( $include_item !== null && (string) $item['id'] === (string) $include_item['id'] ) {
				continue;
			}

			$items[] = self::format_admin_item( $item );
		}

		return array(
			'items'       => $items,
			'total'       => $total,
			'has_more'    => ( $offset + $limit ) < $total,
			'next_offset' => $offset + $limit,
		);
	}

	/**
	 * @param array<string, mixed> $item
	 */
	private static function item_matches_query( array $item, string $query ): bool {
		if ( $query === '' ) {
			return true;
		}

		$haystack = array(
			(string) ( $item['title'] ?? '' ),
			(string) ( $item['name'] ?? '' ),
			(string) ( $item['id'] ?? '' ),
			(string) ( $item['set'] ?? '' ),
			self::set_label( (string) ( $item['set'] ?? '' ) ),
		);

		foreach ( $haystack as $part ) {
			if ( $part !== '' && mb_strpos( mb_strtolower( $part ), $query ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<string, mixed> $item
	 * @return array<string, mixed>
	 */
	public static function format_admin_item( array $item ): array {
		$id = (string) ( $item['id'] ?? '' );

		return array(
			'id'       => $id,
			'set'      => (string) ( $item['set'] ?? '' ),
			'name'     => (string) ( $item['name'] ?? '' ),
			'title'    => (string) ( $item['title'] ?? '' ),
			'url'      => (string) ( $item['url'] ?? '' ),
			'sprite'   => ! empty( $item['sprite'] ),
			'symbol'   => str_starts_with( $id, 'custom/' ) ? substr( $id, 7 ) : '',
			'setLabel' => self::set_label( (string) ( $item['set'] ?? '' ) ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function acf_choices(): array {
		return array(
			'' => __( '— بدون آیکون —', 'asrekhodro' ),
		);
	}

	/**
	 * @return array{id: string, set: string, name: string, title: string, url: string, sprite: bool, symbol: string}|null
	 */
	public static function item_for_id( string $id ): ?array {
		$id = sanitize_text_field( $id );
		if ( $id === '' ) {
			return null;
		}

		foreach ( self::catalog() as $item ) {
			if ( (string) $item['id'] === $id ) {
				$resolved         = $item;
				$resolved['symbol'] = str_starts_with( $id, 'custom/' )
					? substr( $id, 7 )
					: '';

				return $resolved;
			}
		}

		return null;
	}

	public static function url_for_id( string $id ): string {
		$item = self::item_for_id( $id );
		if ( $item === null ) {
			return '';
		}

		return (string) $item['url'];
	}

	/**
	 * @param array<string, mixed> $field
	 * @return array<string, mixed>
	 */
	public static function load_acf_select_field( array $field ): array {
		$field['choices'] = self::acf_choices();

		return $field;
	}

	/**
	 * @return list<array{id: string, set: string, name: string, title: string, url: string, sprite: bool, symbol: string}>
	 */
	public static function admin_catalog(): array {
		$items = array();

		foreach ( self::catalog() as $item ) {
			$items[] = self::format_admin_item( $item );
		}

		return $items;
	}

	public static function sprite_url(): string {
		return ASREKHODRO_THEME_URI . '/assets/cinfo-icons/' . self::SPRITE_FILE;
	}

	private static function file_url( string $set, string $name ): string {
		$set  = sanitize_key( $set );
		$name = sanitize_file_name( $name );
		if ( $set === '' || $name === '' ) {
			return '';
		}

		return ASREKHODRO_THEME_URI . '/assets/cinfo-icons/' . rawurlencode( $set ) . '/' . rawurlencode( $name ) . '.svg';
	}

	private static function set_label( string $set ): string {
		return match ( $set ) {
			'custom'        => 'Custom',
			'tabler-filled' => 'Tabler Filled',
			'tabler'        => 'Tabler',
			'lucide'        => 'Lucide',
			default         => $set,
		};
	}

	/**
	 * @return list<array{id: string, set: string, name: string, title: string, url: string, sprite: bool}>
	 */
	private static function custom_icons(): array {
		$defs = array(
			'ak-cylinder'     => 'تعداد سیلندر / پیشرانه',
			'ak-displacement' => 'حجم موتور (CC)',
			'ak-turbo'        => 'تنفس موتور — توربو',
			'ak-na'           => 'تنفس طبیعی (NA)',
			'ak-hybrid'       => 'هیبرید (HEV)',
			'ak-phev'         => 'پلاگین هیبرید (PHEV)',
			'ak-ev'           => 'خودروی برقی (EV)',
			'ak-transmission' => 'نوع جعبه‌دنده',
			'ak-body'         => 'کلاس بدنه',
			'ak-fuel'         => 'مصرف سوخت',
			'ak-power'        => 'حداکثر توان / اسب‌بخار',
			'ak-torque'       => 'گشتاور',
			'ak-drivetrain'   => 'محور محرک',
			'ak-speed'        => 'حداکثر سرعت',
			'ak-wheel'        => 'سایز چرخ / رینگ',
			'ak-suspension'   => 'سیستم تعلیق',
		);

		$items = array();
		foreach ( $defs as $symbol => $title ) {
			$items[] = array(
				'id'     => 'custom/' . $symbol,
				'set'    => 'custom',
				'name'   => $symbol,
				'title'  => $title,
				'url'    => self::sprite_url() . '#' . $symbol,
				'sprite' => true,
			);
		}

		return $items;
	}

	private static function suggested_title( string $name, string $set ): string {
		unset( $set );

		$n     = strtolower( $name );
		$rules = array(
			array( 'cylinder|silandr|engine-off|^engine$|engine|manual-gear|car-turbine|car-fan|car-lifter', 'موتور / تعداد سیلندر' ),
			array( 'displacement|odometer|circle-gauge|gauge-circle', 'حجم موتور (CC) / داشبورد' ),
			array( '^gauge|dashboard', 'سرعت‌سنج / داشبورد' ),
			array( 'turbo|car-turbine|supercharg', 'تنفس موتور / توربو' ),
			array( 'transmission|manual-gear|automatic-gear|^cog$|settings-2', 'جعبه‌دنده / گیربکس' ),
			array( 'steering|steer', 'فرمان / هدایت' ),
			array( '^tire|^wheel|rim|hub', 'چرخ / رینگ / سایز لاستیک' ),
			array( 'gas-station|fuel|droplet|flame|^oil', 'سوخت / مصرف بنزین' ),
			array( 'battery|charging|plug|zap|ev-|electric|^bolt$', 'برق / باتری / شارژ' ),
			array( 'hybrid|phev', 'هیبرید / پلاگین' ),
			array( 'car-suv|car-4wd|car-door|car-garage|car-crash|caravan|car-front|car-taxi|^car$|car-off|car-crane|body|sedan|suv|coupe|hatch|wagon|pickup|convertible|crossover', 'کلاس بدنه / نوع خودرو' ),
			array( 'parking', 'پارک / سنسور پارک' ),
			array( 'road|route|navigation|map|gps|compass|direction|signpost|milestone|waypoint|u-turn|sign-left|sign-right', 'مسیر / ناوبری / جاده' ),
			array( 'speed', 'حداکثر سرعت' ),
			array( 'power|horse|hp', 'حداکثر توان / اسب‌بخار' ),
			array( 'torque', 'گشتاور' ),
			array( 'drivetrain|4wd|awd|rwd|fwd', 'محور محرک / دیفرانسیل' ),
			array( 'suspension|shock|spring|damper', 'سیستم تعلیق' ),
			array( 'air-conditioning|air-vent|temperature|snowflake|wind|cloud-rain|heater|fan|climate', 'تهویه / کولر / آب‌وهوا' ),
			array( 'radar|camera|cctv|scan|sensor|device-cctv|webcam', 'سنسور / دوربین / رادار' ),
			array( 'cruise|lane|blind|collision|assist|autopilot|acc', 'کروز کنترل / ایمنی پیشرفته' ),
			array( 'lock|key|alarm|shield|immobil', 'قفل / امنیت / دزدگیر' ),
			array( 'wrench|tool|hammer|drill|screwdriver|wash|repair|mechanic|settings|adjustment', 'سرویس / تعمیر / تنظیمات' ),
			array( 'filter', 'فیلتر روغن / هوا' ),
			array( 'trailer|caravan|hitch|tow|forklift|crane|dolly|hand-truck|container', 'یدک / بار / حمل' ),
			array( 'traffic|barrier|cone|traffic-light', 'ترافیک / علائم جاده' ),
			array( 'ambulance|first-aid|lifebuoy|helmet|stethoscope|pill|syringe|heart-pulse', 'ایمنی / سلامت' ),
			array( 'truck|bus|train|plane|ship|sailboat|helicopter|motorbike|scooter|moped|bike|tram|metro|subway|railway|locomotive|speedboat|bulldozer|tractor|firetruck', 'وسیله نقلیه' ),
			array( 'building-warehouse|building-factory|building-store|store|dealer|showroom|warehouse|factory|shopping-cart|home-move', 'نمایندگی / انبار / فروش' ),
			array( 'certificate|badge|award|trophy|medal|license|passport|ticket|id-card', 'گواهی / مدارک' ),
			array( '^check|check-circle', 'دارد / تأیید ویژگی' ),
			array( '^x$|x-circle|ban|-off$', 'ندارد / غیرفعال' ),
			array( 'alert|warning|info|help', 'هشدار / اطلاعات' ),
		);

		foreach ( $rules as $rule ) {
			if ( preg_match( '/' . $rule[0] . '/', $n ) ) {
				return $rule[1];
			}
		}

		return __( 'عمومی — مشخصات فنی', 'asrekhodro' );
	}
}
