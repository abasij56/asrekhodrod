<?php

namespace ABI\Translator\Core\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABI\Translator\Core\Support\Logger;

/**
 * OpenAI-compatible chat-completions provider. GapGPT is the default proxy;
 * OpenAiProvider extends this and only changes the id/default base URL.
 */
class GapGptProvider implements ProviderInterface {

	protected string $api_key;
	protected string $base_url;
	protected string $model;
	protected float $temperature;
	protected int $max_tokens;
	protected int $timeout;

	/** @var array<string, string> */
	private const LANG_NAMES = array(
		'fa' => 'Persian (fa)',
		'en' => 'English (en)',
		'ar' => 'Arabic (ar)',
	);

	public function __construct(
		string $api_key,
		string $base_url,
		string $model,
		float $temperature = 0.3,
		int $max_tokens = 2000,
		int $timeout = 30
	) {
		$this->api_key     = $api_key;
		$this->base_url    = rtrim( $base_url, '/' );
		$this->model       = $model;
		$this->temperature = $temperature;
		$this->max_tokens  = $max_tokens;
		$this->timeout     = max( 5, $timeout );
	}

	public function id(): string {
		return 'gapgpt';
	}

	public function model(): string {
		return $this->model;
	}

	public function translate( string $text, string $from, string $to, array $context = array() ): string {
		$trimmed = trim( $text );
		if ( $trimmed === '' ) {
			return $text;
		}

		$system   = $this->build_system_prompt( $from, $to, $context );
		$response = $this->chat(
			array(
				array(
					'role'    => 'system',
					'content' => $system,
				),
				array(
					'role'    => 'user',
					'content' => $text,
				),
			)
		);

		if ( $response === '' ) {
			throw new ProviderException( 'Empty translation returned by provider.' );
		}

		return $response;
	}

	/**
	 * Translate many short strings in a SINGLE request using a JSON map, instead
	 * of one HTTP request per item. Keys are preserved. Items that come back
	 * missing/empty are simply omitted from the result (caller keeps original /
	 * resolves them on demand), so a partially-valid response is still useful.
	 *
	 * @param array<int|string, string> $items key => source text
	 * @return array<int|string, string> key => translated text (successful only)
	 * @throws ProviderException When the response cannot be parsed at all.
	 */
	public function translateBatch( array $items, string $from, string $to ): array {
		$payload = array();
		foreach ( $items as $key => $text ) {
			if ( trim( (string) $text ) !== '' ) {
				$payload[ (string) $key ] = (string) $text;
			}
		}

		if ( $payload === array() ) {
			return array();
		}

		$system   = $this->build_batch_system_prompt( $from, $to );
		$user     = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$response = $this->chat(
			array(
				array(
					'role'    => 'system',
					'content' => $system,
				),
				array(
					'role'    => 'user',
					'content' => (string) $user,
				),
			),
			$this->batch_max_tokens( count( $payload ) )
		);

		$decoded = $this->decode_json_object( $response );
		if ( $decoded === null ) {
			throw new ProviderException( 'Batch translation returned unparsable output.' );
		}

		$result = array();
		foreach ( $items as $key => $text ) {
			$k = (string) $key;
			if ( ! array_key_exists( $k, $decoded ) ) {
				continue;
			}
			$value = trim( (string) $decoded[ $k ] );
			if ( $value !== '' ) {
				$result[ $key ] = $value;
			}
		}

		return $result;
	}

	public function testConnection(): bool {
		if ( $this->api_key === '' ) {
			return false;
		}

		$response = $this->chat(
			array(
				array(
					'role'    => 'user',
					'content' => 'Reply with the single word: ok',
				),
			),
			16
		);

		return $response !== '';
	}

	/**
	 * @param array<string, mixed> $context
	 */
	protected function build_system_prompt( string $from, string $to, array $context ): string {
		$from_name = self::LANG_NAMES[ $from ] ?? $from;
		$to_name   = self::LANG_NAMES[ $to ] ?? $to;

		$lines = array(
			'You are a professional translator for an automotive news website.',
			sprintf( 'Translate from %s to %s.', $from_name, $to_name ),
			'Rules:',
			'- Keep HTML tags unchanged',
			'- Keep numbers, URLs, brand names (Iran Khodro, SAIPA, Chery, etc.)',
			'- News style: clear, neutral, journalistic',
			'- Do not add or remove sentences',
			'Output only the translation, no explanation.',
		);

		if ( isset( $context['field'] ) && $context['field'] === 'title' ) {
			$lines[] = '- This is a headline: keep it concise.';
		}

		return implode( "\n", $lines );
	}

	/**
	 * System prompt for batch (JSON map) translation of short strings.
	 */
	protected function build_batch_system_prompt( string $from, string $to ): string {
		$from_name = self::LANG_NAMES[ $from ] ?? $from;
		$to_name   = self::LANG_NAMES[ $to ] ?? $to;

		$lines = array(
			'You are a professional translator for an automotive news website.',
			sprintf( 'You receive a JSON object whose values are short texts (mostly news headlines) in %s.', $from_name ),
			sprintf( 'Translate every value to %s.', $to_name ),
			'Rules:',
			'- Return ONLY a JSON object with the EXACT same keys.',
			'- Each value must be the translation of the input value with the same key.',
			'- Keep numbers, URLs and brand names (Iran Khodro, SAIPA, Chery, etc.).',
			'- Headlines must stay concise and journalistic.',
			'- Do not add keys, comments, markdown, or code fences. Output raw JSON only.',
		);

		return implode( "\n", $lines );
	}

	/**
	 * Rough token budget for a batch response based on item count.
	 */
	protected function batch_max_tokens( int $count ): int {
		$estimate = ( $count * 60 ) + 256;

		return min( 4000, max( $this->max_tokens, $estimate ) );
	}

	/**
	 * Decode a JSON object from a model response, tolerating code fences and
	 * surrounding prose. Returns null if no JSON object can be recovered.
	 *
	 * @return array<string, mixed>|null
	 */
	protected function decode_json_object( string $response ): ?array {
		$text = trim( $response );
		if ( $text === '' ) {
			return null;
		}

		// Strip ```json ... ``` / ``` ... ``` fences if present.
		if ( str_starts_with( $text, '```' ) ) {
			$text = (string) preg_replace( '/^```[a-z]*\s*|\s*```$/i', '', $text );
			$text = trim( $text );
		}

		// Narrow to the outermost { ... } block.
		$start = strpos( $text, '{' );
		$end   = strrpos( $text, '}' );
		if ( $start === false || $end === false || $end <= $start ) {
			return null;
		}

		$json    = substr( $text, $start, $end - $start + 1 );
		$decoded = json_decode( $json, true );

		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Perform a chat-completions request and return the assistant message content.
	 *
	 * @param array<int, array<string, string>> $messages
	 * @throws ProviderException
	 */
	protected function chat( array $messages, ?int $max_tokens = null ): string {
		if ( $this->api_key === '' ) {
			throw new ProviderException( 'Missing API key.' );
		}

		if ( $this->base_url === '' ) {
			throw new ProviderException( 'Missing base URL.' );
		}

		$endpoint = $this->base_url . '/chat/completions';

		$body = array(
			'model'       => $this->model,
			'temperature' => $this->temperature,
			'max_tokens'  => $max_tokens ?? $this->max_tokens,
			'messages'    => $messages,
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => $this->timeout,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $this->api_key,
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			Logger::error( 'Provider request failed', array( 'error' => $response->get_error_message() ) );
			throw new ProviderException( 'HTTP request failed: ' . $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			Logger::error( 'Provider returned non-2xx', array( 'code' => $code ) );
			throw new ProviderException( 'Provider HTTP status ' . $code );
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			throw new ProviderException( 'Invalid JSON from provider.' );
		}

		$content = $data['choices'][0]['message']['content'] ?? '';

		return is_string( $content ) ? trim( $content ) : '';
	}
}
