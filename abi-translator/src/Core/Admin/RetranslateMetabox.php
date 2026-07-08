<?php

namespace ABI\Translator\Core\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABI\Translator\Core\Filters\TranslatablePostTypes;
use ABI\Translator\Core\Support\Logger;
use ABI\Translator\Core\Translation\TranslationRepository;

/**
 * Adds a "Re-translate" metabox to translatable post types. Clicking the button
 * purges every cached translation for that post so the next /{lang}/ visit
 * re-translates from the current Persian source.
 */
final class RetranslateMetabox {

	private const ACTION       = 'abi_translator_retranslate';
	private const NONCE        = 'abi_translator_retranslate_nonce';
	private const NOTICE_FLAG  = 'abi_translator_retranslated';

	private TranslationRepository $repository;

	public function __construct( TranslationRepository $repository ) {
		$this->repository = $repository;
	}

	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_metabox' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_notices', array( $this, 'maybe_notice' ) );
	}

	public function add_metabox(): void {
		foreach ( TranslatablePostTypes::all() as $post_type ) {
			add_meta_box(
				'abi-translator-retranslate',
				__( 'ABI Translator', 'abi-translator' ),
				array( $this, 'render' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * @param mixed $post
	 */
	public function render( $post ): void {
		$post_id = $post instanceof \WP_Post ? (int) $post->ID : 0;
		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$action_url = admin_url( 'admin-post.php' );
		?>
		<p class="description">
			<?php echo esc_html__( 'Clear cached translations for this post. It will be re-translated on the next visit to a translated URL.', 'abi-translator' ); ?>
		</p>
		<form method="post" action="<?php echo esc_url( $action_url ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
			<input type="hidden" name="post_id" value="<?php echo esc_attr( (string) $post_id ); ?>" />
			<?php wp_nonce_field( self::ACTION . '_' . $post_id, self::NONCE ); ?>
			<button type="submit" class="button button-secondary">
				<?php echo esc_html__( 'Re-translate this post', 'abi-translator' ); ?>
			</button>
		</form>
		<?php
	}

	public function handle(): void {
		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;

		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'Permission denied.', 'abi-translator' ), 403 );
		}

		check_admin_referer( self::ACTION . '_' . $post_id, self::NONCE );

		$post = get_post( $post_id );
		if ( $post instanceof \WP_Post && TranslatablePostTypes::is_translatable( $post->post_type ) ) {
			try {
				$this->repository->delete_for_object( $post->post_type, $post_id );
			} catch ( \Throwable $e ) {
				Logger::warning(
					'Manual re-translate purge failed',
					array(
						'post_id' => $post_id,
						'reason'  => $e->getMessage(),
					)
				);
			}
		}

		$redirect = add_query_arg(
			array( self::NOTICE_FLAG => '1' ),
			get_edit_post_link( $post_id, 'url' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	public function maybe_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag.
		if ( empty( $_GET[ self::NOTICE_FLAG ] ) ) {
			return;
		}

		echo '<div class="notice notice-success is-dismissible"><p>'
			. esc_html__( 'ABI Translator: cached translations cleared. This post will be re-translated on the next translated-URL visit.', 'abi-translator' )
			. '</p></div>';
	}
}
