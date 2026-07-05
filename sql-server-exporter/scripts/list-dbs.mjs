import { execFileSync } from "node:child_process";

const server = process.argv[2] ?? ".";

try {
  const stdout = execFileSync(
    "sqlcmd",
    [
      "-S",
      server,
      "-Q",
      "SET NOCOUNT ON; SELECT name FROM sys.databases WHERE name LIKE '%Asre%' OR name LIKE '%Khodro%' ORDER BY name",
      "-W",
      "-h",
      "-1",
    ],
    { encoding: "utf8" }
  );

  const names = stdout
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter(Boolean)
    .filter((line) => !/^\(\d+ rows affected\)/.test(line));

  console.log(JSON.stringify(names.map((name) => ({ name })), null, 2));
} catch (error) {
  console.error(error.message);
  process.exit(1);
}
