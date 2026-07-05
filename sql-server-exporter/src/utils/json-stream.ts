import * as fs from "fs";
import * as path from "path";

/**
 * Streams a JSON array to disk without holding all rows in memory.
 */
export class JsonArrayStreamWriter {
  private stream: fs.WriteStream;
  private isFirst = true;
  private closed = false;

  constructor(filePath: string) {
    this.stream = fs.createWriteStream(filePath, { encoding: "utf8" });
    this.stream.write("[\n");
  }

  writeRow(row: unknown): Promise<void> {
    if (this.closed) {
      return Promise.reject(new Error("JSON stream writer is already closed."));
    }

    const prefix = this.isFirst ? "  " : ",\n  ";
    this.isFirst = false;
    const chunk = prefix + JSON.stringify(row);

    return new Promise((resolve, reject) => {
      const canContinue = this.stream.write(chunk, (error) => {
        if (error) reject(error);
      });
      if (canContinue) {
        resolve();
      } else {
        this.stream.once("drain", resolve);
      }
    });
  }

  close(): Promise<void> {
    if (this.closed) {
      return Promise.resolve();
    }
    this.closed = true;

    return new Promise((resolve, reject) => {
      this.stream.write("\n]\n", (error) => {
        if (error) {
          reject(error);
          return;
        }
        this.stream.end(() => resolve());
      });
    });
  }
}

export async function writeJsonFile(filePath: string, data: unknown): Promise<void> {
  await fs.promises.mkdir(path.dirname(filePath), { recursive: true });
  await fs.promises.writeFile(filePath, JSON.stringify(data, null, 2), "utf8");
}
