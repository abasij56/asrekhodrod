<?php

namespace ABI\Translator\Core\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABI\Translator\Core\Settings;

/**
 * Builds the configured AI provider from plugin settings.
 * GapGPT is the default; the concrete provider can change without touching Core.
 */
final class ProviderFactory {

	public static function make(): ProviderInterface {
		$id          = Settings::provider();
		$api_key     = Settings::api_key();
		$base_url    = Settings::base_url();
		$model       = Settings::model();
		$temperature = Settings::temperature();
		$max_tokens  = Settings::max_tokens();
		$timeout     = Settings::timeout();

		$provider = self::instantiate( $id, $api_key, $base_url, $model, $temperature, $max_tokens, $timeout );

		/**
		 * Allow overriding/decorating the provider instance.
		 *
		 * @param ProviderInterface $provider
		 * @param string            $id
		 */
		return apply_filters( 'abi_translator_provider', $provider, $id );
	}

	private static function instantiate(
		string $id,
		string $api_key,
		string $base_url,
		string $model,
		float $temperature,
		int $max_tokens,
		int $timeout
	): ProviderInterface {
		switch ( $id ) {
			case 'openai':
				if ( $base_url === '' ) {
					$base_url = 'https://api.openai.com/v1';
				}
				return new OpenAiProvider( $api_key, $base_url, $model, $temperature, $max_tokens, $timeout );

			case 'gapgpt':
			default:
				if ( $base_url === '' ) {
					$base_url = 'https://api.gapgpt.app/v1';
				}
				return new GapGptProvider( $api_key, $base_url, $model, $temperature, $max_tokens, $timeout );
		}
	}
}
