import * as path from "path";
import type { RelationEntry, RelationsExport } from "../types";
import { writeJsonFile } from "../utils/json-stream";

export async function exportRelations(
  outputDir: string,
  relations: RelationEntry[]
): Promise<string> {
  const payload: RelationsExport = {
    exportedAt: new Date().toISOString(),
    relations: relations.map((relation) => ({
      database: relation.database,
      fromTable: relation.fromTable,
      fromSchema: relation.fromSchema,
      fromColumn: relation.fromColumn,
      toTable: relation.toTable,
      toSchema: relation.toSchema,
      toColumn: relation.toColumn,
      constraintName: relation.constraintName,
    })),
  };

  const filePath = path.join(outputDir, "relations.json");
  await writeJsonFile(filePath, payload);
  return filePath;
}
