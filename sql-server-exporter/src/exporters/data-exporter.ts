import * as fs from "fs";
import * as path from "path";
import type { ConnectionService } from "../services/connection";
import { bracketDatabase } from "../services/connection";
import type { Logger } from "../services/logger";
import type { SchemaExport, TableRef } from "../types";
import { databaseDataDir, qualifiedTableLabel, tableFileName } from "../utils/paths";
import { convertRowForJson } from "../utils/type-converter";
import { JsonArrayStreamWriter } from "../utils/json-stream";

export class DataExporter {
  constructor(
    private readonly connection: ConnectionService,
    private readonly logger: Logger,
    private readonly outputDir: string,
    private readonly batchSize: number
  ) {}

  async exportAll(schema: SchemaExport): Promise<void> {
    this.logger.info("Exporting table data...");

    for (const database of schema.databases) {
      for (const table of database.tables) {
        const tableRef: TableRef = {
          database: database.name,
          schema: table.schema,
          name: table.name,
          qualifiedName: table.qualifiedName,
          columns: table.columns,
          primaryKeys: table.primaryKeys,
          rowCount: table.rowCount,
        };

        await this.exportTable(tableRef);
      }
    }
  }

  async exportTable(table: TableRef): Promise<void> {
    const label = `${table.database}.${table.qualifiedName}`;
    const totalRows = table.rowCount;
    const totalBatches = Math.max(1, Math.ceil(totalRows / this.batchSize));

    this.logger.info(`Exporting ${label} (${totalRows} rows)`);

    const outputPath = path.join(
      databaseDataDir(this.outputDir, table.database),
      tableFileName(table.schema, table.name)
    );

    await fs.promises.mkdir(path.dirname(outputPath), { recursive: true });

    const columnTypes = new Map(table.columns.map((col) => [col.name, col.type]));
    const orderColumns = this.resolveOrderColumns(table);
    const qualifiedFrom = bracketDatabase(table.database, table.schema, table.name);
    const writer = new JsonArrayStreamWriter(outputPath);

    let offset = 0;
    let batchNumber = 0;
    let exportedRows = 0;

    try {
      while (true) {
        batchNumber += 1;
        const batchLabel = totalRows > 0 ? `${batchNumber}/${totalBatches}` : `${batchNumber}`;
        this.logger.info(`Exporting batch ${batchLabel} for ${label}`);

        const rows = await this.fetchBatch(
          table.database,
          qualifiedFrom,
          orderColumns,
          offset,
          this.batchSize
        );

        if (rows.length === 0) {
          break;
        }

        for (const row of rows) {
          await writer.writeRow(convertRowForJson(row, columnTypes));
          exportedRows += 1;
        }

        if (rows.length < this.batchSize) {
          break;
        }

        offset += this.batchSize;
      }

      await writer.close();
      this.logger.info(`Finished ${label} (${exportedRows} rows written)`);
    } catch (error) {
      try {
        await writer.close();
      } catch {
        // Ignore close errors after a failed export.
      }

      const message = error instanceof Error ? error.message : String(error);
      this.logger.logExportError({
        timestamp: new Date().toISOString(),
        database: table.database,
        table: table.qualifiedName,
        phase: "data-export",
        message,
      });
    }
  }

  private resolveOrderColumns(table: TableRef): string[] {
    if (table.primaryKeys.length > 0) {
      return table.primaryKeys;
    }
    if (table.columns.length > 0) {
      return [table.columns[0].name];
    }
    throw new Error(`Table ${table.qualifiedName} has no columns to order by.`);
  }

  private async fetchBatch(
    database: string,
    qualifiedFrom: string,
    orderColumns: string[],
    offset: number,
    batchSize: number
  ): Promise<Record<string, unknown>[]> {
    const orderBy = orderColumns
      .map((column) => `[${column.replace(/]/g, "]]")}]`)
      .join(", ");

    const result = await this.connection.query<Record<string, unknown>>(`
      SELECT *
      FROM ${qualifiedFrom}
      ORDER BY ${orderBy}
      OFFSET ${offset} ROWS
      FETCH NEXT ${batchSize} ROWS ONLY
    `);

    return result.recordset;
  }
}
