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

	private const MENU_SLUG = 'asrekhodro-news-slug-repair';
	private const NONCE     = 'ak_news_slug_repair';

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
		$limit  = isset( $_POST['limit'] ) ? max( 1, min( 200, (int) $_POST['limit'] ) ) : 50;

		wp_send_json_success( NewsPermalinks::repair_imported_news_slugs_batch( $offset, $limit ) );
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
		echo '</div>';

		echo '<div class="ak-news-slug-repair" id="ak-news-slug-repair" data-total="' . esc_attr( (string) $total ) . '">';
		echo '<p class="ak-news-slug-repair__summary">' . sprintf(
			/* translators: %s: count */
			esc_html__( 'تعداد خبرهای ایمپورت‌شده قابل بررسی: %s', 'asrekhodro' ),
			esc_html( number_format_i18n( $total ) )
		) . '</p>';
		echo '<p class="ak-news-slug-repair__actions">';
		echo '<button type="button" class="button button-primary" id="ak-news-slug-repair-start">' . esc_html__( 'شروع اصلاح', 'asrekhodro' ) . '</button> ';
		echo '<button type="button" class="button" id="ak-news-slug-repair-cancel" disabled>' . esc_html__( 'توقف', 'asrekhodro' ) . '</button>';
		echo '</p>';
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

	/**
	 * @param array{checked: int, updated: int, skipped: int, errors: int, column_ok: bool} $result
	 */
	private static function render_result( array $result ): void {
		$class = ( (int) $result['errors'] ) > 0 ? 'notice-warning' : 'notice-success';

		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible" style="padding:12px 16px;">';
		echo '<p><strong>' . esc_html__( 'نتیجه اصلاح', 'asrekhodro' ) . '</strong></p>';
		echo '<ul style="list-style:disc;margin-inline-start:1.25em;">';
		echo '<li>' . sprintf(
			/* translators: %s: count */
			esc_html__( 'بررسی‌شده: %s', 'asrekhodro' ),
			esc_html( number_format_i18n( (int) $result['checked'] ) )
		) . '</li>';
		echo '<li>' . sprintf(
			/* translators: %s: count */
			esc_html__( 'اصلاح‌شده: %s', 'asrekhodro' ),
			esc_html( number_format_i18n( (int) $result['updated'] ) )
		) . '</li>';
		echo '<li>' . sprintf(
			/* translators: %s: count */
			esc_html__( 'بدون نیاز به تغییر: %s', 'asrekhodro' ),
			esc_html( number_format_i18n( (int) $result['skipped'] ) )
		) . '</li>';
		echo '<li>' . sprintf(
			/* translators: %s: count */
			esc_html__( 'خطا: %s', 'asrekhodro' ),
			esc_html( number_format_i18n( (int) $result['errors'] ) )
		) . '</li>';
		echo '<li>' . esc_html(
			! empty( $result['column_ok'] )
				? __( 'ستون post_name: آماده (حداقل ۱۰۲۴)', 'asrekhodro' )
				: __( 'ستون post_name: نتوانست به ۱۰۲۴ برسد — دستی در دیتابیس بررسی کنید', 'asrekhodro' )
		) . '</li>';
		echo '</ul>';
		echo '</div>';
	}

	private static function print_interface_assets(): void {
		$config = array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( self::NONCE ),
			'action'   => 'ak_news_slug_repair_batch',
			'batch'    => 50,
			'messages' => array(
				'confirm'  => __( 'اسلاگ خبرهای ایمپورت‌شده مرحله‌به‌مرحله از روی عنوان ساخته می‌شود. ادامه می‌دهید؟', 'asrekhodro' ),
				'starting' => __( 'شروع اصلاح...', 'asrekhodro' ),
				'running'  => __( 'در حال اجرا...', 'asrekhodro' ),
				'stopped'  => __( 'عملیات متوقف شد. می‌توانید بعداً دوباره ادامه دهید.', 'asrekhodro' ),
				'done'     => __( 'اصلاح اسلاگ‌ها کامل شد.', 'asrekhodro' ),
				'error'    => __( 'خطا در اجرای اصلاح. چند لحظه بعد دوباره تلاش کنید.', 'asrekhodro' ),
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
			var cancelButton = document.getElementById('ak-news-slug-repair-cancel');
			var progress = root.querySelector('.ak-news-slug-repair__progress');
			var bar = document.getElementById('ak-news-slug-repair-bar');
			var status = document.getElementById('ak-news-slug-repair-status');
			var log = document.getElementById('ak-news-slug-repair-log');
			var checkedEl = document.getElementById('ak-news-slug-repair-checked');
			var updatedEl = document.getElementById('ak-news-slug-repair-updated');
			var skippedEl = document.getElementById('ak-news-slug-repair-skipped');
			var errorsEl = document.getElementById('ak-news-slug-repair-errors');
			var stopped = false;
			var totals = { checked: 0, updated: 0, skipped: 0, errors: 0 };

			function setRunning(isRunning) {
				startButton.disabled = isRunning;
				cancelButton.disabled = !isRunning;
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
					setRunning(false);
					return;
				}

				var body = new URLSearchParams();
				body.append('action', config.action);
				body.append('nonce', config.nonce);
				body.append('offset', String(offset));
				body.append('limit', String(config.batch));

				fetch(config.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body: body.toString()
				})
					.then(function (response) { return response.json(); })
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
						setProgress(Number(data.next_offset || 0), Number(data.total || 0));

						appendLog(
							'Batch: checked=' + data.checked +
							', updated=' + data.updated +
							', skipped=' + data.skipped +
							', errors=' + data.errors
						);

						if (data.column_ok === false) {
							appendLog('ستون post_name هنوز به ۱۰۲۴ نرسیده؛ دیتابیس را دستی بررسی کنید.');
						}

						if (data.done) {
							status.textContent = config.messages.done;
							appendLog(config.messages.done);
							setRunning(false);
							return;
						}

						window.setTimeout(function () {
							requestBatch(Number(data.next_offset || 0));
						}, 150);
					})
					.catch(function () {
						status.textContent = config.messages.error;
						appendLog(config.messages.error);
						setRunning(false);
					});
			}

			startButton.addEventListener('click', function () {
				if (!window.confirm(config.messages.confirm)) {
					return;
				}
				stopped = false;
				totals = { checked: 0, updated: 0, skipped: 0, errors: 0 };
				updateStats();
				log.textContent = '';
				status.textContent = config.messages.starting;
				setProgress(0, Number(root.dataset.total || 0));
				setRunning(true);
				appendLog(config.messages.starting);
				requestBatch(0);
			});

			cancelButton.addEventListener('click', function () {
				stopped = true;
				cancelButton.disabled = true;
			});
		})();
		</script>
		<?php
	}
}
