<?php

namespace ABI\Translator\Core\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thrown when a provider cannot fulfil a translation request.
 * The translation layer catches this and falls back to the original text.
 */
final class ProviderException extends \RuntimeException {
}
