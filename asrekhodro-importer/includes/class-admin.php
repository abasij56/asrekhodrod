<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AsreKhodro_Importer_Admin {

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
	}

	public static function register_menu(): void {
		add_management_page(
			'AsreKhodro Import',
			'AsreKhodro Import',
			'manage_options',
			'asrekhodro-import',
			array( self::class, 'render_page' )
		);
	}

	public static function enqueue_assets( string $hook_suffix ): void {
		if ( $hook_suffix !== 'tools_page_asrekhodro-import' ) {
			return;
		}

		wp_enqueue_script(
			'asrekhodro-importer-admin',
			plugins_url( 'assets/admin-import.js', ASREKHODRO_IMPORTER_DIR . 'asrekhodro-importer.php' ),
			array( 'jquery' ),
			ASREKHODRO_IMPORTER_VERSION,
			true
		);

		wp_localize_script(
			'asrekhodro-importer-admin',
			'akImporter',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'asrekhodro_import_progress' ),
				'postBatch' => array(
					'default' => AsreKhodro_Import_Session::default_post_batch_size(),
					'min'     => 1,
					'max'     => AsreKhodro_Import_Session::max_post_batch_size(),
				),
				'strings' => array(
					'starting'       => __( 'Starting import…', 'asrekhodro' ),
					'started'        => __( 'Import started.', 'asrekhodro' ),
					'finished'       => __( 'Import finished.', 'asrekhodro' ),
					'error'          => __( 'Import failed.', 'asrekhodro' ),
					'cancel'         => __( 'Cancel Import', 'asrekhodro' ),
					'cancelling'     => __( 'Cancelling import…', 'asrekhodro' ),
					'cancelled'      => __( 'Import cancelled. Items already imported remain in WordPress.', 'asrekhodro' ),
					'confirmCancel'  => __( 'Stop the import? Already imported items will stay in WordPress.', 'asrekhodro' ),
					'confirmReset'   => __( 'Remove selected imported items from WordPress before import? This cannot be undone.', 'asrekhodro' ),
					'invalidBatch'   => sprintf(
						/* translators: 1: minimum batch size, 2: maximum batch size */
						__( 'Posts per batch must be between %1$d and %2$d.', 'asrekhodro' ),
						1,
						AsreKhodro_Import_Session::max_post_batch_size()
					),
					'contentStarting' => __( 'Starting content re-import…', 'asrekhodro' ),
					'contentStarted'  => __( 'Content re-import started.', 'asrekhodro' ),
					'contentFinished' => __( 'Content re-import finished.', 'asrekhodro' ),
					'contentError'    => __( 'Content re-import failed.', 'asrekhodro' ),
					'contentCancel'   => __( 'Cancel Content Re-import', 'asrekhodro' ),
					'contentCancelling' => __( 'Cancelling content re-import…', 'asrekhodro' ),
					'contentCancelled'  => __( 'Content re-import cancelled. Posts already updated remain unchanged.', 'asrekhodro' ),
					'confirmContentCancel' => __( 'Stop content re-import? Posts already updated will stay as they are.', 'asrekhodro' ),
				),
			)
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$import_dir  = ASREKHODRO_IMPORTER_DEFAULT_IMPORT_DIR;
		$manifest    = $import_dir . '/manifest.json';
		$ready       = file_exists( $manifest );
		$posts_ready = AsreKhodro_Content_Reimport_Session::has_post_export( $import_dir );
		$totals     = $ready ? AsreKhodro_Importer::get_file_totals( $import_dir ) : array();
		$saved_batch = (int) get_user_meta( get_current_user_id(), 'asrekhodro_import_post_batch_size', true );
		$post_batch  = $saved_batch > 0
			? AsreKhodro_Import_Session::sanitize_post_batch_size( $saved_batch )
			: AsreKhodro_Import_Session::default_post_batch_size();
		$max_batch   = AsreKhodro_Import_Session::max_post_batch_size();

		?>
		<div class="wrap">
			<h1>AsreKhodro Import</h1>
			<p>Import sample JSON from SQL Server export. Image files are <strong>not</strong> copied to the server — thumbnails are registered in the Media Library as external URLs (requires the Asre Khodro theme).</p>
			<p>Optional: add to <code>wp-config.php</code> for relative image paths:<br>
			<code>define('ASREKHODRO_MEDIA_BASE_URL', 'https://www.asrekhodro.com');</code></p>

			<table class="widefat striped" style="max-width:900px">
				<tbody>
					<tr>
						<th>Import folder</th>
						<td><code><?php echo esc_html( $import_dir ); ?></code></td>
					</tr>
					<tr>
						<th>Export manifest</th>
						<td><?php echo $ready ? '<span style="color:green">Found</span>' : '<span style="color:red">Missing — run npm run export:sample first</span>'; ?></td>
					</tr>
					<?php if ( $ready ) : ?>
					<tr>
						<th>Items to import</th>
						<td>
							<?php echo esc_html( (string) ( $totals['posts'] ?? 0 ) ); ?> posts,
							<?php echo esc_html( (string) ( $totals['videos'] ?? 0 ) ); ?> videos,
							<?php echo esc_html( (string) ( $totals['reviews'] ?? 0 ) ); ?> reviews,
							<?php echo esc_html( (string) ( $totals['magazines'] ?? 0 ) ); ?> magazines,
							<?php echo esc_html( (string) ( $totals['categories'] ?? 0 ) ); ?> categories,
							<?php echo esc_html( (string) ( $totals['ads'] ?? 0 ) ); ?> ads,
							<?php echo esc_html( (string) ( $totals['comments'] ?? 0 ) ); ?> comments
						</td>
					</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<h2 style="margin-top:24px"><?php esc_html_e( 'Import options', 'asrekhodro' ); ?></h2>
			<table class="form-table" style="max-width:900px;margin-top:0">
				<tbody>
					<tr>
						<th scope="row">
							<label for="ak-import-post-batch"><?php esc_html_e( 'Posts per batch', 'asrekhodro' ); ?></label>
						</th>
						<td>
							<input
								type="number"
								id="ak-import-post-batch"
								class="small-text"
								min="1"
								max="<?php echo esc_attr( (string) $max_batch ); ?>"
								step="1"
								value="<?php echo esc_attr( (string) $post_batch ); ?>"
								<?php disabled( ! $ready ); ?>
							/>
							<p class="description">
								<?php
								printf(
									/* translators: 1: default batch size, 2: maximum batch size */
									esc_html__( 'Number of posts imported per AJAX step. Default %1$d; max %2$d. Higher values are faster but need more server memory and time per step.', 'asrekhodro' ),
									(int) AsreKhodro_Import_Session::default_post_batch_size(),
									(int) $max_batch
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Reset before import', 'asrekhodro' ); ?></th>
						<td>
							<p class="description" style="margin-top:0">
								<?php esc_html_e( 'Optional. Check only the types you want cleared before import (fast bulk SQL). Leave all unchecked to update/add without deleting existing WordPress data. If you reset Posts, Comments/Post categories/Tags are skipped automatically.', 'asrekhodro' ); ?>
							</p>
							<fieldset class="ak-import-reset-fieldset" <?php disabled( ! $ready ); ?>>
								<?php foreach ( AsreKhodro_Import_Reset::get_types() as $type_id => $type_info ) : ?>
									<?php
									$export_count = (int) ( $totals[ $type_info['totals_key'] ] ?? 0 );
									$input_id     = 'ak-import-reset-' . $type_id;
									?>
									<label for="<?php echo esc_attr( $input_id ); ?>" style="display:inline-block;margin:0 16px 8px 0">
										<input
											type="checkbox"
											class="ak-import-reset"
											id="<?php echo esc_attr( $input_id ); ?>"
											name="reset[<?php echo esc_attr( $type_id ); ?>]"
											value="1"
											<?php disabled( $export_count <= 0 ); ?>
											<?php echo $export_count <= 0 ? ' data-export-empty="1"' : ''; ?>
										/>
										<?php
										echo esc_html(
											sprintf(
												/* translators: 1: content type label, 2: export item count */
												__( 'Reset %1$s (%2$d in export)', 'asrekhodro' ),
												$type_info['label'],
												$export_count
											)
										);
										?>
										<?php if ( $export_count <= 0 ) : ?>
											<span class="description"><?php esc_html_e( '— export empty, skipped', 'asrekhodro' ); ?></span>
										<?php endif; ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
				</tbody>
			</table>

			<p style="margin-top:20px">
				<button type="button" class="button button-primary" id="ak-import-start" <?php disabled( ! $ready ); ?>>
					Run Import
				</button>
				<button type="button" class="button" id="ak-import-cancel" style="display:none;margin-left:8px">
					<?php esc_html_e( 'Cancel Import', 'asrekhodro' ); ?>
				</button>
			</p>

			<h2 style="margin-top:28px"><?php esc_html_e( 'Re-import post content only', 'asrekhodro' ); ?></h2>
			<p>
				<?php esc_html_e( 'Reads posts/posts-*.json and rewrites post_content only for existing posts that have embed markup in JSON (iframe, script, aparat, object/embed). Skips posts without embed tags or not found in WordPress. Preserves ak-gallery blocks. Does not strip iframe/script on save.', 'asrekhodro' ); ?>
			</p>
			<p style="margin-top:12px">
				<button type="button" class="button button-secondary" id="ak-content-reimport-start" <?php disabled( ! $posts_ready ); ?>>
					<?php esc_html_e( 'Re-import Post Content', 'asrekhodro' ); ?>
				</button>
				<button type="button" class="button" id="ak-content-reimport-cancel" style="display:none;margin-left:8px">
					<?php esc_html_e( 'Cancel Content Re-import', 'asrekhodro' ); ?>
				</button>
			</p>
			<?php if ( ! $posts_ready ) : ?>
				<p class="description"><?php esc_html_e( 'Upload post JSON chunks to the import folder first (posts/posts-*.json or posts.json).', 'asrekhodro' ); ?></p>
			<?php endif; ?>

			<div id="ak-import-progress" style="display:none;max-width:900px;margin-top:20px">
				<h2><?php esc_html_e( 'Import progress', 'asrekhodro' ); ?></h2>
				<p id="ak-import-status-text"><?php esc_html_e( 'Waiting…', 'asrekhodro' ); ?></p>

				<p>
					<strong id="ak-import-phase-label"><?php esc_html_e( 'Posts', 'asrekhodro' ); ?></strong>
					<span id="ak-import-phase-counter" style="float:right">0 / 0</span>
				</p>
				<div style="background:#f0f0f1;border-radius:3px;height:18px;overflow:hidden;margin-bottom:16px;position:relative">
					<div id="ak-import-phase-bar" style="background:#2271b1;height:18px;width:0;min-width:0;transition:width .2s"></div>
				</div>

				<p>
					<strong><?php esc_html_e( 'Overall', 'asrekhodro' ); ?></strong>
					<span id="ak-import-overall-counter" style="float:right">0 / 0</span>
				</p>
				<div style="background:#f0f0f1;border-radius:3px;height:12px;overflow:hidden;margin-bottom:16px;position:relative">
					<div id="ak-import-overall-bar" style="background:#72aee6;height:12px;width:0;min-width:0;transition:width .2s"></div>
				</div>

				<p>
					<?php esc_html_e( 'Posts imported:', 'asrekhodro' ); ?> <strong id="ak-import-count-posts">0</strong>
					&nbsp;|&nbsp;
					<?php esc_html_e( 'Content updated:', 'asrekhodro' ); ?> <strong id="ak-import-count-content-updated">0</strong>
					&nbsp;|&nbsp;
					<?php esc_html_e( 'Not found:', 'asrekhodro' ); ?> <strong id="ak-import-count-content-skipped">0</strong>
					&nbsp;|&nbsp;
					<?php esc_html_e( 'No embed in JSON:', 'asrekhodro' ); ?> <strong id="ak-import-count-content-no-embed">0</strong>
					&nbsp;|&nbsp;
					<?php esc_html_e( 'Ads imported:', 'asrekhodro' ); ?> <strong id="ak-import-count-ads">0</strong>
					&nbsp;|&nbsp;
					<?php esc_html_e( 'Comments imported:', 'asrekhodro' ); ?> <strong id="ak-import-count-comments">0</strong>
					&nbsp;|&nbsp;
					<?php esc_html_e( 'External media:', 'asrekhodro' ); ?> <strong id="ak-import-count-media">0</strong>
				</p>

				<label for="ak-import-log"><strong><?php esc_html_e( 'Recent log', 'asrekhodro' ); ?></strong></label>
				<pre id="ak-import-log" style="background:#fff;border:1px solid #ccd0d4;padding:12px;max-height:220px;overflow:auto;margin-top:8px"></pre>
			</div>

			<div id="ak-import-result" style="display:none;margin-top:20px;max-width:900px">
				<h2><?php esc_html_e( 'Import result', 'asrekhodro' ); ?></h2>
				<pre style="background:#fff;border:1px solid #ccd0d4;padding:12px;overflow:auto"></pre>
			</div>

			<h2>How to export again</h2>
			<pre style="background:#fff;border:1px solid #ccd0d4;padding:12px;max-width:900px;overflow:auto">cd d:\prj-lenovo-shakhes\asrekhodro-1405\dev\sql-server-exporter
npm run export:sample</pre>
		</div>
		<?php
	}
}
