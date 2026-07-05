import type { ConnectionService } from "../services/connection";
import type { DatabaseSchema, RelationEntry, SchemaExport, TableSchema } from "../types";
import { qualifiedTableLabel } from "../utils/paths";
import { discoverDatabases } from "./databases";
import { discoverTables } from "./tables";
import { discoverColumns } from "./columns";
import { discoverPrimaryKeys, discoverForeignKeys, getRowCount } from "./keys";
import type { Logger } from "../services/logger";

export async function discoverSchema(
  connection: ConnectionService,
  logger: Logger
): Promise<{ schema: SchemaExport; relations: RelationEntry[] }> {
  logger.info("Discovering databases...");
  const databases = await discoverDatabases(connection);
  logger.info(`Found ${databases.length} accessible database(s).`);

  const databaseSchemas: DatabaseSchema[] = [];
  const relations: RelationEntry[] = [];

  for (const database of databases) {
    logger.info(`Discovering tables in ${database}...`);
    const tables = await discoverTables(connection, database);
    const tableSchemas: TableSchema[] = [];

    for (const table of tables) {
      const qualifiedName = qualifiedTableLabel(table.schema, table.name);

      try {
        const [columns, primaryKeys, foreignKeys, rowCount] = await Promise.all([
          discoverColumns(connection, database, table.schema, table.name),
          discoverPrimaryKeys(connection, database, table.schema, table.name),
          discoverForeignKeys(connection, database, table.schema, table.name),
          getRowCount(connection, database, table.schema, table.name),
        ]);

        tableSchemas.push({
          name: table.name,
          schema: table.schema,
          qualifiedName,
          rowCount,
          columns,
          primaryKeys,
          foreignKeys,
        });

        for (const fk of foreignKeys) {
          const maxPairs = Math.min(fk.columns.length, fk.referencedColumns.length);
          for (let i = 0; i < maxPairs; i += 1) {
            relations.push({
              database,
              fromTable: table.name,
              fromSchema: table.schema,
              fromColumn: fk.columns[i],
              toTable: fk.referencedTable,
              toSchema: fk.referencedSchema,
              toColumn: fk.referencedColumns[i],
              constraintName: fk.name,
            });
          }
        }
      } catch (error) {
        const message = error instanceof Error ? error.message : String(error);
        logger.logExportError({
          timestamp: new Date().toISOString(),
          database,
          table: qualifiedName,
          phase: "schema-discovery",
          message,
        });
      }
    }

    databaseSchemas.push({
      name: database,
      tables: tableSchemas,
    });
  }

  const schema: SchemaExport = {
    exportedAt: new Date().toISOString(),
    databases: databaseSchemas.map((db) => ({
      name: db.name,
      tables: db.tables.map((table) => ({
        name: table.name,
        schema: table.schema,
        qualifiedName: table.qualifiedName,
        rowCount: table.rowCount,
        columns: table.columns,
        primaryKeys: table.primaryKeys,
        foreignKeys: table.foreignKeys,
      })),
    })),
  };

  return { schema, relations };
}
