<?php

namespace ABI\Translator\Core\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract every AI translation provider must implement.
 */
interface ProviderInterface {

	/**
	 * Translate a single string from $from language to $to language.
	 *
	 * @param array<string, mixed> $context Optional hints (object_type, field, ...).
	 * @throws ProviderException On any transport/API failure.
	 */
	public function translate( string $text, string $from, string $to, array $context = array() ): string;

	/**
	 * Translate a batch of strings. Returns values keyed by the same keys as $items.
	 *
	 * @param array<int|string, string> $items
	 * @return array<int|string, string>
	 * @throws ProviderException On any transport/API failure.
	 */
	public function translateBatch( array $items, string $from, string $to ): array;

	/**
	 * Lightweight connectivity/credentials check.
	 */
	public function testConnection(): bool;

	/**
	 * Provider identifier (e.g. "gapgpt", "openai").
	 */
	public function id(): string;

	/**
	 * Model identifier currently configured.
	 */
	public function model(): string;
}
