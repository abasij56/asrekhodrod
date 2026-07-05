import type { ConnectionService } from "../services/connection";

export interface DiscoveredTable {
  schema: string;
  name: string;
}

export async function discoverTables(
  connection: ConnectionService,
  database: string
): Promise<DiscoveredTable[]> {
  const result = await connection.queryInDatabase<{ TABLE_SCHEMA: string; TABLE_NAME: string }>(
    database,
    `
    SELECT TABLE_SCHEMA, TABLE_NAME
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_TYPE = 'BASE TABLE'
    ORDER BY TABLE_SCHEMA, TABLE_NAME
    `
  );

  return result.recordset.map((row) => ({
    schema: row.TABLE_SCHEMA,
    name: row.TABLE_NAME,
  }));
}
