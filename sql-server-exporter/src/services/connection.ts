import sql from "mssql";
import type { Logger } from "./logger";

export class ConnectionService {
  private pool: sql.ConnectionPool | null = null;

  constructor(
    private readonly connectionString: string,
    private readonly logger: Logger
  ) {}

  async connect(): Promise<sql.ConnectionPool> {
    if (this.pool?.connected) {
      return this.pool;
    }

    this.logger.info("Connecting to SQL Server...");
    this.pool = await sql.connect(this.connectionString);
    this.logger.info("Connected to SQL Server.");
    return this.pool;
  }

  async query<T = Record<string, unknown>>(queryText: string): Promise<sql.IResult<T>> {
    const pool = await this.connect();
    return pool.request().query<T>(queryText);
  }

  async queryInDatabase<T = Record<string, unknown>>(
    database: string,
    queryText: string,
    inputs?: Record<string, unknown>
  ): Promise<sql.IResult<T>> {
    const pool = await this.connect();
    const request = pool.request();

    if (inputs) {
      for (const [key, value] of Object.entries(inputs)) {
        request.input(key, value);
      }
    }

    const batch = `USE [${escapeIdentifier(database)}];\n${queryText}`;
    return request.query<T>(batch);
  }

  async close(): Promise<void> {
    if (this.pool) {
      await this.pool.close();
      this.pool = null;
      this.logger.info("SQL Server connection closed.");
    }
  }
}

export function escapeIdentifier(name: string): string {
  return name.replace(/]/g, "]]");
}

export function bracketName(schema: string, table: string): string {
  return `[${escapeIdentifier(schema)}].[${escapeIdentifier(table)}]`;
}

export function bracketDatabase(database: string, schema: string, table: string): string {
  return `[${escapeIdentifier(database)}].[${escapeIdentifier(schema)}].[${escapeIdentifier(table)}]`;
}
