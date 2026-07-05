import * as fs from "fs";
import * as path from "path";
import type { ConnectionService } from "../services/connection";
import { bracketDatabase } from "../services/connection";
import type { Logger } from "../services/logger";
import type { MediaConfig, MediaManifest, SchemaExport } from "../types";
import { qualifiedTableLabel } from "../utils/paths";
import { writeJsonFile } from "../utils/json-stream";

export async function loadMediaConfig(configPath?: string): Promise<MediaConfig> {
  if (!configPath) {
    return { mediaColumns: [] };
  }

  const raw = await fs.promises.readFile(configPath, "utf8");
  const parsed = JSON.parse(raw) as MediaConfig;

  if (!parsed.mediaColumns || !Array.isArray(parsed.mediaColumns)) {
    throw new Error("Media config must contain a mediaColumns array.");
  }

  return parsed;
}

export async function buildMediaManifest(
  connection: ConnectionService,
  logger: Logger,
  outputDir: string,
  schema: SchemaExport,
  mediaConfig: MediaConfig
): Promise<string | null> {
  if (mediaConfig.mediaColumns.length === 0) {
    logger.info("No media columns configured; skipping media manifest.");
    return null;
  }

  logger.info("Building media manifest...");
  const files = new Set<string>();

  for (const entry of mediaConfig.mediaColumns) {
    const targets = resolveMediaTargets(schema, entry);

    for (const target of targets) {
      const label = `${target.database}.${target.qualifiedName}.${entry.column}`;
      logger.info(`Scanning media paths in ${label}`);

      try {
        const paths = await collectDistinctPaths(
          connection,
          target.database,
          target.schema,
          target.name,
          entry.column
        );

        for (const filePath of paths) {
          if (filePath) {
            files.add(filePath);
          }
        }
      } catch (error) {
        const message = error instanceof Error ? error.message : String(error);
        logger.logExportError({
          timestamp: new Date().toISOString(),
          database: target.database,
          table: target.qualifiedName,
          phase: "media-manifest",
          message: `Column ${entry.column}: ${message}`,
        });
      }
    }
  }

  const manifest: MediaManifest = {
    exportedAt: new Date().toISOString(),
    files: Array.from(files).sort((a, b) => a.localeCompare(b)),
  };

  const filePath = path.join(outputDir, "media-manifest.json");
  await writeJsonFile(filePath, manifest);
  logger.info(`Media manifest written (${manifest.files.length} file path(s)).`);
  return filePath;
}

function resolveMediaTargets(
  schema: SchemaExport,
  entry: MediaConfig["mediaColumns"][number]
): Array<{ database: string; schema: string; name: string; qualifiedName: string }> {
  const databases = entry.database
    ? schema.databases.filter((db) => db.name === entry.database)
    : schema.databases;

  const targets: Array<{ database: string; schema: string; name: string; qualifiedName: string }> =
    [];

  for (const database of databases) {
    for (const table of database.tables) {
      const schemaMatches = entry.schema ? table.schema === entry.schema : true;
      const tableMatches = table.name === entry.table;

      if (schemaMatches && tableMatches) {
        targets.push({
          database: database.name,
          schema: table.schema,
          name: table.name,
          qualifiedName: table.qualifiedName ?? qualifiedTableLabel(table.schema, table.name),
        });
      }
    }
  }

  return targets;
}

async function collectDistinctPaths(
  connection: ConnectionService,
  database: string,
  schema: string,
  table: string,
  column: string
): Promise<string[]> {
  const safeColumn = `[${column.replace(/]/g, "]]")}]`;
  const qualifiedFrom = bracketDatabase(database, schema, table);
  const collected: string[] = [];
  let offset = 0;
  const batchSize = 1000;

  while (true) {
    const result = await connection.query<Record<string, unknown>>(`
      SELECT DISTINCT ${safeColumn} AS media_path
      FROM ${qualifiedFrom}
      WHERE ${safeColumn} IS NOT NULL
        AND LTRIM(RTRIM(CAST(${safeColumn} AS NVARCHAR(MAX)))) <> ''
      ORDER BY ${safeColumn}
      OFFSET ${offset} ROWS
      FETCH NEXT ${batchSize} ROWS ONLY
    `);

    if (result.recordset.length === 0) {
      break;
    }

    for (const row of result.recordset) {
      const value = row.media_path;
      if (typeof value === "string" && value.trim()) {
        collected.push(value.trim());
      }
    }

    if (result.recordset.length < batchSize) {
      break;
    }

    offset += batchSize;
  }

  return collected;
}
