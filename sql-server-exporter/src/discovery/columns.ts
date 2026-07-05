import type { ConnectionService } from "../services/connection";
import type { ColumnSchema } from "../types";

interface ColumnRow {
  COLUMN_NAME: string;
  DATA_TYPE: string;
  IS_NULLABLE: string;
  CHARACTER_MAXIMUM_LENGTH: number | null;
  NUMERIC_PRECISION: number | null;
  NUMERIC_SCALE: number | null;
}

export async function discoverColumns(
  connection: ConnectionService,
  database: string,
  schema: string,
  table: string
): Promise<ColumnSchema[]> {
  const result = await connection.queryInDatabase<ColumnRow>(
    database,
    `
    SELECT
      COLUMN_NAME,
      DATA_TYPE,
      IS_NULLABLE,
      CHARACTER_MAXIMUM_LENGTH,
      NUMERIC_PRECISION,
      NUMERIC_SCALE
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema
      AND TABLE_NAME = @table
    ORDER BY ORDINAL_POSITION
    `,
    { schema, table }
  );

  return result.recordset.map((row) => ({
    name: row.COLUMN_NAME,
    type: row.DATA_TYPE.toLowerCase(),
    nullable: row.IS_NULLABLE === "YES",
    maxLength: row.CHARACTER_MAXIMUM_LENGTH,
    precision: row.NUMERIC_PRECISION,
    scale: row.NUMERIC_SCALE,
  }));
}
