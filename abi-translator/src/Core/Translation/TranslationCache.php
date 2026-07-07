<?php

namespace ABI\Translator\Core\Translation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * In-request memoization so the same field (e.g. a title rendered several times
 * on one page) is only resolved once per request.
 */
final class TranslationCache {

	/** @var array<string, string> */
	private array $store = array();

	public function key( string $object_type, int $object_id, string $field, string $lang ): string {
		return $object_type . ':' . $object_id . ':' . $field . ':' . $lang;
	}

	public function has( string $key ): bool {
		return array_key_exists( $key, $this->store );
	}

	public function get( string $key ): ?string {
		return $this->store[ $key ] ?? null;
	}

	public function set( string $key, string $value ): void {
		$this->store[ $key ] = $value;
	}

	public function flush(): void {
		$this->store = array();
	}
}
