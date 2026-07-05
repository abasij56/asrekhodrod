import { execFileSync } from "node:child_process";
import * as fs from "node:fs";
import * as path from "node:path";
import { fileURLToPath } from "node:url";
import {
  buildSqlcmdBaseArgs,
  resolveConnectionProfile,
} from "./load-connection-config.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const projectRoot = path.resolve(__dirname, "..");

const KIOSK_CATEGORY_ID = 43;

function parseArgs(argv) {
  const args = argv.slice(2);
  const get = (name, fallback) => {
    const flag = `--${name}`;
    const eqPrefix = `${flag}=`;

    for (const arg of args) {
      if (arg.startsWith(eqPrefix)) {
        return arg.slice(eqPrefix.length) || fallback;
      }
    }

    const i = args.indexOf(flag);
    if (i >= 0 && args[i + 1] && !args[i + 1].startsWith("-")) {
      return args[i + 1];
    }

    return fallback;
  };

  return {
    profile: get("profile", null),
    batch: Math.max(1, Number(get("batch", "200")) || 200),
    minBatch: Math.max(1, Number(get("min-batch", "1")) || 1),
    maxRows: Math.max(0, Number(get("max-rows", "2000")) || 0),
    datasets: get("datasets", "videos,video-categories,magazines")
      .split(",")
      .map((item) => item.trim())
      .filter(Boolean),
  };
}

function parseSqlcmdJson(raw) {
  const text = raw
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter((line) => line && !/^\(\d+ rows affected\)/.test(line))
    .join("");

  if (!text) {
    return [];
  }

  return JSON.parse(text);
}

function isCorruptionError(err) {
  const text = [err.stdout, err.stderr, err.message].filter(Boolean).join("\n");
  return (
    err.status === 605 ||
    err.status === 823 ||
    err.status === 824 ||
    text.includes("Msg 605") ||
    text.includes("Msg 823") ||
    text.includes("Msg 824")
  );
}

function errorSummary(err) {
  const text = [err.stdout, err.stderr, err.message].filter(Boolean).join("\n");
  const line = text
    .split(/\r?\n/)
    .map((item) => item.trim())
    .find((item) => item);
  return `status=${err.status ?? "unknown"}${line ? ` ${line}` : ""}`;
}

function runJsonQuery(profile, database, query) {
  const tmpFile = path.join(
    projectRoot,
    `.tmp-back-test-${process.pid}-${Date.now()}-${Math.random()
      .toString(16)
      .slice(2)}.json`
  );

  try {
    execFileSync(
      "sqlcmd",
      [
        ...buildSqlcmdBaseArgs(profile),
        "-d",
        database,
        "-y",
        "0",
        "-Q",
        query,
        "-f",
        "o:65001",
        "-o",
        tmpFile,
      ],
      { encoding: "utf8", maxBuffer: 64 * 1024 * 1024 }
    );
    return parseSqlcmdJson(fs.readFileSync(tmpFile, "utf8"));
  } finally {
    try {
      fs.unlinkSync(tmpFile);
    } catch {
      // ignore cleanup errors
    }
  }
}

function pageQuery(baseQuery, orderBy, offset, size) {
  return `${baseQuery.replace(/FOR JSON PATH;\s*$/i, "")}
ORDER BY ${orderBy}
OFFSET ${offset} ROWS FETCH NEXT ${size} ROWS ONLY
FOR JSON PATH;`;
}

function countQuery(fromWhereSql) {
  return `
SET NOCOUNT ON;
SELECT COUNT_BIG(*) AS rows
${fromWhereSql}
FOR JSON PATH;
`;
}

const datasets = {
  videos: {
    label: "videos",
    orderBy: "cc.PublishTime DESC, ci.Id DESC",
    countFromWhere: `
FROM dbo.ContentInitialize ci
INNER JOIN dbo.ContentCommonInfo cc ON ci.Id = cc.ContentId
WHERE ci.ContentTypeId = 16
  AND ci.StatusId IN (1, 3)`,
    query: `
SET NOCOUNT ON;
SELECT
  ci.Id AS contentId,
  ci.DomainId AS domainId,
  ci.ContentTypeId AS contentTypeId,
  ci.StatusId AS statusId,
  ci.Title AS title,
  cc.PublishTime AS publishTime,
  COALESCE(
    (
      SELECT TOP 1 fft.Url
      FROM dbo.ContentFiles bcf
      INNER JOIN dbo.FilesFiletypes fft ON fft.FileId = bcf.FileId
      WHERE bcf.ContentId = ci.Id
        AND fft.Url IS NOT NULL
        AND LTRIM(RTRIM(fft.Url)) <> ''
        AND fft.Url LIKE '%/Uploaded/Video/%'
      ORDER BY
        CASE WHEN bcf.IsMain = 1 THEN 0 ELSE 1 END,
        bcf.Periority,
        fft.Id DESC
    ),
    NULL
  ) AS videoUrl
FROM dbo.ContentInitialize ci
INNER JOIN dbo.ContentCommonInfo cc ON ci.Id = cc.ContentId
WHERE ci.ContentTypeId = 16
  AND ci.StatusId IN (1, 3)
FOR JSON PATH;`,
  },
  "video-categories": {
    label: "video-categories",
    orderBy: "cc.ContentId, cc.CategoryId",
    countFromWhere: `
FROM dbo.ContentCategories cc
INNER JOIN dbo.ContentInitialize ci ON ci.Id = cc.ContentId
WHERE ci.ContentTypeId = 16
  AND ci.StatusId IN (1, 3)`,
    query: `
SET NOCOUNT ON;
SELECT
  cc.ContentId AS contentId,
  cc.CategoryId AS categoryId,
  cc.IsMain AS isMain
FROM dbo.ContentCategories cc
INNER JOIN dbo.ContentInitialize ci ON ci.Id = cc.ContentId
WHERE ci.ContentTypeId = 16
  AND ci.StatusId IN (1, 3)
FOR JSON PATH;`,
  },
  magazines: {
    label: "magazines",
    orderBy: "fpi.CreateTime DESC, fi.Id DESC",
    countFromWhere: `
FROM dbo.FileCategories fcat
INNER JOIN dbo.FileInitialize fi ON fi.Id = fcat.FileId
LEFT JOIN dbo.FilePrivateInfo fpi ON fpi.FileId = fi.Id
LEFT JOIN dbo.FileCommonInfo fci ON fci.FileId = fi.Id
WHERE fcat.CategoryId = ${KIOSK_CATEGORY_ID}
  AND fi.StatusId IN (1, 3)`,
    query: `
SET NOCOUNT ON;
SELECT
  fi.Id AS fileId,
  fi.DomainId AS domainId,
  fi.StatusId AS statusId,
  fi.Title AS title,
  ${KIOSK_CATEGORY_ID} AS categoryId,
  fpi.CreateTime AS publishTime,
  fci.Description AS description,
  COALESCE(
    (
      SELECT TOP 1 fft.Url
      FROM dbo.FilesFiletypes fft
      WHERE fft.FileId = fi.Id
        AND fft.Url IS NOT NULL
        AND LTRIM(RTRIM(fft.Url)) <> ''
      ORDER BY fft.Id DESC
    ),
    NULL
  ) AS imageUrl
FROM dbo.FileCategories fcat
INNER JOIN dbo.FileInitialize fi ON fi.Id = fcat.FileId
LEFT JOIN dbo.FilePrivateInfo fpi ON fpi.FileId = fi.Id
LEFT JOIN dbo.FileCommonInfo fci ON fci.FileId = fi.Id
WHERE fcat.CategoryId = ${KIOSK_CATEGORY_ID}
  AND fi.StatusId IN (1, 3)
FOR JSON PATH;`,
  },
};

function probe(profile) {
  console.log("== Basic Back DB probe ==");
  try {
    const rows = runJsonQuery(
      profile,
      "AsreKhodroBack",
      "SET NOCOUNT ON; SELECT DB_NAME() AS databaseName FOR JSON PATH;"
    );
    console.log(`OK connected: ${rows[0]?.databaseName ?? "AsreKhodroBack"}`);
  } catch (err) {
    console.log(`FAIL connect/read: ${errorSummary(err)}`);
    return false;
  }
  return true;
}

function fetchPageSafely(profile, dataset, offset, size, minBatch, depth = 0) {
  const indent = "  ".repeat(depth);

  try {
    const rows = runJsonQuery(
      profile,
      "AsreKhodroBack",
      pageQuery(dataset.query, dataset.orderBy, offset, size)
    );
    console.log(`${indent}OK offset=${offset} size=${size} rows=${rows.length}`);
    return { rows: rows.length, skipped: 0, failed: 0 };
  } catch (err) {
    if (!isCorruptionError(err)) {
      console.log(
        `${indent}FAIL offset=${offset} size=${size} non-corruption ${errorSummary(err)}`
      );
      return { rows: 0, skipped: 0, failed: size };
    }

    if (size <= minBatch) {
      console.log(
        `${indent}SKIP offset=${offset} size=${size} corruption ${errorSummary(err)}`
      );
      return { rows: 0, skipped: size, failed: 0 };
    }

    const leftSize = Math.floor(size / 2);
    const rightSize = size - leftSize;
    console.log(
      `${indent}SPLIT offset=${offset} size=${size} corruption ${errorSummary(err)} -> ${leftSize}+${rightSize}`
    );
    const left = fetchPageSafely(
      profile,
      dataset,
      offset,
      leftSize,
      minBatch,
      depth + 1
    );
    const right = fetchPageSafely(
      profile,
      dataset,
      offset + leftSize,
      rightSize,
      minBatch,
      depth + 1
    );

    return {
      rows: left.rows + right.rows,
      skipped: left.skipped + right.skipped,
      failed: left.failed + right.failed,
    };
  }
}

function testDataset(profile, dataset, options) {
  console.log(`\n== ${dataset.label} ==`);

  let totalRows = null;
  try {
    const countRows = runJsonQuery(
      profile,
      "AsreKhodroBack",
      countQuery(dataset.countFromWhere)
    );
    totalRows = Number(countRows[0]?.rows ?? 0);
    console.log(`COUNT ok: ${totalRows}`);
  } catch (err) {
    console.log(`COUNT failed: ${errorSummary(err)}`);
  }

  const scanRows =
    options.maxRows > 0
      ? options.maxRows
      : totalRows != null
        ? totalRows
        : options.batch;

  console.log(
    `SCAN start: batch=${options.batch}, min-batch=${options.minBatch}, max-rows=${scanRows || "all"}`
  );

  let offset = 0;
  const totals = { rows: 0, skipped: 0, failed: 0 };

  while (scanRows === 0 || offset < scanRows) {
    const size =
      scanRows === 0 ? options.batch : Math.min(options.batch, scanRows - offset);
    if (size <= 0) {
      break;
    }

    const result = fetchPageSafely(
      profile,
      dataset,
      offset,
      size,
      options.minBatch
    );
    totals.rows += result.rows;
    totals.skipped += result.skipped;
    totals.failed += result.failed;

    if (result.rows + result.skipped === 0) {
      break;
    }
    if (result.rows < size && result.skipped === 0) {
      break;
    }

    offset += size;
  }

  console.log(
    `SUMMARY ${dataset.label}: recovered=${totals.rows}, skipped=${totals.skipped}, hardFailed=${totals.failed}`
  );
}

const options = parseArgs(process.argv);
const { name, profile } = resolveConnectionProfile(options.profile);

console.log(
  `Testing Back DB recovery (profile=${name}, server=${profile.server}, datasets=${options.datasets.join(
    ","
  )})`
);
console.log(
  `Options: batch=${options.batch}, min-batch=${options.minBatch}, max-rows=${
    options.maxRows || "all"
  }`
);

if (probe(profile)) {
  for (const name of options.datasets) {
    const dataset = datasets[name];
    if (!dataset) {
      console.log(`\n== ${name} ==`);
      console.log(`SKIP unknown dataset. Known: ${Object.keys(datasets).join(", ")}`);
      continue;
    }
    testDataset(profile, dataset, options);
  }
}
