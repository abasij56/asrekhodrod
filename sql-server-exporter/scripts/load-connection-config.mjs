import * as fs from "node:fs";
import * as path from "node:path";
import { fileURLToPath } from "node:url";

const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");

function configPath() {
  return path.join(projectRoot, "connection.config.json");
}

function exampleConfigPath() {
  return path.join(projectRoot, "connection.config.example.json");
}

export function loadConnectionConfig() {
  const file = configPath();
  if (!fs.existsSync(file)) {
    throw new Error(
      `Missing ${path.basename(file)}. Copy ${path.basename(exampleConfigPath())} to ${path.basename(file)} and set your credentials.`
    );
  }

  const config = JSON.parse(fs.readFileSync(file, "utf8"));
  if (!config?.connections || typeof config.connections !== "object") {
    throw new Error(`${path.basename(file)} must contain a "connections" object.`);
  }

  return config;
}

export function resolveConnectionProfile(profileName) {
  const config = loadConnectionConfig();
  const name = profileName || config.active || "remote";
  const profile = config.connections[name];

  if (!profile) {
    const available = Object.keys(config.connections).join(", ");
    throw new Error(`Unknown connection profile "${name}". Available: ${available}`);
  }

  if (!profile.server) {
    throw new Error(`Connection profile "${name}" is missing "server".`);
  }

  return { name, profile, config };
}

export function toMssqlConnectionString(profile) {
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

/** sqlcmd flags aligned with SSMS "Encrypt" + "Trust server certificate". */
export function buildSqlcmdBaseArgs(profile) {
  const args = ["-S", profile.server];

  if (profile.user) {
    args.push("-U", profile.user);
    if (profile.password !== undefined && profile.password !== "") {
      args.push("-P", profile.password);
    }
  }

  const encrypt = profile.encrypt !== false;
  const trustCert = profile.trustServerCertificate !== false;

  if (encrypt) {
    args.push("-N");
  }
  if (trustCert) {
    args.push("-C");
  }

  return args;
}
