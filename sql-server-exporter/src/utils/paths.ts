import * as path from "path";

/** Sanitize a segment for use as a folder or file name on disk. */
export function sanitizePathSegment(value: string): string {
  return value.replace(/[<>:"/\\|?*\x00-\x1f]/g, "_").trim() || "unnamed";
}

export function tableFileName(schema: string, tableName: string): string {
  if (schema.toLowerCase() === "dbo") {
    return `${sanitizePathSegment(tableName)}.json`;
  }
  return `${sanitizePathSegment(schema)}_${sanitizePathSegment(tableName)}.json`;
}

export function databaseDataDir(outputDir: string, databaseName: string): string {
  return path.join(outputDir, "data", sanitizePathSegment(databaseName));
}

export function qualifiedTableLabel(schema: string, tableName: string): string {
  return schema.toLowerCase() === "dbo" ? tableName : `${schema}.${tableName}`;
}
