<?php

namespace AsreKhodro\Theme\AcfBlocks\CinfoTable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class View {

	/**
	 * @param array<string, mixed> $fields
	 * @return array<string, mixed>
	 */
	public static function context( array $fields, ?\Timber\Post $post = null ): array {
		unset( $post );

		$title = trim( (string) ( $fields['title'] ?? '' ) );
		$col1  = trim( (string) ( $fields['col_1_label'] ?? '' ) ) ?: 'گروه';
		$col2  = trim( (string) ( $fields['col_2_label'] ?? '' ) ) ?: 'مشخصه';
		$col3  = trim( (string) ( $fields['col_3_label'] ?? '' ) ) ?: 'مقدار';

		$groups             = self::normalize_groups( (array) ( $fields['groups'] ?? array() ) );
		$show_group_headers = count( $groups ) > 1;
		$table_rows         = array();

		foreach ( $groups as $group ) {
			if ( $show_group_headers && $group['name'] !== '' ) {
				$table_rows[] = array(
					'type'  => 'group',
					'label' => $group['name'],
				);
			}

			$is_first_item_in_group = true;
			foreach ( $group['items'] as $item ) {
				$row = self::build_item_row( $group['name'], $item, $show_group_headers, $is_first_item_in_group );
				if ( $row !== null ) {
					$table_rows[] = $row;
					$is_first_item_in_group = false;
				}
			}
		}

		$default_anchor = (string) ( Block::config()['default_anchor'] ?? 'specs' );

		return array(
			'table_title'           => $title,
			'table_col_1_label'     => $col1,
			'table_col_2_label'     => $col2,
			'table_col_3_label'     => $col3,
			'table_rows'            => $table_rows,
			'table_default_anchor'  => $default_anchor,
			'table_has_content'     => $title !== '' || $table_rows !== array(),
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $rows
	 * @return list<array{name: string, items: list<array{title: string, value: string, note: string}>}>
	 */
	private static function normalize_groups( array $rows ): array {
		$groups = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$name  = trim( (string) ( $row['group_name'] ?? '' ) );
			$items = array();

			foreach ( (array) ( $row['items'] ?? array() ) as $item_row ) {
				if ( ! is_array( $item_row ) ) {
					continue;
				}

				$item = array(
					'title' => trim( (string) ( $item_row['item_title'] ?? '' ) ),
					'value' => trim( (string) ( $item_row['item_value'] ?? '' ) ),
					'note'  => trim( (string) ( $item_row['item_note'] ?? '' ) ),
				);

				if ( $item['title'] === '' && $item['value'] === '' && $item['note'] === '' ) {
					continue;
				}

				$items[] = $item;
			}

			if ( $name === '' && $items === array() ) {
				continue;
			}

			$groups[] = array(
				'name'  => $name,
				'items' => $items,
			);
		}

		return $groups;
	}

	/**
	 * @param array{title: string, value: string, note: string} $item
	 * @return array{type: string, col1: string, col2: string, col3: string}|null
	 */
	private static function build_item_row( string $group_name, array $item, bool $show_group_headers, bool $is_first_in_group ): ?array {
		if ( $show_group_headers ) {
			$col1 = '';
			$col2 = $item['title'];
			$col3 = $item['value'] !== '' ? $item['value'] : $item['note'];
		} elseif ( $group_name !== '' ) {
			$col1 = $is_first_in_group ? $group_name : '';
			$col2 = $item['title'];
			$col3 = $item['value'] !== '' ? $item['value'] : $item['note'];
		} else {
			$col1 = $item['title'];
			$col2 = $item['value'];
			$col3 = $item['note'];
		}

		if ( $col1 === '' && $col2 === '' && $col3 === '' ) {
			return null;
		}

		return array(
			'type' => 'item',
			'col1' => $col1,
			'col2' => $col2,
			'col3' => $col3,
		);
	}
}
