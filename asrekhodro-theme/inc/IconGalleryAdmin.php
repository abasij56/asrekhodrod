<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page: browsable/searchable gallery of the bundled car-spec SVG icons.
 * Lives under Theme Settings, right below «چیدمان صفحات».
 */
final class IconGalleryAdmin {

	private const MENU_SLUG = 'asrekhodro-icon-gallery';

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'register_menu' ), 101 );
	}

	public static function register_menu(): void {
		add_submenu_page(
			'asrekhodro-settings',
			'گالری آیکون‌ها',
			'گالری آیکون‌ها',
			'edit_theme_options',
			self::MENU_SLUG,
			array( self::class, 'render_page' )
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}

		$icons = CarSpecIcons::admin_catalog();
		$total = count( $icons );

		echo '<div class="wrap ak-icons-wrap">';
		echo '<h1>' . esc_html__( 'گالری آیکون‌ها', 'asrekhodro' ) . '</h1>';
		echo '<p class="ak-icons-intro">' . sprintf(
			/* translators: %s: number of icons */
			esc_html__( '%s آیکون SVG — مجموعه اختصاصی، Tabler و Lucide. برای هر آیکون عنوان پیشنهادی و شناسه‌ای که در فیلد «آیکون» بلاک‌ها استفاده می‌شود نمایش داده شده است.', 'asrekhodro' ),
			esc_html( number_format_i18n( $total ) )
		) . '</p>';

		self::print_styles();
		self::print_sprite();
		self::print_toolbar();

		echo '<div class="ak-icons-grid" id="ak-icons-grid">';
		foreach ( $icons as $icon ) {
			self::print_card( $icon );
		}
		echo '</div>';
		echo '<p class="ak-icons-empty" id="ak-icons-empty">' . esc_html__( 'نتیجه‌ای پیدا نشد. عبارت دیگری امتحان کنید.', 'asrekhodro' ) . '</p>';
		echo '</div>';

		echo '<div class="ak-icons-toast" id="ak-icons-toast" role="status"></div>';

		self::print_script( $total );
	}

	private static function print_toolbar(): void {
		?>
		<div class="ak-icons-toolbar">
			<label class="ak-icons-search">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3-3"/></svg>
				<input id="ak-icons-search" type="search" placeholder="<?php echo esc_attr__( 'جستجو: سیلندر، توربو، گیربکس، engine، battery…', 'asrekhodro' ); ?>" autocomplete="off" />
			</label>
			<div class="ak-icons-filters" id="ak-icons-filters">
				<button type="button" class="is-active" data-set="all"><?php esc_html_e( 'همه', 'asrekhodro' ); ?></button>
				<button type="button" data-set="custom">Custom</button>
				<button type="button" data-set="tabler">Tabler</button>
				<button type="button" data-set="tabler-filled">Filled</button>
				<button type="button" data-set="lucide">Lucide</button>
			</div>
			<span class="ak-icons-stats" id="ak-icons-stats" aria-live="polite"></span>
		</div>
		<?php
	}

	/**
	 * @param array<string, mixed> $icon
	 */
	private static function print_card( array $icon ): void {
		$id       = (string) ( $icon['id'] ?? '' );
		$set      = (string) ( $icon['set'] ?? '' );
		$name     = (string) ( $icon['name'] ?? '' );
		$title    = (string) ( $icon['title'] ?? '' );
		$url      = (string) ( $icon['url'] ?? '' );
		$sprite   = ! empty( $icon['sprite'] );
		$symbol   = (string) ( $icon['symbol'] ?? '' );
		$set_label = (string) ( $icon['setLabel'] ?? $set );

		$haystack = strtolower( trim( $name . ' ' . $title . ' ' . $set . ' ' . $set_label . ' ' . $id ) );

		$set_class = 'ak-icons-card__set';
		if ( $set === 'custom' ) {
			$set_class .= ' ak-icons-card__set--custom';
		} elseif ( $set === 'tabler-filled' ) {
			$set_class .= ' ak-icons-card__set--filled';
		}

		echo '<article class="ak-icons-card" data-set="' . esc_attr( $set ) . '" data-search="' . esc_attr( $haystack ) . '">';
		echo '<div class="ak-icons-card__preview">';
		if ( $sprite && $symbol !== '' ) {
			echo '<svg aria-hidden="true"><use href="#' . esc_attr( $symbol ) . '"></use></svg>';
		} elseif ( $url !== '' ) {
			echo '<img src="' . esc_url( $url ) . '" alt="" loading="lazy" decoding="async" />';
		}
		echo '</div>';
		echo '<div class="ak-icons-card__title">' . esc_html( $title ) . '</div>';
		echo '<div class="ak-icons-card__name">' . esc_html( $name ) . '</div>';
		echo '<div class="ak-icons-card__meta">';
		echo '<span class="' . esc_attr( $set_class ) . '">' . esc_html( $set_label ) . '</span>';
		echo '<button type="button" class="ak-icons-card__copy" data-id="' . esc_attr( $id ) . '">' . esc_html__( 'کپی شناسه', 'asrekhodro' ) . '</button>';
		echo '</div>';
		echo '</article>';
	}

	private static function print_sprite(): void {
		$path = ASREKHODRO_THEME_DIR . '/assets/cinfo-icons/custom/car-spec-icons.svg';
		if ( ! is_readable( $path ) ) {
			return;
		}

		$svg = (string) file_get_contents( $path );
		if ( $svg === '' ) {
			return;
		}

		echo '<div style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true">' . $svg . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted bundled sprite.
	}

	private static function print_styles(): void {
		?>
		<style>
			.ak-icons-intro { max-width: 60rem; color: #50575e; line-height: 1.7; }
			.ak-icons-toolbar {
				position: sticky; top: 32px; z-index: 20;
				display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center;
				margin: 1rem 0 1.25rem; padding: 0.85rem 1rem;
				background: #fff; border: 1px solid #dcdcde; border-radius: 8px;
				box-shadow: 0 1px 2px rgba(0,0,0,0.04);
			}
			.ak-icons-search { position: relative; flex: 1 1 240px; }
			.ak-icons-search svg {
				position: absolute; top: 50%; right: 0.7rem; transform: translateY(-50%);
				width: 1.05rem; height: 1.05rem; color: #787c82; pointer-events: none;
			}
			.ak-icons-search input {
				width: 100%; padding: 0.55rem 2.3rem 0.55rem 0.75rem;
				border: 1px solid #dcdcde; border-radius: 6px; font-size: 0.95rem;
				background: #f6f7f7;
			}
			.ak-icons-search input:focus { outline: 2px solid #2271b1; outline-offset: 0; background: #fff; }
			.ak-icons-filters { display: flex; flex-wrap: wrap; gap: 0.35rem; }
			.ak-icons-filters button {
				border: 1px solid #dcdcde; background: #f6f7f7; color: #50575e;
				font-size: 0.8rem; font-weight: 600; padding: 0.4rem 0.7rem;
				border-radius: 999px; cursor: pointer;
			}
			.ak-icons-filters button:hover { border-color: #2271b1; color: #1d2327; }
			.ak-icons-filters button.is-active { background: #2271b1; border-color: #2271b1; color: #fff; }
			.ak-icons-stats { font-size: 0.8rem; font-weight: 600; color: #787c82; white-space: nowrap; }
			.ak-icons-grid {
				display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
				gap: 0.75rem;
			}
			.ak-icons-card {
				display: flex; flex-direction: column; align-items: center; gap: 0.45rem;
				padding: 1rem 0.65rem 0.75rem; background: #fff; border: 1px solid #dcdcde;
				border-radius: 8px; text-align: center;
			}
			.ak-icons-card:hover { border-color: #c3c4c7; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
			.ak-icons-card.is-hidden { display: none; }
			.ak-icons-card__preview {
				display: grid; place-items: center; width: 3rem; height: 3rem; color: #646970;
			}
			.ak-icons-card__preview svg, .ak-icons-card__preview img {
				display: block; width: 2rem; height: 2rem; max-width: 100%; max-height: 100%;
			}
			.ak-icons-card__title {
				font-size: 0.78rem; font-weight: 700; line-height: 1.4; color: #1d2327; min-height: 2.1em;
			}
			.ak-icons-card__name {
				font-size: 0.66rem; font-weight: 600; color: #787c82;
				font-family: ui-monospace, Menlo, Consolas, monospace; direction: ltr; word-break: break-all;
			}
			.ak-icons-card__meta { display: flex; flex-wrap: wrap; gap: 0.35rem; justify-content: center; align-items: center; }
			.ak-icons-card__set {
				font-size: 0.6rem; font-weight: 700; padding: 0.15rem 0.45rem; border-radius: 999px;
				background: #f0f0f1; color: #646970;
			}
			.ak-icons-card__set--custom { background: rgba(34,113,177,0.1); color: #2271b1; }
			.ak-icons-card__set--filled { background: #eef2ff; color: #4338ca; }
			.ak-icons-card__copy {
				border: none; background: transparent; color: #2271b1; font-size: 0.66rem;
				font-weight: 700; cursor: pointer; padding: 0.15rem 0.35rem; border-radius: 4px;
			}
			.ak-icons-card__copy:hover { background: rgba(34,113,177,0.08); }
			.ak-icons-empty { display: none; text-align: center; padding: 3rem 1rem; color: #787c82; }
			.ak-icons-empty.is-visible { display: block; }
			.ak-icons-toast {
				position: fixed; bottom: 2rem; left: 50%; transform: translateX(-50%) translateY(1rem);
				opacity: 0; pointer-events: none; background: #1d2327; color: #fff;
				font-size: 0.82rem; font-weight: 600; padding: 0.55rem 1rem; border-radius: 999px;
				transition: opacity 0.25s ease, transform 0.25s ease; z-index: 10000;
			}
			.ak-icons-toast.is-visible { opacity: 1; transform: translateX(-50%) translateY(0); }
		</style>
		<?php
	}

	private static function print_script( int $total ): void {
		?>
		<script>
		(function () {
			var grid = document.getElementById('ak-icons-grid');
			if (!grid) { return; }
			var cards = Array.prototype.slice.call(grid.querySelectorAll('.ak-icons-card'));
			var searchInput = document.getElementById('ak-icons-search');
			var statsEl = document.getElementById('ak-icons-stats');
			var emptyEl = document.getElementById('ak-icons-empty');
			var filters = document.getElementById('ak-icons-filters');
			var toastEl = document.getElementById('ak-icons-toast');
			var total = <?php echo (int) $total; ?>;
			var activeSet = 'all';
			var toastTimer;

			function normalize(s) { return (s || '').toLowerCase().replace(/[\s_-]+/g, ' ').trim(); }

			function render() {
				var q = normalize(searchInput.value);
				var visible = 0;
				cards.forEach(function (card) {
					var matchSet = activeSet === 'all' || card.getAttribute('data-set') === activeSet;
					var hay = card.getAttribute('data-search') || '';
					var matchQ = !q || hay.indexOf(q) !== -1;
					var show = matchSet && matchQ;
					card.classList.toggle('is-hidden', !show);
					if (show) { visible++; }
				});
				statsEl.textContent = visible + ' / ' + total;
				emptyEl.classList.toggle('is-visible', visible === 0);
			}

			function toast(msg) {
				toastEl.textContent = msg;
				toastEl.classList.add('is-visible');
				clearTimeout(toastTimer);
				toastTimer = setTimeout(function () { toastEl.classList.remove('is-visible'); }, 1800);
			}

			searchInput.addEventListener('input', render);

			filters.addEventListener('click', function (e) {
				var btn = e.target.closest('button[data-set]');
				if (!btn) { return; }
				activeSet = btn.getAttribute('data-set');
				filters.querySelectorAll('button').forEach(function (b) { b.classList.toggle('is-active', b === btn); });
				render();
			});

			grid.addEventListener('click', function (e) {
				var btn = e.target.closest('.ak-icons-card__copy');
				if (!btn) { return; }
				var id = btn.getAttribute('data-id') || '';
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(id).then(function () { toast('کپی شد: ' + id); }, function () { toast(id); });
				} else {
					toast(id);
				}
			});

			render();
		})();
		</script>
		<?php
	}
}
