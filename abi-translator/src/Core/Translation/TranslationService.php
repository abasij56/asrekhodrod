<?php

namespace ABI\Translator\Core\Translation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABI\Translator\Core\AI\ProviderException;
use ABI\Translator\Core\AI\ProviderFactory;
use ABI\Translator\Core\AI\ProviderInterface;
use ABI\Translator\Core\Settings;
use ABI\Translator\Core\Support\Logger;
use ABI\Translator\Core\Support\RateLimiter;

/**
 * Orchestrates on-demand translation with DB caching and fail-safe fallback.
 *
 * Flow: request-cache -> DB (by source hash) -> AI provider -> save -> return.
 * On ANY failure the original (Persian) text is returned so pages never break.
 */
final class TranslationService {

	private TranslationRepository $repository;
	private TranslationCache $cache;
	private ?ProviderInterface $provider = null;

	/** Approx. character ceiling per provider request for body content. */
	private const CHUNK_LIMIT = 4000;

	public function __construct( TranslationRepository $repository, TranslationCache $cache ) {
		$this->repository = $repository;
		$this->cache      = $cache;
	}

	/** Max items sent to the provider in a single batch request. */
	private const BATCH_SIZE = 20;

	/**
	 * Warm many objects at once:
	 *   1. one DB read (getBatch) fills the request cache for existing translations;
	 *   2. remaining misses are translated in a SINGLE provider request per batch
	 *      (not one request per item), then saved + cached.
	 *
	 * After warming, translate_field() calls for these objects are cache hits.
	 *
	 * @param array<int, string> $id_to_text Map of object_id => source text.
	 */
	public function warm( string $object_type, array $id_to_text, string $field, string $lang ): void {
		if ( $lang === Settings::default_lang() || $id_to_text === array() ) {
			return;
		}

		$id_to_hash = array();
		foreach ( $id_to_text as $id => $text ) {
			$id = (int) $id;
			if ( $id > 0 && trim( (string) $text ) !== '' ) {
				$id_to_hash[ $id ] = ContentHasher::hash( (string) $text );
			}
		}

		if ( $id_to_hash === array() ) {
			return;
		}

		try {
			$found = $this->repository->getBatch( $object_type, $id_to_hash, $field, $lang );
		} catch ( \Throwable $e ) {
			Logger::warning( 'Batch warm read failed', array( 'reason' => $e->getMessage() ) );
			$found = array();
		}

		$misses = array();
		foreach ( $id_to_hash as $id => $hash ) {
			if ( array_key_exists( $id, $found ) ) {
				$this->cache->set( $this->cache->key( $object_type, (int) $id, $field, $lang ), (string) $found[ $id ] );
				continue;
			}

			$should = apply_filters( 'abi_translator_should_translate', true, $object_type, (int) $id, $field, $lang );
			if ( $should ) {
				$misses[ (int) $id ] = (string) $id_to_text[ $id ];
			}
		}

		if ( $misses === array() || ! Settings::has_api_key() ) {
			return;
		}

		$this->translate_misses( $object_type, $misses, $id_to_hash, $field, $lang );
	}

	/**
	 * Translate warm() misses in bounded batches (one provider request each).
	 *
	 * @param array<int, string> $misses     object_id => source text
	 * @param array<int, string> $id_to_hash object_id => source hash
	 */
	private function translate_misses( string $object_type, array $misses, array $id_to_hash, string $field, string $lang ): void {
		$from     = Settings::default_lang();
		$provider = $this->provider();

		foreach ( array_chunk( $misses, self::BATCH_SIZE, true ) as $group ) {
			if ( ! RateLimiter::allow() ) {
				// Rate limited: leave remaining items for on-demand translation later.
				Logger::warning( 'Batch warm skipped: rate limit reached', array( 'object_type' => $object_type ) );
				return;
			}

			try {
				$translated = $provider->translateBatch( $group, $from, $lang );
			} catch ( \Throwable $e ) {
				// Whole batch failed — leave these for on-demand translate_field().
				Logger::warning( 'Batch warm translate failed', array( 'reason' => $e->getMessage() ) );
				continue;
			}

			foreach ( $translated as $id => $text ) {
				$id   = (int) $id;
				$text = trim( (string) $text );
				if ( $text === '' || ! isset( $id_to_hash[ $id ] ) ) {
					continue;
				}

				try {
					$this->repository->save(
						$object_type,
						$id,
						$field,
						$lang,
						$id_to_hash[ $id ],
						$text,
						$provider->id(),
						$provider->model()
					);
				} catch ( \Throwable $e ) {
					Logger::warning( 'Batch warm save failed', array( 'reason' => $e->getMessage() ) );
				}

				$this->cache->set( $this->cache->key( $object_type, $id, $field, $lang ), $text );
			}
		}
	}

	/**
	 * Resolve a translated field, translating + caching on miss.
	 *
	 * @param array<string, mixed> $context
	 */
	public function translate_field(
		string $object_type,
		int $object_id,
		string $field,
		string $text,
		string $lang,
		array $context = array()
	): string {
		$from = Settings::default_lang();

		if ( $lang === $from || trim( $text ) === '' ) {
			return $text;
		}

		$cache_key = $this->cache->key( $object_type, $object_id, $field, $lang );
		if ( $this->cache->has( $cache_key ) ) {
			return (string) $this->cache->get( $cache_key );
		}

		$source_hash = ContentHasher::hash( $text );

		$stored = $this->repository->get( $object_type, $object_id, $field, $lang, $source_hash );
		if ( $stored !== null ) {
			$this->cache->set( $cache_key, $stored );

			return $stored;
		}

		if ( ! Settings::has_api_key() ) {
			// Nothing we can do without credentials — fail safe to the original.
			return $text;
		}

		/**
		 * Allow skipping translation for a specific field/object.
		 *
		 * @param bool $should_translate
		 */
		$should = apply_filters( 'abi_translator_should_translate', true, $object_type, $object_id, $field, $lang );
		if ( ! $should ) {
			return $text;
		}

		if ( ! RateLimiter::allow() ) {
			// Rate limited: serve the original text; a later request can translate it.
			Logger::warning(
				'Translation skipped: rate limit reached',
				array(
					'object_type' => $object_type,
					'object_id'   => $object_id,
					'field'       => $field,
				)
			);

			return $text;
		}

		try {
			$translated = $this->run_provider( $field, $text, $from, $lang, $context );

			if ( trim( $translated ) === '' ) {
				return $text; // Empty result -> fallback, do not cache.
			}

			$provider = $this->provider();
			$this->repository->save(
				$object_type,
				$object_id,
				$field,
				$lang,
				$source_hash,
				$translated,
				$provider->id(),
				$provider->model()
			);

			$this->cache->set( $cache_key, $translated );

			return $translated;
		} catch ( ProviderException $e ) {
			Logger::error(
				'Translation failed, serving original text',
				array(
					'object_type' => $object_type,
					'object_id'   => $object_id,
					'field'       => $field,
					'lang'        => $lang,
					'reason'      => $e->getMessage(),
				)
			);

			return $text; // Fail-safe.
		} catch ( \Throwable $e ) {
			Logger::error( 'Unexpected translation error', array( 'reason' => $e->getMessage() ) );

			return $text; // Never break the page.
		}
	}

	/**
	 * @param array<string, mixed> $context
	 * @throws ProviderException
	 */
	private function run_provider( string $field, string $text, string $from, string $to, array $context ): string {
		$provider = $this->provider();

		$context = apply_filters( 'abi_translator_before_translate', $context, $text );

		// Long HTML bodies are chunked at block boundaries to keep requests bounded.
		if ( $field === 'content' && strlen( $text ) > self::CHUNK_LIMIT ) {
			$chunks     = $this->chunk_html( $text );
			$translated = array();

			foreach ( $chunks as $chunk ) {
				if ( trim( $chunk ) === '' ) {
					$translated[] = $chunk;
					continue;
				}
				$translated[] = $provider->translate( $chunk, $from, $to, $context );
			}

			$output = implode( "\n", $translated );
		} else {
			$output = $provider->translate( $text, $from, $to, $context );
		}

		/**
		 * Filter the translated text before it is cached/returned.
		 *
		 * @param string $output
		 * @param string $text
		 */
		return (string) apply_filters( 'abi_translator_after_translate', $output, $text, $context );
	}

	/**
	 * Split HTML into chunks at top-level paragraph/block boundaries.
	 *
	 * @return array<int, string>
	 */
	private function chunk_html( string $html ): array {
		// Split on closing block tags while keeping the delimiter attached.
		$parts = preg_split( '/(?<=<\/p>|<\/div>|<\/h2>|<\/h3>|<\/li>|<\/blockquote>)/i', $html );
		if ( ! is_array( $parts ) || $parts === array() ) {
			return array( $html );
		}

		$chunks  = array();
		$current = '';

		foreach ( $parts as $part ) {
			if ( strlen( $current ) + strlen( $part ) > self::CHUNK_LIMIT && $current !== '' ) {
				$chunks[] = $current;
				$current  = '';
			}
			$current .= $part;
		}

		if ( $current !== '' ) {
			$chunks[] = $current;
		}

		return $chunks;
	}

	private function provider(): ProviderInterface {
		if ( $this->provider === null ) {
			$this->provider = ProviderFactory::make();
		}

		return $this->provider;
	}
}
