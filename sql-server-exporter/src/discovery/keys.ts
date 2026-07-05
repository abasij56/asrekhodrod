import type { ConnectionService } from "../services/connection";
import type { ForeignKeySchema } from "../types";

interface PrimaryKeyRow {
  COLUMN_NAME: string;
  ORDINAL_POSITION: number;
}

interface ForeignKeyRow {
  CONSTRAINT_NAME: string;
  COLUMN_NAME: string;
  ORDINAL_POSITION: number;
  REFERENCED_TABLE_SCHEMA: string;
  REFERENCED_TABLE_NAME: string;
  REFERENCED_COLUMN_NAME: string;
}

export async function discoverPrimaryKeys(
  connection: ConnectionService,
  database: string,
  schema: string,
  table: string
): Promise<string[]> {
  const result = await connection.queryInDatabase<PrimaryKeyRow>(
    database,
    `
    SELECT
      kcu.COLUMN_NAME,
      kcu.ORDINAL_POSITION
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc
    INNER JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
      ON tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
      AND tc.TABLE_SCHEMA = kcu.TABLE_SCHEMA
      AND tc.TABLE_NAME = kcu.TABLE_NAME
    WHERE tc.CONSTRAINT_TYPE = 'PRIMARY KEY'
      AND tc.TABLE_SCHEMA = @schema
      AND tc.TABLE_NAME = @table
    ORDER BY kcu.ORDINAL_POSITION
    `,
    { schema, table }
  );

  return result.recordset.map((row) => row.COLUMN_NAME);
}

export async function discoverForeignKeys(
  connection: ConnectionService,
  database: string,
  schema: string,
  table: string
): Promise<ForeignKeySchema[]> {
  const result = await connection.queryInDatabase<ForeignKeyRow>(
    database,
    `
    SELECT
      fk.name AS CONSTRAINT_NAME,
      COL_NAME(fkc.parent_object_id, fkc.parent_column_id) AS COLUMN_NAME,
      fkc.constraint_column_id AS ORDINAL_POSITION,
      OBJECT_SCHEMA_NAME(fkc.referenced_object_id) AS REFERENCED_TABLE_SCHEMA,
      OBJECT_NAME(fkc.referenced_object_id) AS REFERENCED_TABLE_NAME,
      COL_NAME(fkc.referenced_object_id, fkc.referenced_column_id) AS REFERENCED_COLUMN_NAME
    FROM sys.foreign_keys fk
    INNER JOIN sys.foreign_key_columns fkc
      ON fk.object_id = fkc.constraint_object_id
    INNER JOIN sys.tables t
      ON fk.parent_object_id = t.object_id
    INNER JOIN sys.schemas s
      ON t.schema_id = s.schema_id
    WHERE s.name = @schema
      AND t.name = @table
    ORDER BY fk.name, fkc.constraint_column_id
    `,
    { schema, table }
  );

  const grouped = new Map<string, ForeignKeySchema>();

  for (const row of result.recordset) {
    const existing = grouped.get(row.CONSTRAINT_NAME);
    if (!existing) {
      grouped.set(row.CONSTRAINT_NAME, {
        name: row.CONSTRAINT_NAME,
        columns: [row.COLUMN_NAME],
        referencedSchema: row.REFERENCED_TABLE_SCHEMA,
        referencedTable: row.REFERENCED_TABLE_NAME,
        referencedColumns: [row.REFERENCED_COLUMN_NAME],
      });
      continue;
    }

    existing.columns.push(row.COLUMN_NAME);
    existing.referencedColumns.push(row.REFERENCED_COLUMN_NAME);
  }

  return Array.from(grouped.values());
}

export async function getRowCount(
  connection: ConnectionService,
  database: string,
  schema: string,
  table: string
): Promise<number> {
  const result = await connection.queryInDatabase<{ row_count: number }>(
    database,
    `
    SELECT SUM(p.rows) AS row_count
    FROM sys.partitions p
    INNER JOIN sys.tables t ON p.object_id = t.object_id
    INNER JOIN sys.schemas s ON t.schema_id = s.schema_id
    WHERE s.name = @schema
      AND t.name = @table
      AND p.index_id IN (0, 1)
    `,
    { schema, table }
  );

  const count = result.recordset[0]?.row_count;
  return typeof count === "number" ? count : 0;
}
