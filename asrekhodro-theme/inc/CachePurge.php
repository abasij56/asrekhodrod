<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Purge the whole LiteSpeed page cache whenever public content is published, updated or unpublished.
 *
 * LiteSpeed purges via response headers, so purges triggered from WP-CLI / system cron (importer,
 * scheduled posts) are silently lost. The purge is therefore always performed by a loopback
 * admin-ajax request, which runs in a web context.
 */
final class CachePurge {

	private const AJAX_ACTION = 'ak_purge_page_cache';
	private const KEY_OPTION  = 'ak_cache_purge_key';

	private const POST_TYPES = array( 'post', 'ak_video', 'ak_review', 'ak_magazine', 'ad_slot', 'carsinfo' );

	private static bool $pending = false;

	public static function init(): void {
		add_action( 'transition_post_status', array( self::class, 'on_transition' ), 10, 3 );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( self::class, 'handle' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * @param string   $new_status
	 * @param string   $old_status
	 * @param \WP_Post $post
	 */
	public static function on_transition( $new_status, $old_status, $post ): void {
		if ( ! $post instanceof \WP_Post || ! in_array( $post->post_type, self::POST_TYPES, true ) ) {
			return;
		}

		if ( $new_status !== 'publish' && $old_status !== 'publish' ) {
			return;
		}

		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}

		if ( self::$pending ) {
			return;
		}

		self::$pending = true;
		add_action( 'shutdown', array( self::class, 'send_purge_request' ) );
	}

	public static function send_purge_request(): void {
		wp_remote_post(
			admin_url( 'admin-ajax.php' ),
			array(
				'timeout'   => 10,
				'blocking'  => true,
				'sslverify' => false,
				'body'      => array(
					'action' => self::AJAX_ACTION,
					'key'    => self::key(),
				),
			)
		);
	}

	public static function handle(): void {
		$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
		if ( $key === '' || ! hash_equals( self::key(), $key ) ) {
			wp_send_json_error( null, 403 );
		}

		do_action( 'litespeed_purge_all' );
		if ( ! headers_sent() ) {
			header( 'X-LiteSpeed-Purge: *', false );
		}

		wp_send_json_success();
	}

	private static function key(): string {
		$key = (string) get_option( self::KEY_OPTION, '' );
		if ( $key === '' ) {
			$key = wp_generate_password( 40, false, false );
			add_option( self::KEY_OPTION, $key, '', false );
		}

		return $key;
	}
}
