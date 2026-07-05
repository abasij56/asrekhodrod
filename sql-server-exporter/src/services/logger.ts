import * as fs from "fs";
import * as path from "path";
import type { ExportError } from "../types";

export class Logger {
  private errorLogPath: string | null = null;
  private errorStream: fs.WriteStream | null = null;

  constructor(private readonly outputDir?: string) {
    if (outputDir) {
      this.errorLogPath = path.join(outputDir, "export-errors.log");
    }
  }

  info(message: string): void {
    console.log(`[INFO] ${message}`);
  }

  warn(message: string): void {
    console.warn(`[WARN] ${message}`);
  }

  error(message: string): void {
    console.error(`[ERROR] ${message}`);
  }

  openErrorLog(): void {
    if (!this.errorLogPath) return;
    fs.mkdirSync(path.dirname(this.errorLogPath), { recursive: true });
    this.errorStream = fs.createWriteStream(this.errorLogPath, { flags: "a" });
    this.errorStream.write(`--- Export session started ${new Date().toISOString()} ---\n`);
  }

  logExportError(entry: ExportError): void {
    const line = `[${entry.timestamp}] [${entry.database}] [${entry.table}] [${entry.phase}] ${entry.message}\n`;
    this.error(line.trim());
    if (this.errorStream) {
      this.errorStream.write(line);
    }
  }

  closeErrorLog(): void {
    if (this.errorStream) {
      this.errorStream.end();
      this.errorStream = null;
    }
  }
}
