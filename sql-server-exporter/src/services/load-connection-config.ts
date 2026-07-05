import * as fs from "fs";
import * as path from "path";

export interface ConnectionProfile {
  description?: string;
  server: string;
  database?: string;
  user?: string;
  password?: string;
  entityFramework?: string;
  /** Default true — same as SSMS "Encrypt connection". */
  encrypt?: boolean;
  /** Default true — same as SSMS "Trust server certificate". */
  trustServerCertificate?: boolean;
}

interface ConnectionConfigFile {
  active?: string;
  connections: Record<string, ConnectionProfile>;
}

const projectRoot = path.resolve(__dirname, "..", "..");

function configFilePath(): string {
  return path.join(projectRoot, "connection.config.json");
}

function exampleConfigFilePath(): string {
  return path.join(projectRoot, "connection.config.example.json");
}

export function loadConnectionConfig(): ConnectionConfigFile {
  const file = configFilePath();
  if (!fs.existsSync(file)) {
    throw new Error(
      `Missing ${path.basename(file)}. Copy ${path.basename(exampleConfigFilePath())} to ${path.basename(file)} and set your credentials.`
    );
  }

  const config = JSON.parse(fs.readFileSync(file, "utf8")) as ConnectionConfigFile;
  if (!config?.connections || typeof config.connections !== "object") {
    throw new Error(`${path.basename(file)} must contain a "connections" object.`);
  }

  return config;
}

export function resolveConnectionProfile(profileName?: string): {
  name: string;
  profile: ConnectionProfile;
} {
  const config = loadConnectionConfig();
  const name = profileName ?? config.active ?? "remote";
  const profile = config.connections[name];

  if (!profile) {
    const available = Object.keys(config.connections).join(", ");
    throw new Error(`Unknown connection profile "${name}". Available: ${available}`);
  }

  if (!profile.server) {
    throw new Error(`Connection profile "${name}" is missing "server".`);
  }

  return { name, profile };
}

export function toMssqlConnectionString(profile: ConnectionProfile): string {
  const parts = [`Server=${profile.server}`, `Database=${profile.database || "master"}`];

  if (profile.user) {
    parts.push(`User Id=${profile.user}`);
    parts.push(`Password=${profile.password ?? ""}`);
  }

  const encrypt = profile.encrypt !== false;
  const trustCert = profile.trustServerCertificate !== false;

  if (encrypt) {
    parts.push("Encrypt=true");
  }
  if (trustCert) {
    parts.push("TrustServerCertificate=true");
  }

  return parts.join(";");
}
