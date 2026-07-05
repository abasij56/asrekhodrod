import { execFileSync } from "node:child_process";
import * as fs from "node:fs";
import * as path from "node:path";
import { fileURLToPath } from "node:url";
import {
  CROSS_DATABASE_RELATIONS,
  DATABASE_ROLES,
  DB_ENTITY_GROUPS,
  DB_IMPORTANT_NOTES,
  DB_LOGICAL_RELATIONS,
  DB_WORDPRESS_NOTES,
} from "./db-docs-config.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const projectRoot = path.resolve(__dirname, "..");

function parseArgs(argv) {
  const args = argv.slice(2);
  const get = (name, fallback) => {
    const i = args.indexOf(`--${name}`);
    if (i >= 0 && args[i + 1]) return args[i + 1];
    return fallback;
  };
  return {
    server: get("server", "."),
    database: get("database", null),
    output: path.resolve(get("output", path.join(projectRoot, "docs"))),
    all: args.includes("--all"),
    fresh: args.includes("--fresh"),
  };
}

function listAsreKhodroDatabases(server) {
  const stdout = execFileSync(
    "sqlcmd",
    [
      "-S", server,
      "-Q", "SET NOCOUNT ON; SELECT name FROM sys.databases WHERE name LIKE '%Asre%' OR name LIKE '%Khodro%' ORDER BY name",
      "-W", "-h", "-1",
    ],
    { encoding: "utf8" }
  );
  return stdout
    .split(/\r?\n/)
    .map((l) => l.trim())
    .filter(Boolean)
    .filter((l) => !/^\(\d+ rows affected\)/.test(l));
}

function runSql(server, database, query) {
  const stdout = execFileSync(
    "sqlcmd",
    ["-S", server, "-d", database, "-Q", query, "-W", "-s", "|", "-w", "65535"],
    { encoding: "utf8", maxBuffer: 128 * 1024 * 1024 }
  );
  return parsePipeTable(stdout);
}

function parsePipeTable(text) {
  const lines = text.split(/\r?\n/).map((l) => l.trim()).filter(Boolean);
  const sep = lines.findIndex((l) => /^-+\|/.test(l));
  if (sep < 1) return [];
  const headers = lines[0].split("|").map((h) => h.trim());
  const rows = [];
  for (let i = sep + 1; i < lines.length; i += 1) {
    if (/^\(\d+ rows affected\)/.test(lines[i])) break;
    const vals = lines[i].split("|").map((v) => v.trim());
    const row = {};
    headers.forEach((h, idx) => { row[h] = vals[idx] ?? ""; });
    rows.push(row);
  }
  return rows;
}

function formatType(row) {
  const type = row.DATA_TYPE;
  if (["nvarchar", "varchar", "varbinary"].includes(type)) {
    const len = row.CHARACTER_MAXIMUM_LENGTH;
    if (!len || len === "NULL" || len === "-1") return `${type}(MAX)`;
    return `${type}(${len})`;
  }
  if (type === "decimal" || type === "numeric") {
    return `${type}(${row.NUMERIC_PRECISION},${row.NUMERIC_SCALE})`;
  }
  return type;
}

function formatNumber(value) {
  const n = Number(value);
  return Number.isNaN(n) ? value : n.toLocaleString("en-US");
}

function inferGroup(database, tableName) {
  const groups = DB_ENTITY_GROUPS[database] ?? {};
  for (const [group, names] of Object.entries(groups)) {
    if (names.includes(tableName)) return group;
  }
  return "Other";
}

function discoverDatabase(server, database) {
  const tableRows = runSql(server, database, `
SET NOCOUNT ON;
SELECT s.name AS schema_name, t.name AS table_name, SUM(p.rows) AS row_count
FROM sys.tables t
INNER JOIN sys.schemas s ON t.schema_id = s.schema_id
INNER JOIN sys.partitions p ON t.object_id = p.object_id
WHERE p.index_id IN (0, 1) AND t.is_ms_shipped = 0
GROUP BY s.name, t.name
ORDER BY s.name, t.name;
`);

  const columnRows = runSql(server, database, `
SET NOCOUNT ON;
SELECT c.TABLE_SCHEMA, c.TABLE_NAME, c.ORDINAL_POSITION, c.COLUMN_NAME,
  c.DATA_TYPE, c.CHARACTER_MAXIMUM_LENGTH, c.NUMERIC_PRECISION, c.NUMERIC_SCALE,
  c.IS_NULLABLE, CASE WHEN pk.COLUMN_NAME IS NOT NULL THEN 1 ELSE 0 END AS is_pk
FROM INFORMATION_SCHEMA.COLUMNS c
LEFT JOIN (
  SELECT kcu.TABLE_SCHEMA, kcu.TABLE_NAME, kcu.COLUMN_NAME
  FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc
  INNER JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
    ON tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
    AND tc.TABLE_SCHEMA = kcu.TABLE_SCHEMA AND tc.TABLE_NAME = kcu.TABLE_NAME
  WHERE tc.CONSTRAINT_TYPE = 'PRIMARY KEY'
) pk ON c.TABLE_SCHEMA = pk.TABLE_SCHEMA AND c.TABLE_NAME = pk.TABLE_NAME
  AND c.COLUMN_NAME = pk.COLUMN_NAME
WHERE c.TABLE_NAME IN (SELECT name FROM sys.tables WHERE is_ms_shipped = 0)
ORDER BY c.TABLE_SCHEMA, c.TABLE_NAME, c.ORDINAL_POSITION;
`);

  const fkRows = runSql(server, database, `
SET NOCOUNT ON;
SELECT fk.name AS fk_name,
  OBJECT_SCHEMA_NAME(fk.parent_object_id) AS from_schema,
  OBJECT_NAME(fk.parent_object_id) AS from_table,
  COL_NAME(fkc.parent_object_id, fkc.parent_column_id) AS from_column,
  OBJECT_SCHEMA_NAME(fkc.referenced_object_id) AS to_schema,
  OBJECT_NAME(fkc.referenced_object_id) AS to_table,
  COL_NAME(fkc.referenced_object_id, fkc.referenced_column_id) AS to_column
FROM sys.foreign_keys fk
INNER JOIN sys.foreign_key_columns fkc ON fk.object_id = fkc.constraint_object_id
ORDER BY from_table, fk_name;
`);

  const tables = tableRows.map((r) => ({
    schema: r.schema_name,
    name: r.table_name,
    row_count: Number(r.row_count),
  }));

  const columnsByTable = new Map();
  for (const row of columnRows) {
    const key = `${row.TABLE_SCHEMA}.${row.TABLE_NAME}`;
    if (!columnsByTable.has(key)) columnsByTable.set(key, []);
    columnsByTable.get(key).push(row);
  }

  return { tables, columnsByTable, foreignKeys: fkRows };
}

function renderTableDoc(database, table, columns, rowCount) {
  const notes = DB_WORDPRESS_NOTES[database] ?? {};
  const relations = DB_LOGICAL_RELATIONS[database] ?? [];
  const crossRefs = CROSS_DATABASE_RELATIONS.filter(
    (r) =>
      (r.fromDb === database && r.fromTable === table.name) ||
      (r.toDb === database && r.toTable === table.name)
  );

  const pkCols = columns.filter((c) => c.is_pk === "1").map((c) => c.COLUMN_NAME);
  const related = relations.filter((r) => r.from === table.name || r.to === table.name);

  let md = `# ${table.schema}.${table.name}\n\n`;
  md += `**Database:** ${database}  \n`;
  md += `**Rows:** ${formatNumber(rowCount)}  \n`;
  md += `**Primary key:** ${pkCols.length ? pkCols.join(", ") : "*(none)*"}  \n`;
  md += `**Group:** ${inferGroup(database, table.name)}\n\n`;

  if (notes[table.name]) {
    md += `## WordPress migration note\n\n${notes[table.name]}\n\n`;
  }

  md += `## Columns\n\n| Column | Type | Nullable | Key |\n|--------|------|----------|-----|\n`;
  for (const col of columns) {
    md += `| ${col.COLUMN_NAME} | ${formatType(col)} | ${col.IS_NULLABLE} | ${col.is_pk === "1" ? "PK" : ""} |\n`;
  }

  if (related.length) {
    md += `\n## Logical relationships (within ${database})\n\n`;
    for (const rel of related) {
      if (rel.from === table.name) {
        md += `- \`${rel.column}\` → \`${rel.to}.${rel.ref}\` — ${rel.note}\n`;
      } else {
        md += `- Referenced by \`${rel.from}.${rel.column}\` — ${rel.note}\n`;
      }
    }
  }

  if (crossRefs.length) {
    md += `\n## Cross-database links\n\n`;
    for (const r of crossRefs) {
      if (r.fromDb === database && r.fromTable === table.name) {
        md += `- \`${r.fromColumn}\` → **${r.toDb}**.${r.toTable}.${r.toColumn} — ${r.note}`;
        if (r.verified) md += ` *(verified: ${r.verified})*`;
        md += `\n`;
      } else if (r.toDb === database && r.toTable === table.name) {
        md += `- Referenced from **${r.fromDb}**.${r.fromTable}.${r.fromColumn} — ${r.note}`;
        if (r.verified) md += ` *(verified: ${r.verified})*`;
        md += `\n`;
      }
    }
  }

  md += `\n---\n\n[← Back to ${database} overview](../README.md)\n`;
  return md;
}

function renderDatabaseReadme(database, tables, totalRows, foreignKeys) {
  const meta = DATABASE_ROLES[database] ?? {};
  const notes = DB_IMPORTANT_NOTES[database] ?? [];
  const groups = DB_ENTITY_GROUPS[database] ?? {};
  const wpNotes = DB_WORDPRESS_NOTES[database] ?? {};

  let md = `# ${database}\n\n`;
  if (meta.role) md += `**Role:** ${meta.role}  \n`;
  if (meta.summary) md += `\n${meta.summary}\n\n`;
  if (meta.wordpressPriority) md += `**WordPress priority:** ${meta.wordpressPriority}\n\n`;

  md += `**Exported:** ${new Date().toISOString()}  \n`;
  md += `**Tables:** ${tables.length}  \n`;
  md += `**Total rows:** ${formatNumber(totalRows)}  \n`;
  md += `**Declared foreign keys:** ${foreignKeys.length}\n\n`;

  if (notes.length) {
    md += `## Important notes\n\n`;
    for (const n of notes) md += `- ${n}\n`;
    md += `\n`;
  }

  const groupEntries = Object.entries(groups);
  if (groupEntries.length) {
    md += `## Entity groups\n\n`;
    for (const [group, names] of groupEntries) {
      const present = names.filter((n) => tables.some((t) => t.name === n));
      if (!present.length) continue;
      md += `### ${group}\n\n`;
      for (const name of present) {
        const t = tables.find((x) => x.name === name);
        md += `- [${name}](./tables/${name}.md) — ${formatNumber(t.row_count)} rows\n`;
      }
      md += `\n`;
    }
  }

  md += `## All tables\n\n| Table | Rows | Group | WordPress hint |\n|-------|------|-------|----------------|\n`;
  for (const table of tables) {
    const hint = (wpNotes[table.name] ?? "").split(".")[0] || "—";
    md += `| [${table.name}](./tables/${table.name}.md) | ${formatNumber(table.row_count)} | ${inferGroup(database, table.name)} | ${hint} |\n`;
  }

  md += `\n## Relationships\n\n`;
  md += `- [Within-database relationships](./relationships.md)\n`;
  md += `- [Cross-database map](../cross-database-relationships.md)\n\n`;
  md += `---\n\n[← Back to all databases](../README.md)\n`;
  return md;
}

function renderRelationshipsDoc(database, tables, foreignKeys) {
  const relations = DB_LOGICAL_RELATIONS[database] ?? [];
  const cross = CROSS_DATABASE_RELATIONS.filter(
    (r) => r.fromDb === database || r.toDb === database
  );

  let md = `# ${database} — relationships\n\n`;

  if (foreignKeys.length) {
    md += `## Declared foreign keys (${foreignKeys.length})\n\n`;
    md += `| From | Column | To | Column | Constraint |\n|------|--------|----|--------|------------|\n`;
    for (const fk of foreignKeys) {
      md += `| ${fk.from_table} | ${fk.from_column} | ${fk.to_table} | ${fk.to_column} | ${fk.fk_name} |\n`;
    }
    md += `\n`;
  } else {
    md += `> No SQL foreign keys declared in this database.\n\n`;
  }

  if (relations.length) {
    md += `## Logical relationships\n\n`;
    md += `| From | Column | To | Column | Notes |\n|------|--------|----|--------|-------|\n`;
    for (const rel of relations) {
      if (!tables.some((t) => t.name === rel.from)) continue;
      const toExists = tables.some((t) => t.name === rel.to) || rel.note.includes("Cross-DB");
      if (!toExists) continue;
      md += `| ${rel.from} | ${rel.column} | ${rel.to} | ${rel.ref} | ${rel.note} |\n`;
    }
    md += `\n`;
  }

  if (cross.length) {
    md += `## Cross-database links\n\n`;
    md += `| Direction | Link | Verified |\n|-----------|------|----------|\n`;
    for (const r of cross) {
      if (r.fromDb === database) {
        md += `| → ${r.toDb} | ${r.fromTable}.${r.fromColumn} → ${r.toTable}.${r.toColumn} | ${r.verified ?? "—"} |\n`;
      } else {
        md += `| ← ${r.fromDb} | ${r.fromTable}.${r.fromColumn} → ${r.toTable}.${r.toColumn} | ${r.verified ?? "—"} |\n`;
      }
    }
    md += `\n`;
  }

  md += `---\n\n[← Back to ${database} overview](./README.md)\n`;
  return md;
}

function renderCrossDatabaseDoc(databases, allStats) {
  let md = `# Cross-database relationships\n\n`;
  md += `How the five AsreKhodro databases connect. There are **no cross-database foreign keys** in SQL Server — links are by shared IDs in application code.\n\n`;

  md += `## Architecture overview\n\n`;
  md += "```\n";
  md += "AsreKhodroBack (master CMS)\n";
  md += "    │ ContentId, CategoryId, FileId, DomainId\n";
  md += "    ├──► AsreKhodroFront (published site cache)\n";
  md += "    ├──► AsreKhodroComments (ObjectId = ContentId)\n";
  md += "    └──► AsrekhodroWidget (DomainId only)\n\n";
  md += "AsreKhodroMessage (standalone: contacts & newsletter)\n";
  md += "```\n\n";

  md += `## Database summary\n\n`;
  md += `| Database | Tables | Total rows | Role |\n|----------|--------|------------|------|\n`;
  for (const db of databases) {
    const s = allStats[db];
    const role = DATABASE_ROLES[db]?.role ?? "—";
    md += `| [${db}](./${db}/README.md) | ${s.tables} | ${formatNumber(s.rows)} | ${role} |\n`;
  }
  md += `\n`;

  md += `## Verified cross-database links\n\n`;
  md += `| From | To | Join | Notes | Verified |\n|------|----|------|-------|----------|\n`;
  for (const r of CROSS_DATABASE_RELATIONS) {
    if (r.fromDb === r.toDb) continue;
    md += `| ${r.fromDb}.${r.fromTable}.${r.fromColumn} | ${r.toDb}.${r.toTable}.${r.toColumn} | \`${r.fromColumn} = ${r.toColumn}\` | ${r.note} | ${r.verified ?? "—"} |\n`;
  }
  md += `\n`;

  md += `## WordPress migration order\n\n`;
  md += `1. **AsreKhodroBack** — posts, categories, media, users (source of truth)\n`;
  md += `2. **AsreKhodroFront** — validate published subset; skip \`Main*\` caches and \`Hits\`\n`;
  md += `3. **AsreKhodroComments** — map \`ObjectId\` → WordPress post ID\n`;
  md += `4. **AsrekhodroWidget** — widgets/blocks (separate file IDs)\n`;
  md += `5. **AsreKhodroMessage** — newsletter contacts (optional plugin)\n\n`;

  md += `## Mermaid diagram\n\n`;
  md += "```mermaid\nflowchart LR\n";
  md += "  Back[AsreKhodroBack<br/>Master CMS]\n";
  md += "  Front[AsreKhodroFront<br/>Published cache]\n";
  md += "  Comments[AsreKhodroComments<br/>Comments]\n";
  md += "  Widget[AsrekhodroWidget<br/>Widgets]\n";
  md += "  Message[AsreKhodroMessage<br/>Newsletter]\n\n";
  md += "  Back -->|ContentId| Front\n";
  md += "  Back -->|ContentId = ObjectId| Comments\n";
  md += "  Back -->|CategoryId| Front\n";
  md += "  Back -.->|DomainId| Widget\n";
  md += "  Message\n";
  md += "```\n\n";

  md += `---\n\n[← Back to all databases](./README.md)\n`;
  return md;
}

function renderRootReadme(databases, allStats) {
  let md = `# AsreKhodro database documentation\n\n`;
  md += `Schema reference for migrating AsreKhodro SQL Server data to WordPress.\n\n`;
  md += `**Databases documented:** ${databases.length}  \n`;
  md += `**Generated:** ${new Date().toISOString()}  \n`;
  md += `**Server:** local SQL Server (\`sqlcmd -S .\`)\n\n`;

  md += `## Start here\n\n`;
  md += `- **[Cross-database relationships](./cross-database-relationships.md)** — how the 5 DBs connect\n\n`;
  md += `## Databases\n\n`;
  for (const db of databases) {
    const s = allStats[db];
    const role = DATABASE_ROLES[db]?.role ?? "";
    md += `- **[${db}](./${db}/README.md)** — ${s.tables} tables, ${formatNumber(s.rows)} rows — ${role}\n`;
  }

  md += `\n## Regenerate all docs\n\n\`\`\`powershell\n`;
  md += `cd d:\\prj-lenovo-shakhes\\asrekhodro-1405\\dev\\sql-server-exporter\n`;
  md += `npm run docs\n\`\`\`\n`;
  return md;
}

function generateDatabaseDocs(server, database, docsRoot) {
  console.log(`  Discovering ${database}...`);
  const { tables, columnsByTable, foreignKeys } = discoverDatabase(server, database);
  const dbDir = path.join(docsRoot, database);
  const tablesDir = path.join(dbDir, "tables");
  fs.mkdirSync(tablesDir, { recursive: true });

  const totalRows = tables.reduce((sum, t) => sum + t.row_count, 0);

  for (const table of tables) {
    const cols = columnsByTable.get(`${table.schema}.${table.name}`) ?? [];
    fs.writeFileSync(
      path.join(tablesDir, `${table.name}.md`),
      renderTableDoc(database, table, cols, table.row_count),
      "utf8"
    );
  }

  fs.writeFileSync(
    path.join(dbDir, "README.md"),
    renderDatabaseReadme(database, tables, totalRows, foreignKeys),
    "utf8"
  );
  fs.writeFileSync(
    path.join(dbDir, "relationships.md"),
    renderRelationshipsDoc(database, tables, foreignKeys),
    "utf8"
  );

  return { tables: tables.length, rows: totalRows };
}

// --- main ---
const opts = parseArgs(process.argv);
let databases = opts.all
  ? listAsreKhodroDatabases(opts.server)
  : [opts.database ?? "AsreKhodroFront"];

if (opts.fresh && fs.existsSync(opts.output)) {
  console.log(`Removing ${opts.output}...`);
  fs.rmSync(opts.output, { recursive: true, force: true });
}

console.log(`Generating docs for: ${databases.join(", ")}`);
fs.mkdirSync(opts.output, { recursive: true });

const allStats = {};
for (const db of databases) {
  allStats[db] = generateDatabaseDocs(opts.server, db, opts.output);
  console.log(`  ✓ ${db}: ${allStats[db].tables} tables`);
}

fs.writeFileSync(
  path.join(opts.output, "cross-database-relationships.md"),
  renderCrossDatabaseDoc(databases, allStats),
  "utf8"
);
fs.writeFileSync(
  path.join(opts.output, "README.md"),
  renderRootReadme(databases, allStats),
  "utf8"
);

console.log(`\nDone → ${opts.output}`);
