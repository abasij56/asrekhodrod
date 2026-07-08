<?php

namespace ABI\Translator\Core\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABI\Translator\Core\AI\ProviderFactory;
use ABI\Translator\Core\Settings;
use ABI\Translator\Core\Translation\TranslationRepository;

/**
 * Settings → ABI Translator admin screen (Provider / API Key / Model / ...).
 * Includes an AJAX "Test connection" action and a missing-API-key notice.
 */
final class SettingsPage {

	private const MENU_SLUG   = 'abi-translator';
	private const GROUP       = 'abi_translator_group';
	private const NONCE_TEST  = 'abi_translator_test_connection';
	private const CAPABILITY  = 'manage_options';

	private ?Dashboard $dashboard;

	public function __construct( ?TranslationRepository $repository = null ) {
		$this->dashboard = $repository !== null ? new Dashboard( $repository ) : null;
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_notices', array( $this, 'maybe_missing_key_notice' ) );
		add_action( 'wp_ajax_abi_translator_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_inline_script' ) );
	}

	public function add_menu(): void {
		add_options_page(
			__( 'ABI Translator', 'abi-translator' ),
			__( 'ABI Translator', 'abi-translator' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function register_settings(): void {
		register_setting(
			self::GROUP,
			ABI_TRANSLATOR_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);

		add_settings_section(
			'abi_translator_languages',
			__( 'Languages', 'abi-translator' ),
			static function (): void {
				echo '<p>' . esc_html__( 'Choose the source (default) language and the target languages to translate into.', 'abi-translator' ) . '</p>';
			},
			self::MENU_SLUG
		);

		$lang_fields = array(
			'default_lang'     => __( 'Default language', 'abi-translator' ),
			'target_languages' => __( 'Target languages', 'abi-translator' ),
		);

		foreach ( $lang_fields as $key => $label ) {
			add_settings_field(
				'abi_translator_' . $key,
				esc_html( $label ),
				array( $this, 'render_field' ),
				self::MENU_SLUG,
				'abi_translator_languages',
				array( 'key' => $key )
			);
		}

		add_settings_section(
			'abi_translator_ai',
			__( 'AI Provider', 'abi-translator' ),
			static function (): void {
				echo '<p>' . esc_html__( 'Configure the AI translation provider. The API key is stored in wp_options and never in the theme.', 'abi-translator' ) . '</p>';
			},
			self::MENU_SLUG
		);

		$fields = array(
			'provider'    => __( 'Provider', 'abi-translator' ),
			'api_key'     => __( 'API Key', 'abi-translator' ),
			'base_url'    => __( 'Base URL', 'abi-translator' ),
			'model'       => __( 'Model', 'abi-translator' ),
			'temperature' => __( 'Temperature', 'abi-translator' ),
			'max_tokens'  => __( 'Max tokens', 'abi-translator' ),
			'timeout'     => __( 'Timeout (seconds)', 'abi-translator' ),
		);

		foreach ( $fields as $key => $label ) {
			add_settings_field(
				'abi_translator_' . $key,
				esc_html( $label ),
				array( $this, 'render_field' ),
				self::MENU_SLUG,
				'abi_translator_ai',
				array( 'key' => $key )
			);
		}

		add_settings_section(
			'abi_translator_maintenance',
			__( 'Maintenance', 'abi-translator' ),
			static function (): void {
				echo '<p>' . esc_html__( 'Optional safeguards for on-demand translation.', 'abi-translator' ) . '</p>';
			},
			self::MENU_SLUG
		);

		$maintenance_fields = array(
			'rate_limit_enabled' => __( 'Rate limit', 'abi-translator' ),
			'rate_limit_max'     => __( 'Max requests / minute', 'abi-translator' ),
		);

		foreach ( $maintenance_fields as $key => $label ) {
			add_settings_field(
				'abi_translator_' . $key,
				esc_html( $label ),
				array( $this, 'render_field' ),
				self::MENU_SLUG,
				'abi_translator_maintenance',
				array( 'key' => $key )
			);
		}
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ): array {
		$input   = is_array( $input ) ? $input : array();
		$current = Settings::all();

		$clean                = array();
		$clean['provider']    = in_array( ( $input['provider'] ?? '' ), array( 'gapgpt', 'openai' ), true )
			? (string) $input['provider']
			: 'gapgpt';
		// Empty submission keeps the previously saved key (field is never pre-filled).
		$submitted_key    = trim( (string) ( $input['api_key'] ?? '' ) );
		$clean['api_key'] = $submitted_key !== '' ? $submitted_key : trim( (string) ( $current['api_key'] ?? '' ) );
		$clean['base_url']    = esc_url_raw( trim( (string) ( $input['base_url'] ?? '' ) ) );
		$clean['model']       = sanitize_text_field( (string) ( $input['model'] ?? 'gpt-4o-mini' ) );
		$clean['temperature'] = max( 0.0, min( 2.0, (float) ( $input['temperature'] ?? 0.3 ) ) );
		$clean['max_tokens']  = max( 1, (int) ( $input['max_tokens'] ?? 2000 ) );
		$clean['timeout']     = max( 5, (int) ( $input['timeout'] ?? 30 ) );

		// Default language: must be a known language.
		$default = (string) ( $input['default_lang'] ?? $current['default_lang'] ?? 'fa' );
		if ( ! array_key_exists( $default, Settings::KNOWN_LANGUAGES ) ) {
			$default = 'fa';
		}
		$clean['default_lang'] = $default;

		// Target languages: known, not equal to default, unique.
		$targets_raw = $input['target_languages'] ?? array();
		$targets     = array();
		if ( is_array( $targets_raw ) ) {
			foreach ( $targets_raw as $code ) {
				$code = (string) $code;
				if ( $code !== $default && array_key_exists( $code, Settings::KNOWN_LANGUAGES ) ) {
					$targets[] = $code;
				}
			}
		}
		$targets = array_values( array_unique( $targets ) );

		// Guarantee at least one target language.
		if ( $targets === array() ) {
			$targets = $default === 'en' ? array( 'fa' ) : array( 'en' );
		}

		// Active languages: default first, then targets.
		$clean['languages'] = array_values( array_unique( array_merge( array( $default ), $targets ) ) );

		// Maintenance: rate limiting.
		$clean['rate_limit_enabled'] = ! empty( $input['rate_limit_enabled'] );
		$clean['rate_limit_max']     = max( 1, (int) ( $input['rate_limit_max'] ?? 60 ) );

		Settings::flush();

		return $clean;
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public function render_field( array $args ): void {
		$key   = (string) ( $args['key'] ?? '' );
		$value = Settings::get( $key, '' );
		$name  = ABI_TRANSLATOR_OPTION . '[' . $key . ']';

		switch ( $key ) {
			case 'default_lang':
				echo '<select name="' . esc_attr( $name ) . '">';
				foreach ( Settings::KNOWN_LANGUAGES as $code => $label ) {
					printf(
						'<option value="%s"%s>%s</option>',
						esc_attr( $code ),
						selected( Settings::default_lang(), $code, false ),
						esc_html( $label )
					);
				}
				echo '</select>';
				echo '<p class="description">' . esc_html__( 'This language is served without a URL prefix and is never translated.', 'abi-translator' ) . '</p>';
				break;

			case 'target_languages':
				$selected     = Settings::target_languages();
				$checkbox_name = ABI_TRANSLATOR_OPTION . '[target_languages][]';
				foreach ( Settings::KNOWN_LANGUAGES as $code => $label ) {
					if ( $code === Settings::default_lang() ) {
						continue; // Default cannot also be a target.
					}
					printf(
						'<label style="display:inline-block;margin-inline-end:12px;"><input type="checkbox" name="%s" value="%s"%s /> %s</label>',
						esc_attr( $checkbox_name ),
						esc_attr( $code ),
						checked( in_array( $code, $selected, true ), true, false ),
						esc_html( $label )
					);
				}
				echo '<p class="description">' . esc_html__( 'Served under a URL prefix (e.g. /en/). Select at least one.', 'abi-translator' ) . '</p>';
				break;

			case 'provider':
				$options = array(
					'gapgpt' => 'GapGPT',
					'openai' => 'OpenAI',
				);
				echo '<select name="' . esc_attr( $name ) . '">';
				foreach ( $options as $val => $label ) {
					printf(
						'<option value="%s"%s>%s</option>',
						esc_attr( $val ),
						selected( (string) $value, $val, false ),
						esc_html( $label )
					);
				}
				echo '</select>';
				break;

			case 'api_key':
				$has_key = trim( (string) $value ) !== '';
				printf(
					'<input type="password" name="%s" value="" class="regular-text" autocomplete="off" placeholder="%s" />',
					esc_attr( $name ),
					$has_key ? esc_attr__( '•••••••• (saved)', 'abi-translator' ) : ''
				);
				echo '<p class="description">' . esc_html__( 'Stored in wp_options. Leave blank to keep the current key.', 'abi-translator' ) . '</p>';
				break;

			case 'base_url':
				printf(
					'<input type="url" name="%s" value="%s" class="regular-text" placeholder="https://api.gapgpt.app/v1" />',
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
				break;

			case 'temperature':
				printf(
					'<input type="number" step="0.1" min="0" max="2" name="%s" value="%s" class="small-text" />',
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
				break;

			case 'max_tokens':
			case 'timeout':
				printf(
					'<input type="number" step="1" min="1" name="%s" value="%s" class="small-text" />',
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
				break;

			case 'rate_limit_enabled':
				printf(
					'<label><input type="checkbox" name="%s" value="1"%s /> %s</label>',
					esc_attr( $name ),
					checked( Settings::rate_limit_enabled(), true, false ),
					esc_html__( 'Limit on-demand translation requests per visitor IP.', 'abi-translator' )
				);
				break;

			case 'rate_limit_max':
				printf(
					'<input type="number" step="1" min="1" name="%s" value="%s" class="small-text" />',
					esc_attr( $name ),
					esc_attr( (string) Settings::rate_limit_max() )
				);
				echo '<p class="description">' . esc_html__( 'Maximum provider requests per IP per minute (when rate limiting is enabled).', 'abi-translator' ) . '</p>';
				break;

			default:
				printf(
					'<input type="text" name="%s" value="%s" class="regular-text" />',
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
				break;
		}
	}

	public function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'ABI Translator', 'abi-translator' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::MENU_SLUG );
				submit_button();
				?>
			</form>

			<h2><?php echo esc_html__( 'Connection test', 'abi-translator' ); ?></h2>
			<p>
				<button type="button" class="button" id="abi-translator-test">
					<?php echo esc_html__( 'Test connection', 'abi-translator' ); ?>
				</button>
				<span id="abi-translator-test-result" style="margin-inline-start:8px;"></span>
			</p>

			<?php
			if ( $this->dashboard !== null ) {
				$this->dashboard->render();
			}
			?>
		</div>
		<?php
	}

	public function enqueue_inline_script( string $hook ): void {
		if ( $hook !== 'settings_page_' . self::MENU_SLUG ) {
			return;
		}

		$nonce = wp_create_nonce( self::NONCE_TEST );
		$ajax  = esc_url( admin_url( 'admin-ajax.php' ) );

		$testing = esc_js( __( 'Testing…', 'abi-translator' ) );
		$ok      = esc_js( __( 'Connection OK', 'abi-translator' ) );
		$fail    = esc_js( __( 'Connection failed', 'abi-translator' ) );

		$script = <<<JS
(function(){
	var btn = document.getElementById('abi-translator-test');
	var out = document.getElementById('abi-translator-test-result');
	if(!btn){return;}
	btn.addEventListener('click', function(){
		out.textContent = '{$testing}';
		var data = new FormData();
		data.append('action','abi_translator_test_connection');
		data.append('_wpnonce','{$nonce}');
		fetch('{$ajax}',{method:'POST',credentials:'same-origin',body:data})
			.then(function(r){return r.json();})
			.then(function(res){
				out.textContent = (res && res.success) ? '{$ok}' : ('{$fail}' + (res && res.data ? ': ' + res.data : ''));
			})
			.catch(function(){ out.textContent = '{$fail}'; });
	});
})();
JS;

		wp_register_script( 'abi-translator-admin', '', array(), ABI_TRANSLATOR_VERSION, true );
		wp_enqueue_script( 'abi-translator-admin' );
		wp_add_inline_script( 'abi-translator-admin', $script );
	}

	public function ajax_test_connection(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( __( 'Permission denied.', 'abi-translator' ), 403 );
		}

		check_ajax_referer( self::NONCE_TEST );

		if ( ! Settings::has_api_key() ) {
			wp_send_json_error( __( 'API key is not set.', 'abi-translator' ) );
		}

		try {
			$ok = ProviderFactory::make()->testConnection();
		} catch ( \Throwable $e ) {
			wp_send_json_error( $e->getMessage() );
		}

		if ( $ok ) {
			wp_send_json_success( __( 'ok', 'abi-translator' ) );
		}

		wp_send_json_error( __( 'No valid response from provider.', 'abi-translator' ) );
	}

	public function maybe_missing_key_notice(): void {
		if ( ! current_user_can( self::CAPABILITY ) || Settings::has_api_key() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && $screen->id === 'settings_page_' . self::MENU_SLUG ) {
			return; // Avoid duplicate messaging on the settings screen itself.
		}

		$url = esc_url( admin_url( 'options-general.php?page=' . self::MENU_SLUG ) );
		echo '<div class="notice notice-warning"><p>';
		printf(
			/* translators: %s: settings page URL */
			wp_kses_post( __( 'ABI Translator: no API key configured yet. Translations are disabled until you <a href="%s">add an API key</a>.', 'abi-translator' ) ),
			$url
		);
		echo '</p></div>';
	}
}
