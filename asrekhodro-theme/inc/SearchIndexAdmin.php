<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI: build search index in batches + enable/disable FULLTEXT search.
 * Does not modify wp_posts. Search stays on title-only until enabled.
 */
final class SearchIndexAdmin {

	private const MENU_SLUG = 'asrekhodro-search-index';
	private const NONCE     = 'ak_search_index';
	private const BATCH_MAX = 1000;

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'register_menu' ), 103 );
		add_action( 'wp_ajax_ak_search_index_batch', array( self::class, 'handle_ajax_batch' ) );
		add_action( 'wp_ajax_ak_search_index_toggle', array( self::class, 'handle_ajax_toggle' ) );
		add_action( 'admin_post_ak_search_index_install', array( self::class, 'handle_install' ) );
	}

	public static function register_menu(): void {
		add_submenu_page(
			'asrekhodro-settings',
			__( 'ایندکس جستجو', 'asrekhodro' ),
			__( 'ایندکس جستجو', 'asrekhodro' ),
			AuthorAccess::THEME_SETTINGS_CAP,
			self::MENU_SLUG,
			array( self::class, 'render_page' )
		);
	}

	public static function handle_install(): void {
		if ( ! AuthorAccess::can_manage_theme_settings() ) {
			wp_die( esc_html__( 'دسترسی کافی ندارید.', 'asrekhodro' ) );
		}
		check_admin_referer( self::NONCE );

		SearchIndex::install_table();
		update_option( SearchIndex::DB_VERSION_OPTION, SearchIndex::DB_VERSION, false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::MENU_SLUG,
					'installed' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function handle_ajax_batch(): void {
		if ( ! AuthorAccess::can_manage_theme_settings() ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی کافی ندارید.', 'asrekhodro' ) ), 403 );
		}

		check_ajax_referer( self::NONCE, 'nonce' );

		$after_id = isset( $_POST['after_id'] ) ? max( 0, (int) $_POST['after_id'] ) : 0;
		$limit    = isset( $_POST['limit'] ) ? max( 1, min( self::BATCH_MAX, (int) $_POST['limit'] ) ) : 200;

		$batch = SearchIndex::reindex_batch( $after_id, $limit );
		$batch['indexed_total'] = SearchIndex::count_indexed();
		$batch['enabled']       = SearchIndex::is_enabled();

		wp_send_json_success( $batch );
	}

	public static function handle_ajax_toggle(): void {
		if ( ! AuthorAccess::can_manage_theme_settings() ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی کافی ندارید.', 'asrekhodro' ) ), 403 );
		}

		check_ajax_referer( self::NONCE, 'nonce' );

		$enabled = ! empty( $_POST['enabled'] );
		if ( $enabled && ! SearchIndex::table_exists() ) {
			wp_send_json_error( array( 'message' => __( 'جدول ایندکس هنوز ساخته نشده است.', 'asrekhodro' ) ) );
		}

		SearchIndex::set_enabled( $enabled );

		wp_send_json_success(
			array(
				'enabled' => SearchIndex::is_enabled(),
				'message' => SearchIndex::is_enabled()
					? __( 'جستجوی FULLTEXT فعال شد.', 'asrekhodro' )
					: __( 'جستجو به حالت قبلی (فقط عنوان) برگشت.', 'asrekhodro' ),
			)
		);
	}

	public static function render_page(): void {
		if ( ! AuthorAccess::can_manage_theme_settings() ) {
			return;
		}

		SearchIndex::maybe_install();

		$table_ok      = SearchIndex::table_exists();
		$publish_total = SearchIndex::count_publish_posts();
		$indexed_total = SearchIndex::count_indexed();
		$enabled       = SearchIndex::is_enabled();
		$progress      = SearchIndex::get_progress();
		$coverage      = $publish_total > 0 ? min( 100, round( ( $indexed_total / $publish_total ) * 100, 1 ) ) : 0;

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'ایندکس جستجو', 'asrekhodro' ) . '</h1>';

		if ( isset( $_GET['installed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'جدول ایندکس آماده است (خالی). هنوز سرچ سایت عوض نشده.', 'asrekhodro' ) . '</p></div>';
		}

		echo '<div class="notice notice-info" style="padding:12px 16px;">';
		echo '<p>' . esc_html__( 'این ابزار یک جدول جدا برای جستجو می‌سازد و title + content + تگ/دسته را ایندکس می‌کند. جداول پست‌های وردپرس تغییر نمی‌کنند.', 'asrekhodro' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'مهم:', 'asrekhodro' ) . '</strong> ' . esc_html__( 'تا وقتی دکمه «فعال‌سازی جستجوی جدید» را نزنید، سرچ سایت همان حالت فعلی (فقط عنوان) می‌ماند.', 'asrekhodro' ) . '</p>';
		echo '<p>' . esc_html__( 'برای آرشیو بزرگ، ایندکس را با بچ‌های کوچک پر کنید. اگر قطع شد، از «ادامه» با همان after_id استفاده کنید.', 'asrekhodro' ) . '</p>';
		echo '</div>';

		echo '<div class="ak-search-index" id="ak-search-index"';
		echo ' data-total="' . esc_attr( (string) $publish_total ) . '"';
		echo ' data-indexed="' . esc_attr( (string) $indexed_total ) . '"';
		echo ' data-enabled="' . esc_attr( $enabled ? '1' : '0' ) . '"';
		echo ' data-after-id="' . esc_attr( (string) ( $progress['done'] ? 0 : $progress['after_id'] ) ) . '"';
		echo '>';

		echo '<table class="widefat striped" style="max-width:720px;margin-bottom:16px;">';
		echo '<tbody>';
		echo '<tr><th>' . esc_html__( 'جدول', 'asrekhodro' ) . '</th><td>' . esc_html( SearchIndex::table_name() ) . ' — ';
		echo $table_ok ? esc_html__( 'وجود دارد', 'asrekhodro' ) : esc_html__( 'وجود ندارد', 'asrekhodro' );
		echo '</td></tr>';
		echo '<tr><th>' . esc_html__( 'پست‌های publish قابل ایندکس', 'asrekhodro' ) . '</th><td id="ak-search-index-total">' . esc_html( number_format_i18n( $publish_total ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'ردیف ایندکس‌شده', 'asrekhodro' ) . '</th><td id="ak-search-index-indexed">' . esc_html( number_format_i18n( $indexed_total ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'پوشش', 'asrekhodro' ) . '</th><td id="ak-search-index-coverage">' . esc_html( (string) $coverage ) . '%</td></tr>';
		echo '<tr><th>' . esc_html__( 'وضعیت سرچ فرانت', 'asrekhodro' ) . '</th><td id="ak-search-index-enabled-label">';
		echo $enabled
			? '<strong style="color:#00a32a;">' . esc_html__( 'FULLTEXT فعال', 'asrekhodro' ) . '</strong>'
			: '<strong>' . esc_html__( 'فقط عنوان (قدیمی)', 'asrekhodro' ) . '</strong>';
		echo '</td></tr>';
		echo '</tbody></table>';

		if ( ! $table_ok ) {
			$install_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=ak_search_index_install' ),
				self::NONCE
			);
			echo '<p><a class="button button-primary" href="' . esc_url( $install_url ) . '">' . esc_html__( 'ساخت جدول خالی', 'asrekhodro' ) . '</a></p>';
		}

		echo '<p class="ak-search-index__batch">';
		echo '<label for="ak-search-index-batch">' . sprintf(
			/* translators: %s: max batch */
			esc_html__( 'تعداد در هر درخواست (۱ تا %s):', 'asrekhodro' ),
			esc_html( number_format_i18n( self::BATCH_MAX ) )
		) . '</label> ';
		echo '<input type="number" id="ak-search-index-batch" min="1" max="' . esc_attr( (string) self::BATCH_MAX ) . '" step="1" value="200" />';
		echo '</p>';
		echo '<p class="ak-search-index__batch">';
		echo '<label for="ak-search-index-after">' . esc_html__( 'ادامه بعد از post_id:', 'asrekhodro' ) . '</label> ';
		echo '<input type="number" id="ak-search-index-after" min="0" step="1" value="' . esc_attr( (string) ( $progress['done'] ? 0 : $progress['after_id'] ) ) . '" />';
		echo '<span class="description">' . esc_html__( '۰ = از اول. اگر قطع شد آخرین after_id را اینجا بگذارید.', 'asrekhodro' ) . '</span>';
		echo '</p>';

		echo '<p class="ak-search-index__actions">';
		echo '<button type="button" class="button button-primary" id="ak-search-index-start"' . disabled( ! $table_ok, true, false ) . '>' . esc_html__( 'شروع از اول', 'asrekhodro' ) . '</button> ';
		echo '<button type="button" class="button" id="ak-search-index-resume"' . disabled( ! $table_ok, true, false ) . '>' . esc_html__( 'ادامه', 'asrekhodro' ) . '</button> ';
		echo '<button type="button" class="button" id="ak-search-index-cancel" disabled>' . esc_html__( 'توقف', 'asrekhodro' ) . '</button> ';
		echo '<button type="button" class="button" id="ak-search-index-toggle"' . disabled( ! $table_ok, true, false ) . '>';
		echo $enabled ? esc_html__( 'خاموش کردن جستجوی جدید', 'asrekhodro' ) : esc_html__( 'فعال‌سازی جستجوی جدید', 'asrekhodro' );
		echo '</button>';
		echo '</p>';

		echo '<div class="ak-search-index__progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">';
		echo '<span id="ak-search-index-bar"></span>';
		echo '</div>';
		echo '<p class="ak-search-index__status" id="ak-search-index-status">' . esc_html__( 'آماده اجرا.', 'asrekhodro' ) . '</p>';
		echo '<ul class="ak-search-index__stats">';
		echo '<li>' . esc_html__( 'بررسی‌شده این اجرا:', 'asrekhodro' ) . ' <strong id="ak-search-index-checked">0</strong></li>';
		echo '<li>' . esc_html__( 'ایندکس‌شده این اجرا:', 'asrekhodro' ) . ' <strong id="ak-search-index-run-indexed">0</strong></li>';
		echo '<li>' . esc_html__( 'خطا:', 'asrekhodro' ) . ' <strong id="ak-search-index-errors">0</strong></li>';
		echo '<li>' . esc_html__( 'آخرین after_id:', 'asrekhodro' ) . ' <strong id="ak-search-index-last-id">0</strong></li>';
		echo '</ul>';
		echo '<pre class="ak-search-index__log" id="ak-search-index-log" aria-live="polite"></pre>';
		echo '</div>';

		self::print_interface_assets();
		echo '</div>';
	}

	private static function print_interface_assets(): void {
		$config = array(
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( self::NONCE ),
			'batchAction'  => 'ak_search_index_batch',
			'toggleAction' => 'ak_search_index_toggle',
			'storageKey'   => 'ak_search_index_progress',
			'defaultBatch' => 200,
			'batchMax'     => self::BATCH_MAX,
			'messages'     => array(
				'confirmStart'  => __( 'ایندکس از post_id=0 شروع شود؟ روی جداول پست‌ها نوشتن انجام نمی‌شود.', 'asrekhodro' ),
				'confirmResume' => __( 'از after_id فعلی ادامه داده شود؟', 'asrekhodro' ),
				'confirmOn'     => __( 'جستجوی سایت به FULLTEXT سوییچ شود؟ اگر پوشش ایندکس ناقص است، نتایج ناقص می‌شوند.', 'asrekhodro' ),
				'confirmOff'    => __( 'برگشت به جستجوی فقط‌عنوان؟', 'asrekhodro' ),
				'starting'      => __( 'شروع ایندکس...', 'asrekhodro' ),
				'running'       => __( 'در حال ایندکس...', 'asrekhodro' ),
				'stopped'       => __( 'متوقف شد. با «ادامه» از همان after_id جلو بروید.', 'asrekhodro' ),
				'done'          => __( 'ایندکس کامل شد. بعد از بررسی پوشش می‌توانید جستجوی جدید را فعال کنید.', 'asrekhodro' ),
				'error'         => __( 'خطا در ایندکس. با «ادامه» دوباره تلاش کنید.', 'asrekhodro' ),
				'invalidBatch'  => sprintf(
					/* translators: %s: max */
					__( 'تعداد هر درخواست باید بین ۱ تا %s باشد.', 'asrekhodro' ),
					number_format_i18n( self::BATCH_MAX )
				),
				'enabled'       => __( 'FULLTEXT فعال', 'asrekhodro' ),
				'disabled'      => __( 'فقط عنوان (قدیمی)', 'asrekhodro' ),
				'toggleOn'      => __( 'فعال‌سازی جستجوی جدید', 'asrekhodro' ),
				'toggleOff'     => __( 'خاموش کردن جستجوی جدید', 'asrekhodro' ),
			),
		);
		?>
		<style>
			.ak-search-index {
				max-width: 760px;
				background: #fff;
				border: 1px solid #dcdcde;
				border-radius: 8px;
				padding: 16px;
			}
			.ak-search-index__batch {
				display: flex;
				align-items: center;
				gap: 8px;
				flex-wrap: wrap;
			}
			.ak-search-index__batch input { width: 120px; }
			.ak-search-index__batch .description { color: #646970; }
			.ak-search-index__progress {
				position: relative;
				height: 18px;
				background: #f0f0f1;
				border-radius: 999px;
				overflow: hidden;
				margin-top: 12px;
			}
			.ak-search-index__progress span {
				display: block;
				width: 0;
				height: 100%;
				background: #2271b1;
				transition: width 160ms ease;
			}
			.ak-search-index__stats {
				display: grid;
				grid-template-columns: repeat(2, minmax(0, 1fr));
				gap: 8px;
				margin: 16px 0;
			}
			.ak-search-index__stats li {
				margin: 0;
				padding: 10px;
				background: #f6f7f7;
				border-radius: 6px;
			}
			.ak-search-index__log {
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
			var root = document.getElementById('ak-search-index');
			if (!root) return;

			var startButton = document.getElementById('ak-search-index-start');
			var resumeButton = document.getElementById('ak-search-index-resume');
			var cancelButton = document.getElementById('ak-search-index-cancel');
			var toggleButton = document.getElementById('ak-search-index-toggle');
			var batchInput = document.getElementById('ak-search-index-batch');
			var afterInput = document.getElementById('ak-search-index-after');
			var bar = document.getElementById('ak-search-index-bar');
			var status = document.getElementById('ak-search-index-status');
			var log = document.getElementById('ak-search-index-log');
			var checkedEl = document.getElementById('ak-search-index-checked');
			var runIndexedEl = document.getElementById('ak-search-index-run-indexed');
			var errorsEl = document.getElementById('ak-search-index-errors');
			var lastIdEl = document.getElementById('ak-search-index-last-id');
			var indexedEl = document.getElementById('ak-search-index-indexed');
			var coverageEl = document.getElementById('ak-search-index-coverage');
			var enabledLabel = document.getElementById('ak-search-index-enabled-label');
			var stopped = false;
			var totals = { checked: 0, indexed: 0, errors: 0 };
			var totalCount = Number(root.dataset.total || 0);
			var enabled = root.dataset.enabled === '1';

			function readBatchSize() {
				var value = parseInt(batchInput.value, 10);
				if (!value || value < 1 || value > config.batchMax) return 0;
				return value;
			}

			function readAfterId() {
				var value = parseInt(afterInput.value, 10);
				if (isNaN(value) || value < 0) return 0;
				return value;
			}

			function appendLog(line) {
				log.textContent += line + "\n";
				log.scrollTop = log.scrollHeight;
			}

			function setRunning(isRunning) {
				startButton.disabled = isRunning;
				resumeButton.disabled = isRunning;
				cancelButton.disabled = !isRunning;
				toggleButton.disabled = isRunning;
				batchInput.disabled = isRunning;
				afterInput.disabled = isRunning;
			}

			function updateProgress(afterId) {
				var pct = 0;
				if (totalCount > 0 && afterId > 0) {
					pct = Math.min(100, Math.round((totals.checked / totalCount) * 100));
				}
				bar.style.width = pct + '%';
				checkedEl.textContent = String(totals.checked);
				runIndexedEl.textContent = String(totals.indexed);
				errorsEl.textContent = String(totals.errors);
				lastIdEl.textContent = String(afterId);
				afterInput.value = String(afterId);
			}

			function updateCoverage(indexedTotal) {
				indexedEl.textContent = Number(indexedTotal || 0).toLocaleString('fa-IR');
				var cov = totalCount > 0 ? Math.min(100, Math.round((indexedTotal / totalCount) * 1000) / 10) : 0;
				coverageEl.textContent = cov + '%';
			}

			function setEnabledUi(isOn) {
				enabled = !!isOn;
				enabledLabel.innerHTML = isOn
					? '<strong style="color:#00a32a;">' + config.messages.enabled + '</strong>'
					: '<strong>' + config.messages.disabled + '</strong>';
				toggleButton.textContent = isOn ? config.messages.toggleOff : config.messages.toggleOn;
			}

			function runBatch(afterId) {
				if (stopped) {
					status.textContent = config.messages.stopped;
					setRunning(false);
					return;
				}

				var batch = readBatchSize();
				if (!batch) {
					status.textContent = config.messages.invalidBatch;
					setRunning(false);
					return;
				}

				status.textContent = config.messages.running;
				var body = new FormData();
				body.append('action', config.batchAction);
				body.append('nonce', config.nonce);
				body.append('after_id', String(afterId));
				body.append('limit', String(batch));

				fetch(config.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
					.then(function (r) { return r.json(); })
					.then(function (json) {
						if (!json || !json.success || !json.data) {
							status.textContent = config.messages.error;
							setRunning(false);
							appendLog('error: bad response');
							return;
						}
						var data = json.data;
						totals.checked += Number(data.checked || 0);
						totals.indexed += Number(data.indexed || 0);
						totals.errors += Number(data.errors || 0);
						var nextId = Number(data.next_after_id || afterId);
						updateProgress(nextId);
						updateCoverage(Number(data.indexed_total || 0));
						appendLog('after_id=' + nextId + ' checked=' + data.checked + ' indexed=' + data.indexed);

						if (data.done) {
							status.textContent = config.messages.done;
							setRunning(false);
							return;
						}
						runBatch(nextId);
					})
					.catch(function (err) {
						status.textContent = config.messages.error;
						setRunning(false);
						appendLog(String(err));
					});
			}

			startButton.addEventListener('click', function () {
				if (!window.confirm(config.messages.confirmStart)) return;
				stopped = false;
				totals = { checked: 0, indexed: 0, errors: 0 };
				log.textContent = '';
				setRunning(true);
				status.textContent = config.messages.starting;
				updateProgress(0);
				runBatch(0);
			});

			resumeButton.addEventListener('click', function () {
				if (!window.confirm(config.messages.confirmResume)) return;
				stopped = false;
				setRunning(true);
				status.textContent = config.messages.running;
				runBatch(readAfterId());
			});

			cancelButton.addEventListener('click', function () {
				stopped = true;
			});

			toggleButton.addEventListener('click', function () {
				var turningOn = !enabled;
				if (!window.confirm(turningOn ? config.messages.confirmOn : config.messages.confirmOff)) return;
				var body = new FormData();
				body.append('action', config.toggleAction);
				body.append('nonce', config.nonce);
				body.append('enabled', turningOn ? '1' : '0');
				fetch(config.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
					.then(function (r) { return r.json(); })
					.then(function (json) {
						if (!json || !json.success) {
							window.alert((json && json.data && json.data.message) || config.messages.error);
							return;
						}
						setEnabledUi(!!json.data.enabled);
						status.textContent = json.data.message || '';
					})
					.catch(function (err) {
						window.alert(String(err));
					});
			});
		})();
		</script>
		<?php
	}
}
