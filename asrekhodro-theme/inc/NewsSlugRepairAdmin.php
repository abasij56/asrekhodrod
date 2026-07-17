<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin tool: rebuild truncated Persian news slugs from titles.
 * Lives under Theme Settings.
 */
final class NewsSlugRepairAdmin {

	private const MENU_SLUG  = 'asrekhodro-news-slug-repair';
	private const NONCE      = 'ak_news_slug_repair';
	private const BATCH_MAX  = 5000;

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'register_menu' ), 102 );
		add_action( 'wp_ajax_ak_news_slug_repair_batch', array( self::class, 'handle_ajax_batch' ) );
	}

	public static function register_menu(): void {
		add_submenu_page(
			'asrekhodro-settings',
			__( 'اصلاح اسلاگ اخبار', 'asrekhodro' ),
			__( 'اصلاح اسلاگ اخبار', 'asrekhodro' ),
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

		$offset = isset( $_POST['offset'] ) ? max( 0, (int) $_POST['offset'] ) : 0;
		$limit  = isset( $_POST['limit'] ) ? max( 1, min( self::BATCH_MAX, (int) $_POST['limit'] ) ) : 50;
		$force  = ! empty( $_POST['force'] );

		wp_send_json_success( NewsPermalinks::repair_imported_news_slugs_batch( $offset, $limit, $force ) );
	}

	public static function render_page(): void {
		if ( ! AuthorAccess::can_manage_theme_settings() ) {
			return;
		}

		$total = NewsPermalinks::count_imported_news_posts();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'اصلاح اسلاگ اخبار', 'asrekhodro' ) . '</h1>';

		echo '<div class="notice notice-info" style="padding:12px 16px;">';
		echo '<p>' . esc_html__( 'وردپرس به‌صورت پیش‌فرض اسلاگ‌های فارسی بلند را کوتاه می‌کند. این کار لینک‌های قدیمی گوگل (مثل /News/{id}/{عنوان-کامل}) را خراب می‌کند.', 'asrekhodro' ) . '</p>';
		echo '<p>' . esc_html__( 'با زدن دکمه زیر، برای همه خبرهای ایمپورت‌شده (دارای شناسه قدیمی)، اسلاگ کامل دوباره از روی عنوان ساخته و ذخیره می‌شود. مسیر _asrekhodro_legacy_path هم هم‌راستا می‌شود.', 'asrekhodro' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'توجه:', 'asrekhodro' ) . '</strong> ' . esc_html__( 'این عملیات اسلاگ ذخیره‌شده در دیتابیس را تغییر می‌دهد. قبل از اجرا از صحت بکاپ مطمئن شوید. خبرهای جدیدی که از این به بعد ذخیره می‌شوند دیگر truncate نمی‌شوند.', 'asrekhodro' ) . '</p>';
		echo '<p>' . esc_html__( 'اگر وسط کار قطع شد، از «ادامه» استفاده کنید تا از همان نقطه جلو برود. «شروع از اول» همه را دوباره بررسی می‌کند؛ موارد درست‌شده معمولاً سریع بدون تغییر رد می‌شوند.', 'asrekhodro' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'بازنویسی اجباری:', 'asrekhodro' ) . '</strong> ' . esc_html__( 'اسلاگ همه را بدون شرط از روی عنوان می‌نویسد (حتی اگر قبلاً درست باشد). برای عنوان‌های تکراری ممکن است اسلاگ یکسان ذخیره شود.', 'asrekhodro' ) . '</p>';
		echo '</div>';

		echo '<div class="ak-news-slug-repair" id="ak-news-slug-repair" data-total="' . esc_attr( (string) $total ) . '">';
		echo '<p class="ak-news-slug-repair__summary">' . sprintf(
			/* translators: %s: count */
			esc_html__( 'تعداد خبرهای ایمپورت‌شده قابل بررسی: %s', 'asrekhodro' ),
			esc_html( number_format_i18n( $total ) )
		) . '</p>';

		echo '<p class="ak-news-slug-repair__batch">';
		echo '<label for="ak-news-slug-repair-batch">' . sprintf(
			/* translators: %s: max batch size */
			esc_html__( 'تعداد در هر درخواست (۱ تا %s):', 'asrekhodro' ),
			esc_html( number_format_i18n( self::BATCH_MAX ) )
		) . '</label> ';
		echo '<input type="number" id="ak-news-slug-repair-batch" min="1" max="' . esc_attr( (string) self::BATCH_MAX ) . '" step="1" value="50" />';
		echo '</p>';
		echo '<p class="ak-news-slug-repair__batch">';
		echo '<label for="ak-news-slug-repair-offset">' . esc_html__( 'شروع از ردیف (اختیاری):', 'asrekhodro' ) . '</label> ';
		echo '<input type="number" id="ak-news-slug-repair-offset" min="0" step="1" value="0" />';
		echo '<span class="description">' . esc_html__( 'مثلاً اگر روی ۱۱۹۶۵۰ قطع شد، همان عدد را بگذار و «ادامه از ردیف» را بزن.', 'asrekhodro' ) . '</span>';
		echo '</p>';

		echo '<p class="ak-news-slug-repair__actions">';
		echo '<button type="button" class="button button-primary" id="ak-news-slug-repair-start">' . esc_html__( 'شروع از اول', 'asrekhodro' ) . '</button> ';
		echo '<button type="button" class="button button-primary" id="ak-news-slug-repair-force" style="background:#b32d2e;border-color:#b32d2e;">' . esc_html__( 'بازنویسی اجباری همه', 'asrekhodro' ) . '</button> ';
		echo '<button type="button" class="button" id="ak-news-slug-repair-from-offset">' . esc_html__( 'ادامه از ردیف', 'asrekhodro' ) . '</button> ';
		echo '<button type="button" class="button" id="ak-news-slug-repair-resume" disabled>' . esc_html__( 'ادامه ذخیره‌شده', 'asrekhodro' ) . '</button> ';
		echo '<button type="button" class="button" id="ak-news-slug-repair-cancel" disabled>' . esc_html__( 'توقف', 'asrekhodro' ) . '</button>';
		echo '</p>';
		echo '<p class="ak-news-slug-repair__resume-hint" id="ak-news-slug-repair-resume-hint"></p>';

		echo '<div class="ak-news-slug-repair__progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">';
		echo '<span id="ak-news-slug-repair-bar"></span>';
		echo '</div>';
		echo '<p class="ak-news-slug-repair__status" id="ak-news-slug-repair-status">' . esc_html__( 'آماده اجرا.', 'asrekhodro' ) . '</p>';
		echo '<ul class="ak-news-slug-repair__stats">';
		echo '<li>' . esc_html__( 'بررسی‌شده:', 'asrekhodro' ) . ' <strong id="ak-news-slug-repair-checked">0</strong></li>';
		echo '<li>' . esc_html__( 'اصلاح‌شده:', 'asrekhodro' ) . ' <strong id="ak-news-slug-repair-updated">0</strong></li>';
		echo '<li>' . esc_html__( 'بدون تغییر:', 'asrekhodro' ) . ' <strong id="ak-news-slug-repair-skipped">0</strong></li>';
		echo '<li>' . esc_html__( 'خطا:', 'asrekhodro' ) . ' <strong id="ak-news-slug-repair-errors">0</strong></li>';
		echo '</ul>';
		echo '<pre class="ak-news-slug-repair__log" id="ak-news-slug-repair-log" aria-live="polite"></pre>';
		echo '</div>';

		self::print_interface_assets();
		echo '</div>';
	}

	private static function print_interface_assets(): void {
		$config = array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( self::NONCE ),
			'action'      => 'ak_news_slug_repair_batch',
			'storageKey'  => 'ak_news_slug_repair_progress',
			'defaultBatch'=> 50,
			'batchMax'    => self::BATCH_MAX,
			'messages'    => array(
				'confirmStart'   => __( 'اسلاگ خبرهای ایمپورت‌شده از اول بررسی می‌شود. موارد درست‌شده معمولاً سریع رد می‌شوند. ادامه می‌دهید؟', 'asrekhodro' ),
				'confirmForce'   => __( 'بازنویسی اجباری: اسلاگ همه خبرها بدون شرط از روی عنوان نوشته می‌شود. ادامه می‌دهید؟', 'asrekhodro' ),
				'confirmResume'  => __( 'از آخرین نقطه ذخیره‌شده ادامه داده شود؟', 'asrekhodro' ),
				'confirmOffset'  => __( 'از ردیف واردشده ادامه داده شود؟', 'asrekhodro' ),
				'starting'       => __( 'شروع اصلاح...', 'asrekhodro' ),
				'forcing'        => __( 'بازنویسی اجباری...', 'asrekhodro' ),
				'resuming'       => __( 'ادامه از نقطه قبلی...', 'asrekhodro' ),
				'running'        => __( 'در حال اجرا...', 'asrekhodro' ),
				'stopped'        => __( 'عملیات متوقف شد. با «ادامه ذخیره‌شده» یا «ادامه از ردیف» جلو بروید.', 'asrekhodro' ),
				'done'           => __( 'اصلاح اسلاگ‌ها کامل شد.', 'asrekhodro' ),
				'error'          => __( 'خطا در اجرای اصلاح. با «ادامه ذخیره‌شده» یا «ادامه از ردیف» دوباره تلاش کنید.', 'asrekhodro' ),
				'invalidBatch'   => sprintf(
					/* translators: %s: max batch size */
					__( 'تعداد هر درخواست باید بین ۱ تا %s باشد.', 'asrekhodro' ),
					number_format_i18n( self::BATCH_MAX )
				),
				'invalidOffset'  => __( 'ردیف شروع نامعتبر است.', 'asrekhodro' ),
				'resumeHint'     => __( 'آخرین نقطه ذخیره‌شده: %s از %s', 'asrekhodro' ),
				'noResume'       => __( 'نقطه ذخیره‌شده‌ای برای ادامه وجود ندارد.', 'asrekhodro' ),
			),
		);
		?>
		<style>
			.ak-news-slug-repair {
				max-width: 760px;
				background: #fff;
				border: 1px solid #dcdcde;
				border-radius: 8px;
				padding: 16px;
			}
			.ak-news-slug-repair__batch {
				display: flex;
				align-items: center;
				gap: 8px;
				flex-wrap: wrap;
			}
			.ak-news-slug-repair__batch input {
				width: 110px;
			}
			.ak-news-slug-repair__batch .description {
				color: #646970;
			}
			.ak-news-slug-repair__resume-hint {
				color: #646970;
				min-height: 1.4em;
			}
			.ak-news-slug-repair__progress {
				position: relative;
				height: 18px;
				background: #f0f0f1;
				border-radius: 999px;
				overflow: hidden;
			}
			.ak-news-slug-repair__progress span {
				display: block;
				width: 0;
				height: 100%;
				background: #2271b1;
				transition: width 160ms ease;
			}
			.ak-news-slug-repair__stats {
				display: grid;
				grid-template-columns: repeat(4, minmax(0, 1fr));
				gap: 8px;
				margin: 16px 0;
			}
			.ak-news-slug-repair__stats li {
				margin: 0;
				padding: 10px;
				background: #f6f7f7;
				border-radius: 6px;
			}
			.ak-news-slug-repair__log {
				min-height: 90px;
				max-height: 220px;
				overflow: auto;
				margin: 0;
				padding: 12px;
				background: #1d2327;
				color: #f0f0f1;
				border-radius: 6px;
				white-space: pre-wrap;
			}
		</style>
		<script>
		(function () {
			var config = <?php echo wp_json_encode( $config ); ?>;
			var root = document.getElementById('ak-news-slug-repair');
			if (!root) return;

			var startButton = document.getElementById('ak-news-slug-repair-start');
			var forceButton = document.getElementById('ak-news-slug-repair-force');
			var fromOffsetButton = document.getElementById('ak-news-slug-repair-from-offset');
			var resumeButton = document.getElementById('ak-news-slug-repair-resume');
			var cancelButton = document.getElementById('ak-news-slug-repair-cancel');
			var batchInput = document.getElementById('ak-news-slug-repair-batch');
			var offsetInput = document.getElementById('ak-news-slug-repair-offset');
			var resumeHint = document.getElementById('ak-news-slug-repair-resume-hint');
			var progress = root.querySelector('.ak-news-slug-repair__progress');
			var bar = document.getElementById('ak-news-slug-repair-bar');
			var status = document.getElementById('ak-news-slug-repair-status');
			var log = document.getElementById('ak-news-slug-repair-log');
			var checkedEl = document.getElementById('ak-news-slug-repair-checked');
			var updatedEl = document.getElementById('ak-news-slug-repair-updated');
			var skippedEl = document.getElementById('ak-news-slug-repair-skipped');
			var errorsEl = document.getElementById('ak-news-slug-repair-errors');
			var stopped = false;
			var forceMode = false;
			var totals = { checked: 0, updated: 0, skipped: 0, errors: 0 };
			var currentOffset = 0;
			var totalCount = Number(root.dataset.total || 0);

			function readBatchSize() {
				var value = parseInt(batchInput.value, 10);
				if (!value || value < 1 || value > config.batchMax) {
					return 0;
				}
				return value;
			}

			function readOffset() {
				var value = parseInt(offsetInput.value, 10);
				if (isNaN(value) || value < 0) {
					return -1;
				}
				if (totalCount > 0 && value > totalCount) {
					return -1;
				}
				return value;
			}

			function loadProgress() {
				try {
					var raw = window.localStorage.getItem(config.storageKey);
					if (!raw) return null;
					var data = JSON.parse(raw);
					if (!data || typeof data !== 'object') return null;
					return data;
				} catch (e) {
					return null;
				}
			}

			function saveProgress(offset, done) {
				var payload = {
					offset: Number(offset || 0),
					total: Number(root.dataset.total || 0),
					checked: totals.checked,
					updated: totals.updated,
					skipped: totals.skipped,
					errors: totals.errors,
					batch: readBatchSize() || config.defaultBatch,
					force: !!forceMode,
					done: !!done,
					updatedAt: Date.now()
				};
				try {
					window.localStorage.setItem(config.storageKey, JSON.stringify(payload));
				} catch (e) {}
				offsetInput.value = String(payload.offset);
				refreshResumeUi();
			}

			function clearProgress() {
				try {
					window.localStorage.removeItem(config.storageKey);
				} catch (e) {}
				refreshResumeUi();
			}

			function refreshResumeUi() {
				var saved = loadProgress();
				if (!saved || saved.done || !saved.offset || saved.offset <= 0) {
					resumeButton.disabled = true;
					resumeHint.textContent = config.messages.noResume;
					return;
				}
				resumeButton.disabled = false;
				resumeHint.textContent = config.messages.resumeHint
					.replace('%s', String(saved.offset))
					.replace('%s', String(saved.total || root.dataset.total || 0));
			}

			function setRunning(isRunning) {
				startButton.disabled = isRunning;
				forceButton.disabled = isRunning;
				fromOffsetButton.disabled = isRunning;
				resumeButton.disabled = isRunning || !(loadProgress() && loadProgress().offset > 0 && !loadProgress().done);
				cancelButton.disabled = !isRunning;
				batchInput.disabled = isRunning;
				offsetInput.disabled = isRunning;
				if (!isRunning) {
					refreshResumeUi();
				}
			}

			function appendLog(message) {
				var now = new Date().toLocaleTimeString();
				log.textContent += '[' + now + '] ' + message + "\n";
				log.scrollTop = log.scrollHeight;
			}

			function updateStats() {
				checkedEl.textContent = totals.checked;
				updatedEl.textContent = totals.updated;
				skippedEl.textContent = totals.skipped;
				errorsEl.textContent = totals.errors;
			}

			function setProgress(done, total) {
				var percent = total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 100;
				bar.style.width = percent + '%';
				progress.setAttribute('aria-valuenow', String(percent));
				status.textContent = config.messages.running + ' ' + percent + '% (' + done + '/' + total + ')';
			}

			function requestBatch(offset) {
				if (stopped) {
					status.textContent = config.messages.stopped;
					appendLog(config.messages.stopped);
					saveProgress(offset, false);
					setRunning(false);
					return;
				}

				var batch = readBatchSize();
				if (!batch) {
					status.textContent = config.messages.invalidBatch;
					appendLog(config.messages.invalidBatch);
					setRunning(false);
					return;
				}

				currentOffset = offset;
				var body = new URLSearchParams();
				body.append('action', config.action);
				body.append('nonce', config.nonce);
				body.append('offset', String(offset));
				body.append('limit', String(batch));
				body.append('force', forceMode ? '1' : '0');

				fetch(config.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body: body.toString()
				})
					.then(function (response) {
						if (!response.ok) {
							throw new Error('HTTP ' + response.status);
						}
						return response.json();
					})
					.then(function (payload) {
						if (!payload || !payload.success || !payload.data) {
							throw new Error('Invalid response');
						}

						var data = payload.data;
						totals.checked += Number(data.checked || 0);
						totals.updated += Number(data.updated || 0);
						totals.skipped += Number(data.skipped || 0);
						totals.errors += Number(data.errors || 0);
						updateStats();

						var nextOffset = Number(data.next_offset || 0);
						currentOffset = nextOffset;
						setProgress(nextOffset, Number(data.total || 0));
						saveProgress(nextOffset, !!data.done);

						appendLog(
							'Batch @' + offset +
							(forceMode ? ' [FORCE]' : '') +
							': checked=' + data.checked +
							', updated=' + data.updated +
							', skipped=' + data.skipped +
							', errors=' + data.errors +
							', next=' + nextOffset
						);

						if (data.column_ok === false) {
							appendLog('ستون post_name هنوز به ۱۰۲۴ نرسیده؛ دیتابیس را دستی بررسی کنید.');
						}

						if (data.done) {
							status.textContent = config.messages.done;
							appendLog(config.messages.done);
							clearProgress();
							setRunning(false);
							return;
						}

						window.setTimeout(function () {
							requestBatch(nextOffset);
						}, 150);
					})
					.catch(function (err) {
						status.textContent = config.messages.error;
						appendLog(config.messages.error + (err && err.message ? ' (' + err.message + ')' : ''));
						saveProgress(currentOffset, false);
						setRunning(false);
					});
			}

			function beginRun(fromOffset, resetTotals, confirmMessage, startMessage, force) {
				if (!window.confirm(confirmMessage)) {
					return;
				}
				var batch = readBatchSize();
				if (!batch) {
					window.alert(config.messages.invalidBatch);
					return;
				}

				stopped = false;
				forceMode = !!force;
				currentOffset = fromOffset;
				if (resetTotals) {
					totals = { checked: 0, updated: 0, skipped: 0, errors: 0 };
					log.textContent = '';
				}
				updateStats();
				status.textContent = startMessage;
				setProgress(fromOffset, Number(root.dataset.total || 0));
				setRunning(true);
				appendLog(startMessage + ' offset=' + fromOffset + ', batch=' + batch + (forceMode ? ', force=1' : ''));
				saveProgress(fromOffset, false);
				requestBatch(fromOffset);
			}

			startButton.addEventListener('click', function () {
				offsetInput.value = '0';
				beginRun(0, true, config.messages.confirmStart, config.messages.starting, false);
			});

			forceButton.addEventListener('click', function () {
				offsetInput.value = '0';
				beginRun(0, true, config.messages.confirmForce, config.messages.forcing, true);
			});

			fromOffsetButton.addEventListener('click', function () {
				var offset = readOffset();
				if (offset < 0) {
					window.alert(config.messages.invalidOffset);
					return;
				}
				var saved = loadProgress();
				var force = !!(saved && saved.force);
				beginRun(offset, true, config.messages.confirmOffset, config.messages.resuming, force);
			});

			resumeButton.addEventListener('click', function () {
				var saved = loadProgress();
				if (!saved || !saved.offset || saved.done) {
					window.alert(config.messages.noResume);
					refreshResumeUi();
					return;
				}
				if (saved.batch) {
					batchInput.value = String(saved.batch);
				}
				offsetInput.value = String(saved.offset || 0);
				totals = {
					checked: Number(saved.checked || 0),
					updated: Number(saved.updated || 0),
					skipped: Number(saved.skipped || 0),
					errors: Number(saved.errors || 0)
				};
				beginRun(Number(saved.offset || 0), false, config.messages.confirmResume, config.messages.resuming, !!saved.force);
			});

			cancelButton.addEventListener('click', function () {
				stopped = true;
				cancelButton.disabled = true;
			});

			refreshResumeUi();
			var saved = loadProgress();
			if (saved && saved.offset > 0 && !saved.done) {
				forceMode = !!saved.force;
				totals = {
					checked: Number(saved.checked || 0),
					updated: Number(saved.updated || 0),
					skipped: Number(saved.skipped || 0),
					errors: Number(saved.errors || 0)
				};
				updateStats();
				offsetInput.value = String(saved.offset || 0);
				if (saved.batch) {
					batchInput.value = String(saved.batch);
				}
				setProgress(Number(saved.offset || 0), Number(saved.total || root.dataset.total || 0));
				status.textContent = config.messages.stopped;
			}
		})();
		</script>
		<?php
	}
}
