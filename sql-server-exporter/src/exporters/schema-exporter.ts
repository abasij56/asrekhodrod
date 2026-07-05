import * as path from "path";
import type { SchemaExport } from "../types";
import { writeJsonFile } from "../utils/json-stream";

export async function exportSchema(outputDir: string, schema: SchemaExport): Promise<string> {
  const filePath = path.join(outputDir, "schema.json");
  await writeJsonFile(filePath, schema);
  return filePath;
}
