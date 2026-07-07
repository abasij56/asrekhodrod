<?php

namespace ABI\Translator\Core\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OpenAI provider. Shares the OpenAI-compatible transport with GapGptProvider;
 * only the identifier differs (default base URL is configured in admin).
 */
final class OpenAiProvider extends GapGptProvider {

	public function id(): string {
		return 'openai';
	}
}
