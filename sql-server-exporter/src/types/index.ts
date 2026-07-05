export interface CliOptions {
  connection: string;
  output: string;
  batchSize: number;
  mediaConfig?: string;
}

export interface ColumnSchema {
  name: string;
  type: string;
  nullable: boolean;
  maxLength?: number | null;
  precision?: number | null;
  scale?: number | null;
}

export interface TableSchema {
  name: string;
  schema: string;
  qualifiedName: string;
  rowCount: number;
  columns: ColumnSchema[];
  primaryKeys: string[];
  foreignKeys: ForeignKeySchema[];
}

export interface ForeignKeySchema {
  name: string;
  columns: string[];
  referencedTable: string;
  referencedSchema: string;
  referencedColumns: string[];
}

export interface DatabaseSchema {
  name: string;
  tables: TableSchema[];
}

export interface SchemaExport {
  exportedAt: string;
  databases: DatabaseSchema[];
}

export interface RelationEntry {
  database: string;
  fromTable: string;
  fromSchema: string;
  fromColumn: string;
  toTable: string;
  toSchema: string;
  toColumn: string;
  constraintName: string;
}

export interface RelationsExport {
  exportedAt: string;
  relations: RelationEntry[];
}

export interface MediaColumnConfig {
  database?: string;
  table: string;
  schema?: string;
  column: string;
}

export interface MediaConfig {
  mediaColumns: MediaColumnConfig[];
}

export interface MediaManifest {
  exportedAt: string;
  files: string[];
}

export interface TableRef {
  database: string;
  schema: string;
  name: string;
  qualifiedName: string;
  columns: ColumnSchema[];
  primaryKeys: string[];
  rowCount: number;
}

export interface ExportError {
  timestamp: string;
  database: string;
  table: string;
  phase: string;
  message: string;
}
