import type { ConnectionService } from "../services/connection";

const SYSTEM_DATABASES = new Set([
  "master",
  "tempdb",
  "model",
  "msdb",
  "resource",
  "distribution",
]);

export async function discoverDatabases(connection: ConnectionService): Promise<string[]> {
  const result = await connection.query<{ name: string }>(`
    SELECT name
    FROM sys.databases
    WHERE state_desc = 'ONLINE'
      AND HAS_DBACCESS(name) = 1
      AND DATABASEPROPERTYEX(name, 'Updateability') = 'READ_WRITE'
    ORDER BY name
  `);

  return result.recordset
    .map((row) => row.name)
    .filter((name) => !SYSTEM_DATABASES.has(name.toLowerCase()));
}
