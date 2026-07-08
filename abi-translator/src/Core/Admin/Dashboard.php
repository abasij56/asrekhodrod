<?php

namespace ABI\Translator\Core\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABI\Translator\Core\Translation\TranslationRepository;

/**
 * Renders the read-only Statistics + estimated-cost panel on the settings page.
 *
 * Tokens are not stored per row, so cost is a transparent rough estimate:
 *   estimated_tokens = rows × avg_tokens_per_row
 *   estimated_cost   = estimated_tokens / 1,000,000 × price_per_million_usd
 * Both assumptions are filterable and shown in the UI so nobody mistakes the
 * figure for a billed amount.
 */
final class Dashboard {

	private const CAPABILITY = 'manage_options';

	/** Rough combined (input+output) tokens per stored translation row. */
	private const AVG_TOKENS_PER_ROW = 700;

	/** Rough blended price per 1M tokens, in USD. */
	private const PRICE_PER_MILLION_USD = 0.30;

	private TranslationRepository $repository;

	public function __construct( TranslationRepository $repository ) {
		$this->repository = $repository;
	}

	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$stats = $this->repository->stats();

		echo '<hr />';
		echo '<h2>' . esc_html__( 'Statistics', 'abi-translator' ) . '</h2>';

		if ( (int) $stats['total'] === 0 ) {
			echo '<p>' . esc_html__( 'No translations stored yet.', 'abi-translator' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped" style="max-width:640px;">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Language', 'abi-translator' ) . '</th>';
		echo '<th>' . esc_html__( 'Object type', 'abi-translator' ) . '</th>';
		echo '<th style="text-align:end;">' . esc_html__( 'Translations', 'abi-translator' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $stats['rows'] as $row ) {
			printf(
				'<tr><td>%s</td><td>%s</td><td style="text-align:end;">%s</td></tr>',
				esc_html( $row['lang'] ),
				esc_html( $row['object_type'] ),
				esc_html( number_format_i18n( $row['count'] ) )
			);
		}

		printf(
			'<tr><th colspan="2">%s</th><th style="text-align:end;">%s</th></tr>',
			esc_html__( 'Total', 'abi-translator' ),
			esc_html( number_format_i18n( $stats['total'] ) )
		);
		echo '</tbody></table>';

		$this->render_cost( (int) $stats['total'] );
	}

	private function render_cost( int $total_rows ): void {
		/** @var int $avg_tokens */
		$avg_tokens = (int) apply_filters( 'abi_translator_avg_tokens_per_row', self::AVG_TOKENS_PER_ROW );
		/** @var float $price */
		$price = (float) apply_filters( 'abi_translator_price_per_million_usd', self::PRICE_PER_MILLION_USD );

		$estimated_tokens = $total_rows * max( 0, $avg_tokens );
		$estimated_cost   = ( $estimated_tokens / 1000000 ) * max( 0.0, $price );

		echo '<h2>' . esc_html__( 'Estimated cost', 'abi-translator' ) . '</h2>';
		echo '<p><strong>$' . esc_html( number_format( $estimated_cost, 2 ) ) . '</strong> '
			. esc_html__( '(rough estimate, not a billed amount)', 'abi-translator' ) . '</p>';

		echo '<p class="description">';
		printf(
			/* translators: 1: rows, 2: avg tokens, 3: price per million */
			esc_html__( 'Formula: %1$s rows × %2$s tokens/row ÷ 1,000,000 × $%3$s per 1M tokens. Tokens are not tracked per request, so this uses fixed assumptions (filterable).', 'abi-translator' ),
			esc_html( number_format_i18n( $total_rows ) ),
			esc_html( number_format_i18n( $avg_tokens ) ),
			esc_html( number_format( $price, 2 ) )
		);
		echo '</p>';
	}
}
