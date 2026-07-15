<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Carsinfo encyclopedia directory — countries, brands, and model cards.
 */
final class CarsInfoDirectory {

	private static bool $assets_needed = false;

	public static function mark_assets_needed(): void {
		self::$assets_needed = true;
	}

	public static function needs_assets(): bool {
		return self::$assets_needed || self::page_has_directory_block();
	}

	public static function page_has_directory_block(): bool {
		if ( is_page_template( 'page-carsinfo-directory.php' ) ) {
			return true;
		}

		return is_singular() && has_block( 'acf/carsinfo-directory' );
	}

	/**
	 * Public archive URL for the cars encyclopedia directory page.
	 */
	public static function archive_url(): string {
		$pages = get_posts(
			array(
				'post_type'              => 'page',
				'post_status'            => 'publish',
				'meta_key'               => '_wp_page_template',
				'meta_value'             => 'page-carsinfo-directory.php',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if ( $pages !== array() ) {
			$url = get_permalink( (int) $pages[0] );
			if ( is_string( $url ) && $url !== '' ) {
				return esc_url( $url );
			}
		}

		$page = get_page_by_path( 'دانشنامه-خودرو' );
		if ( $page instanceof \WP_Post ) {
			$url = get_permalink( $page );
			if ( is_string( $url ) && $url !== '' ) {
				return esc_url( $url );
			}
		}

		return esc_url( home_url( '/دانشنامه-خودرو/' ) );
	}

	/**
	 * @param array<string, mixed> $fields
	 * @return array<string, mixed>
	 */
	public static function block_context( array $fields = array() ): array {
		self::mark_assets_needed();

		$country_root = CarsInfo::country_category_id();
		$brand_root   = CarsInfo::brand_category_id();
		$configured   = $country_root > 0 && $brand_root > 0;
		$brands          = $configured ? self::brand_payload( $brand_root ) : array();
		$featured_models = self::featured_models( self::featured_post_ids( $fields ) );

		return array(
			'directory_eyebrow'           => trim( (string) ( $fields['eyebrow'] ?? '' ) ) ?: 'Car Encyclopedia',
			'directory_title'             => trim( (string) ( $fields['title'] ?? '' ) ) ?: 'دانشنامه خودرو',
			'directory_lead'              => trim( (string) ( $fields['lead'] ?? '' ) ) ?: 'ابتدا برند را انتخاب کنید، سپس مدل مورد نظر را بیابید و وارد صفحه اختصاصی آن شوید.',
			'directory_search_placeholder'  => trim( (string) ( $fields['search_placeholder'] ?? '' ) ) ?: 'جستجوی برند یا مدل...',
			'directory_show_steps'        => ! array_key_exists( 'show_steps', $fields ) || ! empty( $fields['show_steps'] ),
			'directory_configured'        => $configured,
			'directory_has_items'         => $brands !== array(),
			'directory_empty_hint'        => $configured ? self::empty_hint( $brand_root, $country_root ) : '',
			'directory_config_message'    => $configured
				? ''
				: 'کتگوری کشور و برند را در Theme Settings → تنظیمات دانشنامه خودرو مشخص کنید.',
			'directory_countries'         => $configured ? self::country_filters( $country_root ) : array(),
			'directory_brands'            => $brands,
			'directory_json'              => $configured
				? wp_json_encode(
					array(
						'countries' => self::country_filters( $country_root ),
						'brands'    => $brands,
					),
					JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
				)
				: '{}',
			'directory_featured_title'    => trim( (string) ( $fields['featured_title'] ?? '' ) ) ?: 'منتخب‌ها',
			'directory_featured_cars'     => $featured_models,
			'directory_has_featured'      => $featured_models !== array(),
		);
	}

	/**
	 * @param array<string, mixed> $fields
	 * @return list<int>
	 */
	private static function featured_post_ids( array $fields ): array {
		$value = $fields['featured_cars'] ?? array();
		if ( ! is_array( $value ) ) {
			return array();
		}

		$ids = array();
		foreach ( $value as $post_id ) {
			$post_id = (int) $post_id;
			if ( $post_id > 0 ) {
				$ids[] = $post_id;
			}
		}

		return $ids;
	}

	/**
	 * @param list<int> $post_ids
	 * @return list<array<string, mixed>>
	 */
	public static function featured_models( array $post_ids ): array {
		if ( $post_ids === array() ) {
			return array();
		}

		$country_root = CarsInfo::country_category_id();
		$models       = array();

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			if ( $post->post_type !== 'carsinfo' || $post->post_status !== 'publish' ) {
				continue;
			}

			$models[] = self::format_model( $post, $country_root );
		}

		return $models;
	}

	/**
	 * Featured-car cards (brand logo + up to 3 card specs) for the ماشین‌های منتخب block.
	 *
	 * @param list<int> $post_ids
	 * @return list<array<string, mixed>>
	 */
	public static function featured_cards( array $post_ids ): array {
		if ( $post_ids === array() ) {
			return array();
		}

		$country_root = CarsInfo::country_category_id();
		$cards        = array();

		foreach ( $post_ids as $post_id ) {
			$post = get_post( (int) $post_id );
			if ( ! $post instanceof \WP_Post || $post->post_type !== 'carsinfo' || $post->post_status !== 'publish' ) {
				continue;
			}

			$cards[] = self::featured_card( $post, $country_root );
		}

		return $cards;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function latest_featured_cards( int $count, int $category = 0 ): array {
		$count = max( 1, min( 24, $count ) );

		$args = array(
			'post_type'              => 'carsinfo',
			'post_status'            => 'publish',
			'posts_per_page'         => $count,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => true,
		);

		if ( $category > 0 ) {
			$args['cat'] = $category;
		}

		$posts        = get_posts( $args );
		$country_root = CarsInfo::country_category_id();
		$cards        = array();

		foreach ( (array) $posts as $post ) {
			if ( $post instanceof \WP_Post ) {
				$cards[] = self::featured_card( $post, $country_root );
			}
		}

		return $cards;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function featured_card( \WP_Post $post, int $country_root ): array {
		$model      = self::format_model( $post, $country_root );
		$brand_root = CarsInfo::brand_category_id();
		$brand      = $brand_root > 0 ? self::post_brand_term( (int) $post->ID, $brand_root, $country_root ) : null;

		$model['brand'] = $brand instanceof \WP_Term
			? array(
				'id'   => (int) $brand->term_id,
				'name' => (string) $brand->name,
				'abbr' => self::abbr( (string) $brand->name ),
				'logo' => ThemeCategories::term_directory_logo( (int) $brand->term_id, (string) $brand->name ),
			)
			: array(
				'id'   => 0,
				'name' => '',
				'abbr' => '',
				'logo' => array( 'url' => '', 'alt' => '', 'width' => 0, 'height' => 0 ),
			);

		$content        = (string) $post->post_content;
		$model['specs'] = self::card_specs( $content );
		$model['rates'] = self::card_rates( $content );

		return $model;
	}

	/**
	 * Up to 3 card specs pulled from the cinfo-facts block (items flagged «نمایش در کارت»).
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function card_specs( string $content ): array {
		$rows = array();
		foreach ( self::block_data_sets( $content, 'acf/cinfo-facts' ) as $data ) {
			$count = (int) ( $data['fact_items'] ?? 0 );
			for ( $i = 0; $i < $count; $i++ ) {
				$p      = 'fact_items_' . $i . '_';
				$rows[] = array(
					'item_label'    => (string) ( $data[ $p . 'item_label' ] ?? '' ),
					'item_value'    => (string) ( $data[ $p . 'item_value' ] ?? '' ),
					'item_icon'     => (string) ( $data[ $p . 'item_icon' ] ?? '' ),
					'item_icon_svg' => (string) ( $data[ $p . 'item_icon_svg' ] ?? '' ),
					'show_in_card'  => ! empty( $data[ $p . 'show_in_card' ] ),
				);
			}
		}

		if ( $rows === array() ) {
			return array();
		}

		$ctx   = \AsreKhodro\Theme\AcfBlocks\CinfoFacts\View::context( array( 'fact_items' => $rows ) );
		$items = is_array( $ctx['facts_items'] ?? null ) ? $ctx['facts_items'] : array();

		$specs = array();
		foreach ( $items as $item ) {
			if ( empty( $item['show_in_card'] ) ) {
				continue;
			}

			$text = trim( (string) ( $item['value'] ?? '' ) );
			if ( $text === '' ) {
				$text = trim( (string) ( $item['label'] ?? '' ) );
			}
			if ( $text === '' ) {
				continue;
			}

			$specs[] = array(
				'text'        => $text,
				'label'       => (string) ( $item['label'] ?? '' ),
				'icon_url'    => (string) ( $item['icon_url'] ?? '' ),
				'icon_sprite' => ! empty( $item['icon_sprite'] ),
				'icon_svg'    => (string) ( $item['icon_svg'] ?? '' ),
			);

			if ( count( $specs ) >= 3 ) {
				break;
			}
		}

		return $specs;
	}

	/**
	 * Up to 4 rate rows pulled from the cinfo-hero block (items flagged «نمایش در کارت»).
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function card_rates( string $content ): array {
		$rows = array();
		foreach ( self::block_data_sets( $content, 'acf/cinfo-hero' ) as $data ) {
			$count = (int) ( $data['rate_items'] ?? 0 );
			for ( $i = 0; $i < $count; $i++ ) {
				$p      = 'rate_items_' . $i . '_';
				$rows[] = array(
					'item_title'   => (string) ( $data[ $p . 'item_title' ] ?? '' ),
					'item_rate'    => (string) ( $data[ $p . 'item_rate' ] ?? '' ),
					'show_in_card' => ! empty( $data[ $p . 'show_in_card' ] ),
				);
			}
		}

		if ( $rows === array() ) {
			return array();
		}

		$ctx   = \AsreKhodro\Theme\AcfBlocks\CinfoHero\View::context( array( 'rate_items' => $rows ) );
		$items = is_array( $ctx['hero_rate_items'] ?? null ) ? $ctx['hero_rate_items'] : array();

		$rates = array();
		foreach ( $items as $item ) {
			if ( empty( $item['show_in_card'] ) ) {
				continue;
			}

			$title = trim( (string) ( $item['title'] ?? '' ) );
			if ( $title === '' ) {
				continue;
			}

			$rates[] = array(
				'title'      => $title,
				'rate_label' => (string) ( $item['rate_label'] ?? '' ),
				'bar_width'  => (float) ( $item['bar_width'] ?? 0 ),
			);

			if ( count( $rates ) >= 4 ) {
				break;
			}
		}

		return $rates;
	}

	/**
	 * Collect flattened ACF block data arrays for every instance of a block in post content.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function block_data_sets( string $content, string $block_name ): array {
		if ( $content === '' || ! function_exists( 'parse_blocks' ) ) {
			return array();
		}

		$sets = array();
		self::collect_block_data( parse_blocks( $content ), $block_name, $sets );

		return $sets;
	}

	/**
	 * @param array<int, mixed>          $blocks
	 * @param list<array<string, mixed>> $sets
	 */
	private static function collect_block_data( array $blocks, string $block_name, array &$sets ): void {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			if ( ( $block['blockName'] ?? '' ) === $block_name ) {
				$data = $block['attrs']['data'] ?? array();
				if ( is_array( $data ) ) {
					$sets[] = $data;
				}
			}

			$inner = $block['innerBlocks'] ?? array();
			if ( is_array( $inner ) && $inner !== array() ) {
				self::collect_block_data( $inner, $block_name, $sets );
			}
		}
	}

	private static function empty_hint( int $brand_root, int $country_root ): string {
		$published = self::published_carsinfo_posts();
		if ( $published === array() ) {
			return 'هنوز پست carsinfo منتشرشده‌ای وجود ندارد.';
		}

		$missing_brand = 0;
		foreach ( $published as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			if ( self::post_brand_term( (int) $post->ID, $brand_root, $country_root ) === null ) {
				++$missing_brand;
			}
		}

		if ( $missing_brand === count( $published ) ) {
			return sprintf(
				/* translators: %d: number of carsinfo posts */
				__( '%d پست carsinfo دارید، اما به هیچ‌کدام دسته برند (زیر «کتگوری برند» در Theme Settings) اختصاص داده نشده است.', 'asrekhodro' ),
				count( $published )
			);
		}

		if ( $missing_brand > 0 ) {
			return sprintf(
				/* translators: 1: missing count, 2: total count */
				__( '%1$d از %2$d پست carsinfo دسته برند ندارند. برای نمایش در آرشیو، هر پست باید یک دسته برند و یک دسته کشور داشته باشد.', 'asrekhodro' ),
				$missing_brand,
				count( $published )
			);
		}

		return __( 'مدلی برای نمایش پیدا نشد. دسته برند و کشور هر پست را بررسی کنید.', 'asrekhodro' );
	}

	/**
	 * @return list<array{id: int, slug: string, name: string}>
	 */
	private static function country_filters( int $root_id ): array {
		$children = self::child_terms( $root_id );
		if ( $children !== array() ) {
			return array_map( array( self::class, 'term_row' ), $children );
		}

		$root = get_term( $root_id, 'category' );
		if ( $root instanceof \WP_Term && ! is_wp_error( $root ) ) {
			return array( self::term_row( $root ) );
		}

		return array();
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function brand_payload( int $root_id ): array {
		$country_root = CarsInfo::country_category_id();
		$groups       = self::group_posts_by_brand( $root_id, $country_root );
		$payload      = array();

		foreach ( $groups as $group ) {
			$term = $group['term'] ?? null;
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			$models = is_array( $group['models'] ?? null ) ? $group['models'] : array();
			$logo   = ThemeCategories::term_directory_logo( (int) $term->term_id, (string) $term->name );
			$payload[] = array(
				'id'          => (int) $term->term_id,
				'slug'        => (string) $term->slug,
				'name'        => (string) $term->name,
				'abbr'        => self::abbr( (string) $term->name ),
				'logo'        => $logo,
				'model_count' => count( $models ),
				'models'      => $models,
			);
		}

		usort(
			$payload,
			static function ( array $a, array $b ): int {
				return strcmp( (string) ( $a['name'] ?? '' ), (string) ( $b['name'] ?? '' ) );
			}
		);

		return $payload;
	}

	/**
	 * @return array<int, array{term: \WP_Term, models: list<array<string, mixed>>}>
	 */
	private static function group_posts_by_brand( int $brand_root, int $country_root ): array {
		$groups = array();

		foreach ( self::published_carsinfo_posts() as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$brand = self::post_brand_term( (int) $post->ID, $brand_root, $country_root );
			if ( ! $brand instanceof \WP_Term ) {
				continue;
			}

			$term_id = (int) $brand->term_id;
			if ( ! isset( $groups[ $term_id ] ) ) {
				$groups[ $term_id ] = array(
					'term'   => $brand,
					'models' => array(),
				);
			}

			$groups[ $term_id ]['models'][] = self::format_model( $post, $country_root );
		}

		return $groups;
	}

	/**
	 * @return list<\WP_Post>
	 */
	private static function published_carsinfo_posts(): array {
		$posts = get_posts(
			array(
				'post_type'              => 'carsinfo',
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => true,
			)
		);

		return is_array( $posts ) ? $posts : array();
	}

	private static function post_brand_term( int $post_id, int $brand_root, int $country_root ): ?\WP_Term {
		if ( $post_id <= 0 || $brand_root <= 0 ) {
			return null;
		}

		$terms = wp_get_post_terms( $post_id, 'category' );
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return null;
		}

		$candidates = array();
		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			if ( (int) $term->term_id === $brand_root ) {
				if ( $country_root <= 0 || ! self::term_belongs_to_root( $term, $country_root ) ) {
					$candidates[] = $term;
				}
				continue;
			}

			if ( $country_root > 0 && self::term_belongs_to_root( $term, $country_root ) ) {
				continue;
			}

			if ( self::term_belongs_to_root( $term, $brand_root ) ) {
				$candidates[] = $term;
			}
		}

		if ( $candidates === array() ) {
			return null;
		}

		usort(
			$candidates,
			static function ( \WP_Term $a, \WP_Term $b ): int {
				return self::term_depth( $b ) <=> self::term_depth( $a );
			}
		);

		return $candidates[0];
	}

	private static function term_depth( \WP_Term $term ): int {
		$depth  = 0;
		$parent = (int) $term->parent;

		while ( $parent > 0 ) {
			++$depth;
			$ancestor = get_term( $parent, 'category' );
			if ( ! $ancestor instanceof \WP_Term || is_wp_error( $ancestor ) ) {
				break;
			}
			$parent = (int) $ancestor->parent;
		}

		return $depth;
	}

	/**
	 * @return list<\WP_Term>
	 */
	private static function child_terms( int $parent_id ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'parent'     => $parent_id,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$terms,
				static fn( $term ): bool => $term instanceof \WP_Term
			)
		);
	}

	/**
	 * @return array{id: int, slug: string, name: string}
	 */
	private static function term_row( \WP_Term $term ): array {
		return array(
			'id'   => (int) $term->term_id,
			'slug' => (string) $term->slug,
			'name' => (string) $term->name,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function format_model( \WP_Post $post, int $country_root = 0 ): array {
		$thumb_id = (int) get_post_thumbnail_id( $post->ID );
		$image    = array(
			'url'    => '',
			'alt'    => (string) get_the_title( $post ),
			'width'  => 600,
			'height' => 375,
		);

		if ( $thumb_id > 0 ) {
			$src = wp_get_attachment_image_src( $thumb_id, 'medium_large' );
			if ( is_array( $src ) ) {
				$image['url']    = (string) ( $src[0] ?? '' );
				$image['width']  = (int) ( $src[1] ?? 600 );
				$image['height'] = (int) ( $src[2] ?? 375 );
			}

			$alt = get_post_meta( $thumb_id, '_wp_attachment_image_alt', true );
			if ( is_string( $alt ) && $alt !== '' ) {
				$image['alt'] = $alt;
			}
		}

		$subtitle = trim( (string) get_the_excerpt( $post ) );
		if ( $subtitle === '' ) {
			$subtitle = trim( (string) $post->post_excerpt );
		}

		return array(
			'id'        => (int) $post->ID,
			'title'     => (string) get_the_title( $post ),
			'subtitle'  => $subtitle,
			'url'       => (string) get_permalink( $post ),
			'image'     => $image,
			'specs'     => array(),
			'countries' => self::post_country_ids( (int) $post->ID, $country_root ),
		);
	}

	/**
	 * @return list<int>
	 */
	private static function post_country_ids( int $post_id, int $country_root ): array {
		if ( $post_id <= 0 || $country_root <= 0 ) {
			return array();
		}

		$terms = wp_get_post_terms( $post_id, 'category' );
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		$brand_root = CarsInfo::brand_category_id();
		$ids        = array();
		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			if ( $brand_root > 0 && self::term_belongs_to_root( $term, $brand_root ) ) {
				continue;
			}

			if ( self::term_belongs_to_root( $term, $country_root ) && (int) $term->term_id !== $country_root ) {
				$ids[] = (int) $term->term_id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	private static function term_belongs_to_root( \WP_Term $term, int $root_id ): bool {
		if ( (int) $term->term_id === $root_id ) {
			return true;
		}

		$ancestor = (int) $term->parent;
		while ( $ancestor > 0 ) {
			if ( $ancestor === $root_id ) {
				return true;
			}

			$parent = get_term( $ancestor, 'category' );
			if ( ! $parent instanceof \WP_Term || is_wp_error( $parent ) ) {
				break;
			}

			$ancestor = (int) $parent->parent;
		}

		return false;
	}

	private static function abbr( string $name ): string {
		$name = trim( $name );
		if ( $name === '' ) {
			return '?';
		}

		if ( function_exists( 'mb_substr' ) ) {
			return (string) mb_substr( $name, 0, 1 );
		}

		return (string) substr( $name, 0, 1 );
	}
}
