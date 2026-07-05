# SQL Server Exporter

Internal migration utility for extracting data from an existing SQL Server website in preparation for a future WordPress import.

This tool exports schema metadata, foreign-key relationships, table data as JSON, and an optional media path manifest. It is **not** a reusable SaaS product — it is a focused, maintainable CLI for one-off migration work.

## Requirements

- Node.js 18+
- Network access to the target SQL Server instance
- Credentials with read access to the databases you want to export

## Install

```bash
cd sql-server-exporter
npm install
npm run build
```

## Connection config

Copy the example file and edit credentials (this file is **gitignored**):

```bash
cp connection.config.example.json connection.config.json
```

`connection.config.json` holds named profiles:

| Profile | Use |
|---------|-----|
| `local` | Previous default — `sqlcmd -S .` with Windows auth |
| `remote` | Legacy production server (`87.107.154.141`) |

Set `"active": "remote"` (or `"local"`) to pick the default profile.

Scripts read this file automatically. Override with:

```bash
npm run export:sample -- --profile=local
npm run export:all
npm run export:all -- --source=front
npm start -- --profile=remote
npm start -- --connection="Server=...;User Id=...;Password=..."
```

## Usage

```bash
npm start
```

Or with an explicit connection string:

```bash
npm start -- --connection="Server=localhost;Database=master;User Id=sa;Password=YourPassword;Encrypt=true;TrustServerCertificate=true"
```

### Options

| Flag | Default | Description |
|------|---------|-------------|
| `--connection` | — | Standard SQL Server connection string (overrides config file) |
| `--profile` | `active` in config | Named profile from `connection.config.json` |
| `--output` | `./export` | Output directory |
| `--batch-size` | `1000` | Rows fetched per batch during data export |
| `--media-config` | — | JSON file listing columns that store media paths |

### Example with all options

```bash
npm start -- \
  --connection="Server=192.168.1.10;Database=master;User Id=exporter;Password=secret;Encrypt=true;TrustServerCertificate=true" \
  --output=./export \
  --batch-size=2000 \
  --media-config=./media-config.json
```

## Output structure

```
export/
├── schema.json
├── relations.json
├── media-manifest.json      # only when --media-config is provided
├── export-errors.log        # created when errors occur
└── data/
    ├── NewsDB/
    │   ├── News.json
    │   ├── Categories.json
    │   └── Users.json
    └── AdsDB/
        └── Ads.json
```

## Media configuration

Copy `media-config.example.json` and list columns that contain file paths:

```json
{
  "mediaColumns": [
    {
      "database": "NewsDB",
      "table": "News",
      "column": "ImagePath"
    }
  ]
}
```

The exporter collects distinct paths into `media-manifest.json`. Files are **not** copied — only the manifest is generated.

## Behaviour

- Discovers all accessible user databases (system DBs are skipped)
- Exports tables, columns, primary keys, foreign keys, and row counts
- Streams table data in batches — never loads a full table into memory
- Converts SQL Server types to JSON-friendly values (`datetime` → ISO-8601, `bit` → boolean, etc.)
- Continues exporting other tables when one fails
- Logs progress to the console and failures to `export-errors.log`

## Development

```bash
npm run dev -- --connection="..." --output=./export
```

## Project layout

```
src/
├── cli/           Argument parsing
├── discovery/     Schema and metadata discovery
├── exporters/     JSON output writers
├── services/      Connection and logging
├── types/         Shared TypeScript types
└── utils/         Type conversion, streaming, paths
```

## WordPress compatibility

Exports are JSON-first and designed for a future custom WordPress importer. No WordPress XML is generated.
