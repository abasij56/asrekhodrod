import type { CliOptions } from "../types";
import { resolveConnectionProfile, toMssqlConnectionString } from "../services/load-connection-config";

function parseArgValue(args: string[], name: string): string | undefined {
  const prefix = `--${name}=`;
  const direct = args.find((arg) => arg.startsWith(prefix));
  if (direct) {
    return direct.slice(prefix.length);
  }

  const index = args.findIndex((arg) => arg === `--${name}`);
  if (index >= 0 && args[index + 1]) {
    return args[index + 1];
  }

  return undefined;
}

export function parseCliOptions(argv: string[]): CliOptions {
  const args = argv.slice(2);

  if (args.includes("--help") || args.includes("-h")) {
    printHelp();
    process.exit(0);
  }

  const connectionArg = parseArgValue(args, "connection");
  const profileName = parseArgValue(args, "profile");
  const connection =
    connectionArg ??
    toMssqlConnectionString(resolveConnectionProfile(profileName).profile);

  if (!connection) {
    throw new Error(
      'Missing connection. Set connection.config.json, or pass --connection="Server=...;Database=...;User Id=...;Password=..."'
    );
  }

  const output = parseArgValue(args, "output") ?? "./export";
  const batchSizeRaw = parseArgValue(args, "batch-size") ?? "1000";
  const batchSize = Number(batchSizeRaw);
  const mediaConfig = parseArgValue(args, "media-config");

  if (!Number.isInteger(batchSize) || batchSize <= 0) {
    throw new Error("--batch-size must be a positive integer.");
  }

  return {
    connection,
    output,
    batchSize,
    mediaConfig,
  };
}

function printHelp(): void {
  console.log(`
SQL Server Exporter
Internal utility for exporting SQL Server schema and data to JSON.

Usage:
  npm start
  npm start -- --profile=local
  npm start -- --connection="Server=...;Database=...;User Id=...;Password=..."

Options:
  --connection   SQL Server connection string (overrides connection.config.json)
  --profile      Named profile from connection.config.json (default: active profile)
  --output       Output directory (default: ./export)
  --batch-size   Rows per batch when exporting data (default: 1000)
  --media-config Path to media column configuration JSON
  --help, -h     Show this help message

Example:
  npm start -- \\
    --connection="Server=localhost;Database=master;User Id=sa;Password=secret;Encrypt=true;TrustServerCertificate=true" \\
    --output=./export \\
    --batch-size=1000 \\
    --media-config=./media-config.json
`);
}
