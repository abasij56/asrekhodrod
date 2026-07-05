const STRING_TYPES = new Set([
  "char",
  "nchar",
  "varchar",
  "nvarchar",
  "text",
  "ntext",
  "xml",
  "uniqueidentifier",
  "sql_variant",
  "hierarchyid",
]);

const NUMBER_TYPES = new Set([
  "tinyint",
  "smallint",
  "int",
  "bigint",
  "decimal",
  "numeric",
  "float",
  "real",
  "money",
  "smallmoney",
]);

const DATE_TYPES = new Set(["date"]);
const DATETIME_TYPES = new Set(["datetime", "datetime2", "smalldatetime"]);
const DATETIMEOFFSET_TYPES = new Set(["datetimeoffset"]);
const TIME_TYPES = new Set(["time"]);
const BOOLEAN_TYPES = new Set(["bit"]);
const BINARY_TYPES = new Set(["binary", "varbinary", "image", "timestamp", "rowversion"]);

export function normalizeSqlType(dataType: string): string {
  return dataType.toLowerCase().replace(/\s+/g, "");
}

export function convertValueForJson(value: unknown, sqlType: string): unknown {
  if (value === null || value === undefined) {
    return null;
  }

  const type = normalizeSqlType(sqlType);

  if (BOOLEAN_TYPES.has(type)) {
    return Boolean(value);
  }

  if (NUMBER_TYPES.has(type)) {
    const num = typeof value === "number" ? value : Number(value);
    return Number.isFinite(num) ? num : null;
  }

  if (DATE_TYPES.has(type)) {
    return formatDateOnly(value);
  }

  if (DATETIME_TYPES.has(type) || DATETIMEOFFSET_TYPES.has(type)) {
    return formatDateTime(value);
  }

  if (TIME_TYPES.has(type)) {
    return formatTime(value);
  }

  if (BINARY_TYPES.has(type)) {
    return bufferToBase64(value);
  }

  if (STRING_TYPES.has(type)) {
    return String(value);
  }

  if (value instanceof Date) {
    return value.toISOString();
  }

  if (Buffer.isBuffer(value)) {
    return value.toString("base64");
  }

  if (typeof value === "object") {
    return JSON.parse(JSON.stringify(value));
  }

  return value;
}

export function convertRowForJson(
  row: Record<string, unknown>,
  columnTypes: Map<string, string>
): Record<string, unknown> {
  const converted: Record<string, unknown> = {};

  for (const [column, value] of Object.entries(row)) {
    const sqlType = columnTypes.get(column) ?? "nvarchar";
    converted[column] = convertValueForJson(value, sqlType);
  }

  return converted;
}

function formatDateOnly(value: unknown): string {
  const date = toDate(value);
  if (!date) return String(value);
  return date.toISOString().slice(0, 10);
}

function formatDateTime(value: unknown): string {
  const date = toDate(value);
  if (!date) return String(value);
  return date.toISOString();
}

function formatTime(value: unknown): string {
  if (typeof value === "string") {
    return value;
  }
  const date = toDate(value);
  if (!date) return String(value);
  return date.toISOString().slice(11, 23);
}

function toDate(value: unknown): Date | null {
  if (value instanceof Date && !Number.isNaN(value.getTime())) {
    return value;
  }
  if (typeof value === "string" || typeof value === "number") {
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? null : date;
  }
  return null;
}

function bufferToBase64(value: unknown): string | null {
  if (Buffer.isBuffer(value)) {
    return value.toString("base64");
  }
  if (value instanceof Uint8Array) {
    return Buffer.from(value).toString("base64");
  }
  return String(value);
}
