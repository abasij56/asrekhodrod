import * as fs from "fs";
import * as path from "path";
import { parseCliOptions } from "./cli";
import { discoverSchema } from "./discovery/schema";
import { exportSchema } from "./exporters/schema-exporter";
import { exportRelations } from "./exporters/relations-exporter";
import { DataExporter } from "./exporters/data-exporter";
import { buildMediaManifest, loadMediaConfig } from "./exporters/media-manifest";
import { ConnectionService } from "./services/connection";
import { Logger } from "./services/logger";

async function main(): Promise<void> {
  const options = parseCliOptions(process.argv);
  const outputDir = path.resolve(options.output);

  fs.mkdirSync(outputDir, { recursive: true });

  const logger = new Logger(outputDir);
  logger.openErrorLog();

  const connection = new ConnectionService(options.connection, logger);

  try {
    const { schema, relations } = await discoverSchema(connection, logger);

    const schemaPath = await exportSchema(outputDir, schema);
    logger.info(`Schema written to ${schemaPath}`);

    const relationsPath = await exportRelations(outputDir, relations);
    logger.info(`Relations written to ${relationsPath}`);

    const dataExporter = new DataExporter(connection, logger, outputDir, options.batchSize);
    await dataExporter.exportAll(schema);

    const mediaConfig = await loadMediaConfig(options.mediaConfig);
    await buildMediaManifest(connection, logger, outputDir, schema, mediaConfig);

    logger.info("Export complete.");
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    logger.error(`Export failed: ${message}`);
    logger.logExportError({
      timestamp: new Date().toISOString(),
      database: "*",
      table: "*",
      phase: "export",
      message,
    });
    process.exitCode = 1;
  } finally {
    logger.closeErrorLog();
    await connection.close();
  }
}

main().catch((error) => {
  console.error("[ERROR] Unhandled failure:", error);
  process.exit(1);
});
