<?php

namespace AsreKhodro\Theme\CdnServer\Admin;

use AsreKhodro\Theme\AuthorAccess;
use AsreKhodro\Theme\CdnServer\Config;
use AsreKhodro\Theme\CdnServer\ImageOptimize;
use AsreKhodro\Theme\CdnServer\MediaCdnMigrate;
use AsreKhodro\Theme\CdnServer\Watermark;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI: migrate media library files to CDN (like news slug repair).
 */
final class MediaCdnMigrateAdmin {

	private const MENU_SLUG = 'asrekhodro-media-cdn-migrate';
	private const NONCE     = 'ak_media_cdn_migrate';
	private const BATCH_MAX = 5;

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'register_menu' ), 103 );
		add_action( 'wp_ajax_ak_media_cdn_migrate_batch', array( self::class, 'handle_ajax_batch' ) );
	}

	public static function register_menu(): void {
		add_submenu_page(
			'asrekhodro-settings',
			__( 'انتقال رسانه به CDN', 'asrekhodro' ),
			__( 'انتقال رسانه به CDN', 'asrekhodro' ),
			AuthorAccess::THEME_SETTINGS_CAP,
			self::MENU_SLUG,
			array( self::class, 'render_page' )
		);
	}

	public static function handle_ajax_batch(): void {
		if ( ! AuthorAccess::can_manage_theme_settings() ) {
			wp_send_json_error(
				array( 'message' => __( 'دسترسی کافی ندارید.', 'asrekhodro' ) ),
				403
			);
		}

		check_ajax_referer( self::NONCE, 'nonce' );

		if ( ! Config::is_configured() ) {
			wp_send_json_error(
				array( 'message' => __( 'سرور CDN پیکربندی/فعال نیست.', 'asrekhodro' ) ),
				400
			);
		}

		$offset  = isset( $_POST['offset'] ) ? max( 0, (int) $_POST['offset'] ) : 0;
		$limit   = isset( $_POST['limit'] ) ? max( 1, min( self::BATCH_MAX, (int) $_POST['limit'] ) ) : 1;
		$dry_run = ! empty( $_POST['dry_run'] );

		@set_time_limit( 180 );
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}

		wp_send_json_success( MediaCdnMigrate::process_batch( $offset, $limit, $dry_run ) );
	}

	public static function render_page(): void {
		if ( ! AuthorAccess::can_manage_theme_settings() ) {
			return;
		}

		$total      = MediaCdnMigrate::count_attachments();
		$configured = Config::is_configured();
		$wm_on      = Watermark::settings() !== null;
		$opt_on     = ImageOptimize::settings() !== null;
		$opt        = ImageOptimize::settings();
		$cdn_base   = Config::get_public_base_url();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'انتقال رسانه به CDN', 'asrekhodro' ) . '</h1>';

		echo '<div class="notice notice-info" style="padding:12px 16px;">';
		echo '<p>' . esc_html__( 'رسانه‌های کتابخانه از جدیدترین به قدیمی‌ترین بررسی می‌شوند. اگر روی سرور CDN نباشند، آپلود می‌شوند و بعد از تأیید دسترسی عمومی، آدرس‌ها بازنویسی می‌شوند.', 'asrekhodro' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'ایمنی لایو:', 'asrekhodro' ) . '</strong> ' . esc_html__( 'فایل محلی حذف نمی‌شود. بعد از انتقال موفق، نام فایل محلی با پسوند -move2cdn علامت می‌خورد. مهاجرت‌های ناقص قبلی در اجرای بعدی تعمیر می‌شوند (بدون آپلود مجدد). فقط بعد از verify موفق URL عمومی، دیتابیس تغییر می‌کند.', 'asrekhodro' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'بهینه‌سازی تصویر:', 'asrekhodro' ) . '</strong> ';
		if ( $opt_on && is_array( $opt ) ) {
			echo sprintf(
				/* translators: %s: max width */
				esc_html__( 'فعال است — حداکثر پهنا %s پیکسل، سپس فشرده‌سازی، بعد واترمارک و آپلود.', 'asrekhodro' ),
				esc_html( number_format_i18n( (int) $opt['max_width'] ) )
			);
		} else {
			echo esc_html__( 'غیرفعال است.', 'asrekhodro' );
		}
		echo '</p>';
		echo '<p><strong>' . esc_html__( 'واترمارک:', 'asrekhodro' ) . '</strong> ';
		echo $wm_on
			? esc_html__( 'فعال است و روی تصاویر قابل‌پشتیبانی هنگام آپلود اعمال می‌شود.', 'asrekhodro' )
			: esc_html__( 'غیرفعال است (بدون واترمارک آپلود می‌شود).', 'asrekhodro' );
		echo '</p>';
		if ( $cdn_base !== '' ) {
			echo '<p><strong>' . esc_html__( 'پایه CDN:', 'asrekhodro' ) . '</strong> <code>' . esc_html( $cdn_base ) . '</code></p>';
		}
		echo '</div>';

		if ( ! $configured ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'CDN فعال/پیکربندی نشده است. ابتدا تنظیمات سرور CDN را کامل کنید.', 'asrekhodro' ) . '</p></div>';
		}

		echo '<div class="ak-media-cdn-migrate" id="ak-media-cdn-migrate" data-total="' . esc_attr( (string) $total ) . '" data-configured="' . ( $configured ? '1' : '0' ) . '">';
		echo '<p class="ak-media-cdn-migrate__summary">' . sprintf(
			/* translators: %s: count */
			esc_html__( 'تعداد رسانه‌های قابل بررسی: %s', 'asrekhodro' ),
			esc_html( number_format_i18n( $total ) )
		) . '</p>';

		echo '<p class="ak-media-cdn-migrate__batch">';
		echo '<label for="ak-media-cdn-migrate-batch">' . sprintf(
			/* translators: %s: max batch */
			esc_html__( 'تعداد در هر درخواست (۱ تا %s — برای لایو ۱ پیشنهاد می‌شود):', 'asrekhodro' ),
			esc_html( number_format_i18n( self::BATCH_MAX ) )
		) . '</label> ';
		echo '<input type="number" id="ak-media-cdn-migrate-batch" min="1" max="' . esc_attr( (string) self::BATCH_MAX ) . '" step="1" value="1" />';
		echo '</p>';

		echo '<p class="ak-media-cdn-migrate__batch">';
		echo '<label for="ak-media-cdn-migrate-max-total">' . esc_html__( 'تعداد کل انتقال (خالی = همه):', 'asrekhodro' ) . '</label> ';
		echo '<input type="number" id="ak-media-cdn-migrate-max-total" min="1" step="1" value="" placeholder="' . esc_attr__( 'همه', 'asrekhodro' ) . '" />';
		echo '</p>';

		echo '<p class="ak-media-cdn-migrate__batch">';
		echo '<label for="ak-media-cdn-migrate-offset">' . esc_html__( 'شروع از ردیف (اختیاری):', 'asrekhodro' ) . '</label> ';
		echo '<input type="number" id="ak-media-cdn-migrate-offset" min="0" step="1" value="0" />';
		echo '</p>';

		echo '<p class="ak-media-cdn-migrate__batch">';
		echo '<label><input type="checkbox" id="ak-media-cdn-migrate-dry" checked /> ' . esc_html__( 'حالت آزمایشی (Dry-run) — بدون آپلود و بدون تغییر دیتابیس', 'asrekhodro' ) . '</label>';
		echo '</p>';

		echo '<p class="ak-media-cdn-migrate__actions">';
		echo '<button type="button" class="button button-primary" id="ak-media-cdn-migrate-start"' . disabled( ! $configured, true, false ) . '>' . esc_html__( 'شروع از اول', 'asrekhodro' ) . '</button> ';
		echo '<button type="button" class="button" id="ak-media-cdn-migrate-from-offset"' . disabled( ! $configured, true, false ) . '>' . esc_html__( 'ادامه از ردیف', 'asrekhodro' ) . '</button> ';
		echo '<button type="button" class="button" id="ak-media-cdn-migrate-resume" disabled>' . esc_html__( 'ادامه ذخیره‌شده', 'asrekhodro' ) . '</button> ';
		echo '<button type="button" class="button" id="ak-media-cdn-migrate-cancel" disabled>' . esc_html__( 'توقف', 'asrekhodro' ) . '</button>';
		echo '</p>';
		echo '<p class="ak-media-cdn-migrate__resume-hint" id="ak-media-cdn-migrate-resume-hint"></p>';

		echo '<div class="ak-media-cdn-migrate__progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">';
		echo '<span id="ak-media-cdn-migrate-bar"></span>';
		echo '</div>';
		echo '<p class="ak-media-cdn-migrate__percent" id="ak-media-cdn-migrate-percent">0%</p>';
		echo '<p class="ak-media-cdn-migrate__status" id="ak-media-cdn-migrate-status">' . esc_html__( 'آماده اجرا. ابتدا Dry-run را تست کنید.', 'asrekhodro' ) . '</p>';

		echo '<ul class="ak-media-cdn-migrate__stats">';
		echo '<li>' . esc_html__( 'بررسی‌شده:', 'asrekhodro' ) . ' <strong id="ak-media-cdn-migrate-checked">0</strong></li>';
		echo '<li>' . esc_html__( 'آپلود/نیازمند آپلود:', 'asrekhodro' ) . ' <strong id="ak-media-cdn-migrate-uploaded">0</strong></li>';
		echo '<li>' . esc_html__( 'بازنویسی‌شده:', 'asrekhodro' ) . ' <strong id="ak-media-cdn-migrate-rewritten">0</strong></li>';
		echo '<li>' . esc_html__( 'ردشده:', 'asrekhodro' ) . ' <strong id="ak-media-cdn-migrate-skipped">0</strong></li>';
		echo '<li>' . esc_html__( 'خطا:', 'asrekhodro' ) . ' <strong id="ak-media-cdn-migrate-errors">0</strong></li>';
		echo '</ul>';
		echo '<pre class="ak-media-cdn-migrate__log" id="ak-media-cdn-migrate-log" aria-live="polite"></pre>';
		echo '</div>';

		self::print_interface_assets();
		echo '</div>';
	}

	private static function print_interface_assets(): void {
		$config = array(
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( self::NONCE ),
			'action'       => 'ak_media_cdn_migrate_batch',
			'storageKey'   => 'ak_media_cdn_migrate_progress',
			'defaultBatch' => 1,
			'batchMax'     => self::BATCH_MAX,
			'messages'     => array(
				'confirmStart'  => __( 'از ابتدا شروع شود؟ اگر Dry-run خاموش باشد، فایل‌ها واقعاً به CDN می‌روند و آدرس‌ها عوض می‌شوند.', 'asrekhodro' ),
				'confirmResume' => __( 'از آخرین نقطه ذخیره‌شده ادامه داده شود؟', 'asrekhodro' ),
				'confirmOffset' => __( 'از ردیف واردشده ادامه داده شود؟', 'asrekhodro' ),
				'starting'      => __( 'شروع…', 'asrekhodro' ),
				'resuming'      => __( 'ادامه…', 'asrekhodro' ),
				'running'       => __( 'در حال اجرا…', 'asrekhodro' ),
				'stopped'       => __( 'متوقف شد. می‌توانید ادامه دهید.', 'asrekhodro' ),
				'done'          => __( 'تمام شد.', 'asrekhodro' ),
				'error'         => __( 'خطا در اجرا. ادامه ذخیره‌شده را امتحان کنید.', 'asrekhodro' ),
				'notConfigured' => __( 'CDN پیکربندی نشده است.', 'asrekhodro' ),
				'invalidBatch'    => sprintf(
					/* translators: %s: max */
					__( 'تعداد هر درخواست باید بین ۱ تا %s باشد.', 'asrekhodro' ),
					number_format_i18n( self::BATCH_MAX )
				),
				'invalidMaxTotal' => __( 'تعداد کل انتقال باید خالی یا عددی بزرگ‌تر از صفر باشد.', 'asrekhodro' ),
				'invalidOffset'   => __( 'ردیف شروع نامعتبر است.', 'asrekhodro' ),
				'resumeHint'      => __( 'آخرین نقطه: %s از %s', 'asrekhodro' ),
				'noResume'        => __( 'نقطه ذخیره‌شده‌ای نیست.', 'asrekhodro' ),
				'limitReached'    => __( 'به سقف تعداد کل انتقال رسید.', 'asrekhodro' ),
			),
		);
		?>
		<style>
			.ak-media-cdn-migrate {
				max-width: 920px;
				background: #fff;
				border: 1px solid #dcdcde;
				border-radius: 8px;
				padding: 16px;
			}
			.ak-media-cdn-migrate__batch {
				display: flex;
				align-items: center;
				gap: 8px;
				flex-wrap: wrap;
			}
			.ak-media-cdn-migrate__batch input[type="number"] { width: 110px; }
			.ak-media-cdn-migrate__resume-hint { color: #646970; min-height: 1.4em; }
			.ak-media-cdn-migrate__progress {
				position: relative;
				height: 18px;
				background: #f0f0f1;
				border-radius: 999px;
				overflow: hidden;
			}
			.ak-media-cdn-migrate__progress span {
				display: block;
				width: 0;
				height: 100%;
				background: #2271b1;
				transition: width 160ms ease;
			}
			.ak-media-cdn-migrate__percent { font-weight: 600; margin: 8px 0 0; }
			.ak-media-cdn-migrate__stats {
				display: grid;
				grid-template-columns: repeat(5, minmax(0, 1fr));
				gap: 8px;
				margin: 16px 0;
				list-style: none;
				padding: 0;
			}
			.ak-media-cdn-migrate__stats li {
				margin: 0;
				padding: 10px;
				background: #f6f7f7;
				border-radius: 6px;
			}
			.ak-media-cdn-migrate__log {
				max-height: 420px;
				overflow: auto;
				margin: 0;
				padding: 12px;
				background: #1d2327;
				color: #f0f0f1;
				border-radius: 6px;
				white-space: pre-wrap;
				font-size: 12px;
				line-height: 1.55;
			}
			@media (max-width: 782px) {
				.ak-media-cdn-migrate__stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
			}
		</style>
		<script>
		(function () {
			var config = <?php echo wp_json_encode( $config ); ?>;
			var root = document.getElementById('ak-media-cdn-migrate');
			if (!root) return;

			var startButton = document.getElementById('ak-media-cdn-migrate-start');
			var fromOffsetButton = document.getElementById('ak-media-cdn-migrate-from-offset');
			var resumeButton = document.getElementById('ak-media-cdn-migrate-resume');
			var cancelButton = document.getElementById('ak-media-cdn-migrate-cancel');
			var batchInput = document.getElementById('ak-media-cdn-migrate-batch');
			var maxTotalInput = document.getElementById('ak-media-cdn-migrate-max-total');
			var offsetInput = document.getElementById('ak-media-cdn-migrate-offset');
			var dryInput = document.getElementById('ak-media-cdn-migrate-dry');
			var resumeHint = document.getElementById('ak-media-cdn-migrate-resume-hint');
			var progress = root.querySelector('.ak-media-cdn-migrate__progress');
			var bar = document.getElementById('ak-media-cdn-migrate-bar');
			var percentEl = document.getElementById('ak-media-cdn-migrate-percent');
			var status = document.getElementById('ak-media-cdn-migrate-status');
			var log = document.getElementById('ak-media-cdn-migrate-log');
			var checkedEl = document.getElementById('ak-media-cdn-migrate-checked');
			var uploadedEl = document.getElementById('ak-media-cdn-migrate-uploaded');
			var rewrittenEl = document.getElementById('ak-media-cdn-migrate-rewritten');
			var skippedEl = document.getElementById('ak-media-cdn-migrate-skipped');
			var errorsEl = document.getElementById('ak-media-cdn-migrate-errors');

			var stopped = false;
			var totals = { checked: 0, uploaded: 0, rewritten: 0, skipped: 0, errors: 0 };
			var currentOffset = 0;
			var startOffset = 0;
			var maxTotal = 0;
			var runChecked = 0;
			var totalCount = Number(root.dataset.total || 0);
			var configured = root.dataset.configured === '1';

			function readBatchSize() {
				var value = parseInt(batchInput.value, 10);
				if (!value || value < 1 || value > config.batchMax) return 0;
				return value;
			}

			function readMaxTotal() {
				var raw = String(maxTotalInput.value || '').trim();
				if (raw === '') return 0;
				var value = parseInt(raw, 10);
				if (!value || value < 1) return -1;
				return value;
			}

			function readOffset() {
				var value = parseInt(offsetInput.value, 10);
				if (isNaN(value) || value < 0) return -1;
				if (totalCount > 0 && value > totalCount) return -1;
				return value;
			}

			function isDryRun() {
				return !!(dryInput && dryInput.checked);
			}

			function runGoalCount() {
				if (maxTotal > 0) return maxTotal;
				return Math.max(0, totalCount - startOffset);
			}

			function loadProgress() {
				try {
					var raw = window.localStorage.getItem(config.storageKey);
					if (!raw) return null;
					var data = JSON.parse(raw);
					return data && typeof data === 'object' ? data : null;
				} catch (e) {
					return null;
				}
			}

			function saveProgress(offset, done) {
				var payload = {
					offset: Number(offset || 0),
					total: Number(root.dataset.total || 0),
					checked: totals.checked,
					uploaded: totals.uploaded,
					rewritten: totals.rewritten,
					skipped: totals.skipped,
					errors: totals.errors,
					batch: readBatchSize() || config.defaultBatch,
					maxTotal: maxTotal > 0 ? maxTotal : 0,
					runChecked: runChecked,
					startOffset: startOffset,
					dryRun: isDryRun(),
					done: !!done,
					updatedAt: Date.now()
				};
				try {
					window.localStorage.setItem(config.storageKey, JSON.stringify(payload));
				} catch (e) {}
				offsetInput.value = String(payload.offset);
				refreshResumeUi();
			}

			function refreshResumeUi() {
				var saved = loadProgress();
				if (!saved || saved.done || !saved.offset || saved.offset <= 0) {
					resumeButton.disabled = true;
					resumeHint.textContent = '';
					return;
				}
				resumeButton.disabled = false;
				resumeHint.textContent = config.messages.resumeHint
					.replace('%s', String(saved.offset))
					.replace('%s', String(saved.total || totalCount));
			}

			function setRunning(isRunning) {
				startButton.disabled = isRunning || !configured;
				fromOffsetButton.disabled = isRunning || !configured;
				resumeButton.disabled = isRunning || !loadProgress() || (loadProgress() && loadProgress().done);
				cancelButton.disabled = !isRunning;
				batchInput.disabled = isRunning;
				maxTotalInput.disabled = isRunning;
				offsetInput.disabled = isRunning;
				if (dryInput) dryInput.disabled = isRunning;
				if (!isRunning) refreshResumeUi();
			}

			function updateProgressUi() {
				var goal = runGoalCount();
				var percent = goal > 0 ? Math.min(100, Math.round((runChecked / goal) * 100)) : 0;
				bar.style.width = percent + '%';
				progress.setAttribute('aria-valuenow', String(percent));
				percentEl.textContent = percent + '%';
				checkedEl.textContent = String(totals.checked);
				uploadedEl.textContent = String(totals.uploaded);
				rewrittenEl.textContent = String(totals.rewritten);
				skippedEl.textContent = String(totals.skipped);
				errorsEl.textContent = String(totals.errors);
			}

			function appendLog(line) {
				log.textContent += line + '\n';
				log.scrollTop = log.scrollHeight;
			}

			function formatItem(item) {
				var lines = [];
				var id = item.id || '?';
				var status = item.status || '';
				lines.push('[' + status + '] #' + id + ' ' + (item.title || ''));
				if (item.message) lines.push('  ' + item.message);
				if (item.current_url) lines.push('  old: ' + item.current_url);
				if (item.new_url) lines.push('  new: ' + item.new_url);
				if (item.optimize || item.optimize_done) lines.push('  optimize: yes');
				if (item.watermark || item.watermark_done) lines.push('  watermark: yes');
				if (item.local_renamed && item.local_renamed.length) {
					lines.push('  local renamed (' + item.local_renamed.length + '):');
					item.local_renamed.forEach(function (p) {
						lines.push('    ' + p);
					});
				}
				if (item.edit_link) lines.push('  media: ' + item.edit_link);
				if (item.usages && item.usages.length) {
					item.usages.forEach(function (u) {
						var bit = '  use(' + (u.type || '') + '): #' + u.id + ' ' + (u.title || '');
						if (u.link) bit += ' | ' + u.link;
						if (u.edit) bit += ' | edit: ' + u.edit;
						lines.push(bit);
					});
				} else {
					lines.push('  use: (no linked posts found)');
				}
				if (item.content_hits) {
					lines.push('  content rewrites: posts=' + (item.content_hits.posts || 0) + ' meta=' + (item.content_hits.meta || 0));
				}
				return lines.join('\n');
			}

			function runBatch(offset) {
				if (stopped) {
					status.textContent = config.messages.stopped;
					setRunning(false);
					return;
				}

				if (maxTotal > 0 && runChecked >= maxTotal) {
					status.textContent = config.messages.limitReached;
					updateProgressUi();
					saveProgress(offset, true);
					setRunning(false);
					return;
				}

				var batch = readBatchSize();
				if (!batch) {
					status.textContent = config.messages.invalidBatch;
					setRunning(false);
					return;
				}

				if (maxTotal > 0) {
					batch = Math.min(batch, maxTotal - runChecked);
				}

				status.textContent = config.messages.running + ' offset=' + offset;
				currentOffset = offset;

				var body = new FormData();
				body.append('action', config.action);
				body.append('nonce', config.nonce);
				body.append('offset', String(offset));
				body.append('limit', String(batch));
				if (isDryRun()) body.append('dry_run', '1');

				fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
					.then(function (res) {
						return res.text().then(function (text) {
							var json = null;
							try {
								json = text ? JSON.parse(text) : null;
							} catch (e) {
								var snippet = (text || '').replace(/\s+/g, ' ').slice(0, 180);
								throw new Error(
									'پاسخ سرور JSON نبود (احتمالاً timeout/خطای PHP). HTTP ' +
									res.status + (snippet ? ': ' + snippet : '')
								);
							}
							return json;
						});
					})
					.then(function (json) {
						if (!json || !json.success || !json.data) {
							throw new Error((json && json.data && json.data.message) || config.messages.error);
						}

						var data = json.data;
						var checkedNow = Number(data.checked || 0);
						totals.checked += checkedNow;
						totals.uploaded += Number(data.uploaded || 0);
						totals.rewritten += Number(data.rewritten || 0);
						totals.skipped += Number(data.skipped || 0);
						totals.errors += Number(data.errors || 0);
						runChecked += checkedNow;

						(data.items || []).forEach(function (item) {
							appendLog(formatItem(item));
							appendLog('---');
						});

						var next = Number(data.next_offset || (offset + batch));
						if (data.total) {
							totalCount = Number(data.total);
							root.dataset.total = String(totalCount);
						}

						var hitLimit = maxTotal > 0 && runChecked >= maxTotal;
						var finished = !!data.done || next >= totalCount || hitLimit;

						updateProgressUi();
						saveProgress(next, finished);

						if (finished) {
							status.textContent = hitLimit && !data.done && next < totalCount
								? config.messages.limitReached
								: config.messages.done;
							setRunning(false);
							return;
						}

						runBatch(next);
					})
					.catch(function (err) {
						appendLog('ERROR: ' + (err && err.message ? err.message : config.messages.error));
						status.textContent = config.messages.error;
						saveProgress(currentOffset, false);
						setRunning(false);
					});
			}

			function startFrom(offset, label) {
				if (!configured) {
					status.textContent = config.messages.notConfigured;
					return;
				}
				var batch = readBatchSize();
				if (!batch) {
					status.textContent = config.messages.invalidBatch;
					return;
				}
				var limit = readMaxTotal();
				if (limit < 0) {
					status.textContent = config.messages.invalidMaxTotal;
					return;
				}
				if (offset < 0) {
					status.textContent = config.messages.invalidOffset;
					return;
				}

				stopped = false;
				maxTotal = limit;
				startOffset = offset;
				runChecked = 0;
				totals = { checked: 0, uploaded: 0, rewritten: 0, skipped: 0, errors: 0 };
				log.textContent = '';
				updateProgressUi();
				setRunning(true);
				status.textContent = label;
				appendLog(
					label + ' offset=' + offset +
					', batch=' + batch +
					', max_total=' + (maxTotal > 0 ? maxTotal : 'all') +
					', dry_run=' + (isDryRun() ? '1' : '0')
				);
				runBatch(offset);
			}

			startButton.addEventListener('click', function () {
				if (!window.confirm(config.messages.confirmStart)) return;
				startFrom(0, config.messages.starting);
			});

			fromOffsetButton.addEventListener('click', function () {
				var offset = readOffset();
				if (offset < 0) {
					status.textContent = config.messages.invalidOffset;
					return;
				}
				if (!window.confirm(config.messages.confirmOffset)) return;
				startFrom(offset, config.messages.resuming);
			});

			resumeButton.addEventListener('click', function () {
				var saved = loadProgress();
				if (!saved || !saved.offset) {
					status.textContent = config.messages.noResume;
					return;
				}
				if (!window.confirm(config.messages.confirmResume)) return;
				if (typeof saved.dryRun === 'boolean' && dryInput) {
					dryInput.checked = !!saved.dryRun;
				}
				if (saved.batch) batchInput.value = String(saved.batch);
				if (saved.maxTotal) {
					maxTotalInput.value = String(saved.maxTotal);
					maxTotal = Number(saved.maxTotal);
				} else {
					var limit = readMaxTotal();
					if (limit < 0) {
						status.textContent = config.messages.invalidMaxTotal;
						return;
					}
					maxTotal = limit;
				}
				startOffset = Number(saved.startOffset || 0);
				runChecked = Number(saved.runChecked || 0);
				totals = {
					checked: Number(saved.checked || 0),
					uploaded: Number(saved.uploaded || 0),
					rewritten: Number(saved.rewritten || 0),
					skipped: Number(saved.skipped || 0),
					errors: Number(saved.errors || 0)
				};
				stopped = false;
				setRunning(true);
				status.textContent = config.messages.resuming;
				updateProgressUi();
				appendLog(config.messages.resuming + ' offset=' + saved.offset + ', max_total=' + (maxTotal > 0 ? maxTotal : 'all'));
				runBatch(Number(saved.offset || 0));
			});

			cancelButton.addEventListener('click', function () {
				stopped = true;
				status.textContent = config.messages.stopped;
			});

			refreshResumeUi();
		})();
		</script>
		<?php
	}
}
