<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Limits the built-in Author role to the approved wp-admin areas:
 * news, categories/tags, media (+ CDN upload), comments, ads, videos,
 * magazines, carsinfo, and theme settings.
 */
final class AuthorAccess {

	public const THEME_SETTINGS_CAP = 'manage_asrekhodro_settings';

	/**
	 * Capabilities granted to authors beyond WordPress defaults.
	 *
	 * @var list<string>
	 */
	private const AUTHOR_EXTRA_CAPS = array(
		'manage_categories',
		'moderate_comments',
		'edit_others_posts',
		'edit_private_posts',
		'read_private_posts',
		'delete_others_posts',
		'delete_private_posts',
		'delete_published_posts',
		self::THEME_SETTINGS_CAP,
	);

	/**
	 * Top-level admin menu slugs authors may see.
	 *
	 * @var list<string>
	 */
	private const ALLOWED_MENUS = array(
		'index.php',
		'edit.php',
		'upload.php',
		'edit-comments.php',
		'edit.php?post_type=ad_slot',
		'edit.php?post_type=ak_video',
		'edit.php?post_type=ak_magazine',
		'edit.php?post_type=carsinfo',
		'asrekhodro-settings',
		'profile.php',
	);

	/**
	 * Post types an author may manage.
	 *
	 * @var list<string>
	 */
	private const ALLOWED_POST_TYPES = array(
		'post',
		'attachment',
		'ad_slot',
		'ak_video',
		'ak_magazine',
		'carsinfo',
	);

	/**
	 * Taxonomies an author may manage.
	 *
	 * @var list<string>
	 */
	private const ALLOWED_TAXONOMIES = array(
		'category',
		'post_tag',
		'ad_position',
		'video_category',
	);

	/**
	 * Theme-settings admin pages (parent + subpages).
	 *
	 * @var list<string>
	 */
	private const THEME_SETTINGS_PAGES = array(
		'asrekhodro-settings',
		'asrekhodro-layout-builder',
		'asrekhodro-icon-gallery',
		'asrekhodro-news-slug-repair',
		'add-external-media',
	);

	public static function init(): void {
		add_action( 'init', array( self::class, 'ensure_capabilities' ), 5 );
		add_action( 'admin_menu', array( self::class, 'restrict_admin_menu' ), 9999 );
		add_action( 'admin_init', array( self::class, 'block_forbidden_screens' ), 1 );
		add_action( 'admin_bar_menu', array( self::class, 'restrict_admin_bar' ), 999 );
		add_filter( 'map_meta_cap', array( self::class, 'map_meta_cap' ), 10, 4 );
		add_filter( 'rest_pre_dispatch', array( self::class, 'block_forbidden_rest' ), 10, 3 );
	}

	public static function can_manage_theme_settings(): bool {
		return current_user_can( self::THEME_SETTINGS_CAP );
	}

	public static function ensure_capabilities(): void {
		$author = get_role( 'author' );
		if ( $author instanceof \WP_Role ) {
			foreach ( self::AUTHOR_EXTRA_CAPS as $cap ) {
				$author->add_cap( $cap );
			}
		}

		$administrator = get_role( 'administrator' );
		if ( $administrator instanceof \WP_Role ) {
			$administrator->add_cap( self::THEME_SETTINGS_CAP );
		}
	}

	public static function is_restricted_author(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user = wp_get_current_user();
		$roles = (array) $user->roles;

		if ( in_array( 'administrator', $roles, true ) || in_array( 'editor', $roles, true ) ) {
			return false;
		}

		return in_array( 'author', $roles, true );
	}

	public static function restrict_admin_menu(): void {
		if ( ! self::is_restricted_author() ) {
			return;
		}

		global $menu, $submenu;

		if ( is_array( $menu ) ) {
			foreach ( $menu as $index => $item ) {
				$slug = isset( $item[2] ) ? (string) $item[2] : '';
				if ( $slug === '' || self::is_separator( $item ) ) {
					continue;
				}
				if ( ! in_array( $slug, self::ALLOWED_MENUS, true ) ) {
					remove_menu_page( $slug );
				}
			}
		}

		if ( ! is_array( $submenu ) ) {
			return;
		}

		foreach ( $submenu as $parent => $items ) {
			if ( ! in_array( (string) $parent, self::ALLOWED_MENUS, true ) ) {
				continue;
			}

			foreach ( (array) $items as $item ) {
				$slug = isset( $item[2] ) ? (string) $item[2] : '';
				if ( $slug === '' || self::is_submenu_allowed( (string) $parent, $slug ) ) {
					continue;
				}
				remove_submenu_page( (string) $parent, $slug );
			}
		}
	}

	public static function block_forbidden_screens(): void {
		if ( ! self::is_restricted_author() || ! is_admin() || wp_doing_ajax() ) {
			return;
		}

		global $pagenow;

		$pagenow = is_string( $pagenow ) ? $pagenow : '';
		$page    = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['page'] ) ) : '';
		$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( (string) $_GET['taxonomy'] ) ) : '';
		$post_type = self::request_post_type();

		if ( in_array( $pagenow, array( 'admin-post.php', 'async-upload.php', 'media-upload.php' ), true ) ) {
			return;
		}

		if ( $page !== '' && in_array( $page, self::THEME_SETTINGS_PAGES, true ) ) {
			return;
		}

		if ( in_array( $pagenow, array( 'index.php', 'profile.php', 'upload.php', 'media-new.php', 'media.php', 'edit-comments.php', 'comment.php' ), true ) ) {
			return;
		}

		if ( in_array( $pagenow, array( 'edit.php', 'post.php', 'post-new.php' ), true ) ) {
			if ( in_array( $post_type, self::ALLOWED_POST_TYPES, true ) ) {
				return;
			}
			self::deny();
		}

		if ( in_array( $pagenow, array( 'edit-tags.php', 'term.php' ), true ) ) {
			if ( in_array( $taxonomy, self::ALLOWED_TAXONOMIES, true ) ) {
				return;
			}
			self::deny();
		}

		if ( $pagenow === 'admin.php' && $page !== '' && in_array( $page, self::THEME_SETTINGS_PAGES, true ) ) {
			return;
		}

		self::deny();
	}

	/**
	 * Meta capabilities that receive a post ID in $args[0].
	 *
	 * @var list<string>
	 */
	private const POST_OBJECT_META_CAPS = array(
		'edit_post',
		'read_post',
		'delete_post',
		'publish_post',
		'edit_page',
		'read_page',
		'delete_page',
		'edit_post_meta',
		'delete_post_meta',
		'add_post_meta',
	);

	/**
	 * Meta capabilities that receive a term ID in $args[0].
	 *
	 * @var list<string>
	 */
	private const TERM_OBJECT_META_CAPS = array(
		'assign_term',
		'edit_term',
		'delete_term',
	);

	/**
	 * @param list<string> $caps
	 * @param list<mixed>  $args
	 * @return list<string>
	 */
	public static function map_meta_cap( array $caps, string $cap, int $user_id, array $args ): array {
		if ( $user_id <= 0 || ! self::user_is_restricted_author( $user_id ) ) {
			return $caps;
		}

		// Term meta-caps pass a term ID — never treat it as a post ID
		// (collision caused "not allowed to assign the provided terms").
		if ( in_array( $cap, self::TERM_OBJECT_META_CAPS, true ) ) {
			return self::map_term_meta_cap( $caps, $args );
		}

		if ( ! in_array( $cap, self::POST_OBJECT_META_CAPS, true ) ) {
			return $caps;
		}

		$post_id = isset( $args[0] ) ? (int) $args[0] : 0;
		if ( $post_id <= 0 ) {
			return $caps;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return $caps;
		}

		if ( ! in_array( $post->post_type, self::ALLOWED_POST_TYPES, true ) ) {
			return array( 'do_not_allow' );
		}

		return $caps;
	}

	/**
	 * @param list<string> $caps
	 * @param list<mixed>  $args
	 * @return list<string>
	 */
	private static function map_term_meta_cap( array $caps, array $args ): array {
		$term_id = isset( $args[0] ) ? (int) $args[0] : 0;
		if ( $term_id <= 0 ) {
			return $caps;
		}

		$term = get_term( $term_id );
		if ( ! $term instanceof \WP_Term ) {
			return $caps;
		}

		if ( ! in_array( $term->taxonomy, self::ALLOWED_TAXONOMIES, true ) ) {
			return array( 'do_not_allow' );
		}

		return $caps;
	}

	/**
	 * @param mixed            $result
	 * @param \WP_REST_Server  $server
	 * @param \WP_REST_Request $request
	 * @return mixed
	 */
	public static function block_forbidden_rest( $result, $server, \WP_REST_Request $request ) {
		unset( $server );

		if ( ! self::is_restricted_author() ) {
			return $result;
		}

		$route  = $request->get_route();
		$method = strtoupper( $request->get_method() );

		if ( ! in_array( $method, array( 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
			return $result;
		}

		if ( preg_match( '#/wp/v2/(pages|ak_review)(?:/|$)#', $route ) ) {
			return new \WP_Error(
				'asrekhodro_rest_forbidden',
				__( 'شما به این بخش دسترسی ندارید.', 'asrekhodro' ),
				array( 'status' => 403 )
			);
		}

		return $result;
	}

	public static function restrict_admin_bar( \WP_Admin_Bar $bar ): void {
		if ( ! self::is_restricted_author() ) {
			return;
		}

		foreach ( array( 'customize', 'themes', 'widgets', 'menus', 'updates', 'new-page', 'new-user', 'new-ak_review' ) as $id ) {
			$bar->remove_node( $id );
		}
	}

	/**
	 * @param array<int, mixed> $item
	 */
	private static function is_separator( array $item ): bool {
		return isset( $item[4] ) && is_string( $item[4] ) && str_contains( $item[4], 'wp-menu-separator' );
	}

	private static function is_submenu_allowed( string $parent, string $slug ): bool {
		$allowed_by_parent = array(
			'edit.php' => array(
				'edit.php',
				'post-new.php',
				'edit-tags.php?taxonomy=category',
				'edit-tags.php?taxonomy=post_tag',
			),
			'upload.php' => array(
				'upload.php',
				'media-new.php',
				'add-external-media',
				'upload.php?page=add-external-media',
			),
			'edit-comments.php' => array(
				'edit-comments.php',
			),
			'edit.php?post_type=ad_slot' => array(
				'edit.php?post_type=ad_slot',
				'post-new.php?post_type=ad_slot',
				'edit-tags.php?taxonomy=ad_position&amp;post_type=ad_slot',
				'edit-tags.php?taxonomy=ad_position&post_type=ad_slot',
			),
			'edit.php?post_type=ak_video' => array(
				'edit.php?post_type=ak_video',
				'post-new.php?post_type=ak_video',
				'edit-tags.php?taxonomy=video_category&amp;post_type=ak_video',
				'edit-tags.php?taxonomy=video_category&post_type=ak_video',
			),
			'edit.php?post_type=ak_magazine' => array(
				'edit.php?post_type=ak_magazine',
				'post-new.php?post_type=ak_magazine',
			),
			'edit.php?post_type=carsinfo' => array(
				'edit.php?post_type=carsinfo',
				'post-new.php?post_type=carsinfo',
				'edit-tags.php?taxonomy=category&amp;post_type=carsinfo',
				'edit-tags.php?taxonomy=category&post_type=carsinfo',
			),
			'asrekhodro-settings' => array(
				'asrekhodro-settings',
				'asrekhodro-layout-builder',
				'asrekhodro-icon-gallery',
				'asrekhodro-news-slug-repair',
			),
			'profile.php' => array(
				'profile.php',
			),
			'index.php' => array(
				'index.php',
			),
		);

		$allowed = $allowed_by_parent[ $parent ] ?? array();
		if ( in_array( $slug, $allowed, true ) ) {
			return true;
		}

		// WordPress may HTML-encode query ampersands in submenu slugs.
		$normalized = html_entity_decode( $slug, ENT_QUOTES );
		return in_array( $normalized, $allowed, true );
	}

	private static function request_post_type(): string {
		if ( isset( $_GET['post_type'] ) ) {
			$type = sanitize_key( wp_unslash( (string) $_GET['post_type'] ) );
			return $type !== '' ? $type : 'post';
		}

		if ( isset( $_GET['post'] ) ) {
			$post = get_post( (int) $_GET['post'] );
			if ( $post instanceof \WP_Post ) {
				return $post->post_type;
			}
		}

		if ( isset( $_POST['post_ID'] ) ) {
			$post = get_post( (int) $_POST['post_ID'] );
			if ( $post instanceof \WP_Post ) {
				return $post->post_type;
			}
		}

		global $pagenow;
		if ( in_array( $pagenow, array( 'edit.php', 'post-new.php', 'post.php' ), true ) ) {
			return 'post';
		}

		return '';
	}

	private static function user_is_restricted_author( int $user_id ): bool {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$roles = (array) $user->roles;
		if ( in_array( 'administrator', $roles, true ) || in_array( 'editor', $roles, true ) ) {
			return false;
		}

		return in_array( 'author', $roles, true );
	}

	private static function deny(): void {
		wp_die(
			esc_html__( 'شما به این بخش دسترسی ندارید.', 'asrekhodro' ),
			esc_html__( 'دسترسی غیرمجاز', 'asrekhodro' ),
			array( 'response' => 403 )
		);
	}
}
