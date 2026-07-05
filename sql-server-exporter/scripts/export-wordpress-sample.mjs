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

function parseArgs(argv) {
  const args = argv.slice(2);
  const get = (name, fallback) => {
    const flag = `--${name}`;
    const eqPrefix = `${flag}=`;

    for (const arg of args) {
      if (arg.startsWith(eqPrefix)) {
        const value = arg.slice(eqPrefix.length);
        return value !== "" ? value : fallback;
      }
    }

    const i = args.indexOf(flag);
    if (i >= 0 && args[i + 1] && !args[i + 1].startsWith("-")) {
      return args[i + 1];
    }

    return fallback;
  };
  const exportAll = args.includes("--all");
  const limitRaw = get("limit", exportAll ? "0" : "100");
  const reviewLimitRaw = get("review-limit", exportAll ? "0" : "50");
  const magazineLimitRaw = get("magazine-limit", exportAll ? "0" : "50");

  return {
    profile: get("profile", null),
    server: get("server", null),
    source: get("source", "auto"),
    exportAll,
    resume: !args.includes("--no-resume"),
    enrichImagesOnly: args.includes("--enrich-images-only"),
    skipContentFileImages: args.includes("--skip-content-file-images"),
    limit: Number(limitRaw),
    reviewLimit: Number(reviewLimitRaw),
    magazineLimit: Number(magazineLimitRaw),
    output: path.resolve(
      get(
        "output",
        path.join(projectRoot, "..", "awp", "wp-content", "asrekhodro-import")
      )
    ),
  };
}

const KIOSK_CATEGORY_ID = 43;
const CHUNK_SIZE = 1000;
const MIN_POST_BATCH_SIZE = 1;
const CONTENT_FILES_ID_BATCH = 150;
const CONTENT_FILES_MIN_BATCH = 10;
const CONTENT_FILES_ENRICH_VERSION = 3;
const SQLCMD_RETRIES = 4;
let backUnavailable = false;

function markBackUnavailable(label) {
  if (backUnavailable) {
    return;
  }
  backUnavailable = true;
  console.warn(
    `  ! AsreKhodroBack unavailable (Msg 823) during ${label}. ` +
      "Using AsreKhodroFront / skipping Back-only data for the rest of this run."
  );
  console.warn("    Tip: use --source=front to skip Back entirely.");
}

function chunkFilePath(outputDir, collection, index) {
  const num = String(index).padStart(3, "0");
  return path.join(outputDir, collection, `${collection}-${num}.json`);
}

function writeChunkFile(outputDir, collection, index, rows) {
  const filePath = chunkFilePath(outputDir, collection, index);
  fs.mkdirSync(path.dirname(filePath), { recursive: true });
  fs.writeFileSync(filePath, `${JSON.stringify(rows, null, 2)}\n`, "utf8");
}

function progressPath(outputDir) {
  return path.join(outputDir, ".export-progress.json");
}

function loadExportProgress(outputDir) {
  const file = progressPath(outputDir);
  if (!fs.existsSync(file)) {
    return {};
  }

  try {
    return JSON.parse(fs.readFileSync(file, "utf8"));
  } catch {
    return {};
  }
}

function saveExportProgress(outputDir, collection, patch) {
  const progress = loadExportProgress(outputDir);
  progress[collection] = {
    ...(progress[collection] ?? {}),
    ...patch,
    updatedAt: new Date().toISOString(),
  };
  fs.mkdirSync(outputDir, { recursive: true });
  fs.writeFileSync(
    progressPath(outputDir),
    `${JSON.stringify(progress, null, 2)}\n`,
    "utf8"
  );
}

function clearEmptyBackOnlyProgress(progress, sourceMode) {
  if (sourceMode === "front") {
    return progress;
  }

  const backOnlyCollections = [
    "videos",
    "video-categories",
    "reviews",
    "review-categories",
    "magazines",
  ];

  for (const collection of backOnlyCollections) {
    const saved = progress[collection];
    if (saved?.complete && (saved.rows ?? 0) === 0 && (saved.files ?? 0) === 0) {
      delete progress[collection];
      console.log(
        `  → ${collection} (cleared empty Front-only resume marker; will query Back)`
      );
    }
  }

  return progress;
}

function listChunkFiles(outputDir, collection) {
  const dir = path.join(outputDir, collection);
  if (!fs.existsSync(dir)) {
    return [];
  }

  return fs
    .readdirSync(dir)
    .filter((name) => new RegExp(`^${collection}-\\d+\\.json$`).test(name))
    .sort()
    .map((name) => path.join(dir, name));
}

function countRowsInChunkFiles(chunkFiles) {
  let total = 0;
  for (const file of chunkFiles) {
    total += JSON.parse(fs.readFileSync(file, "utf8")).length;
  }
  return total;
}

function resumeOffsetCollection(outputDir, collection, resume, progress) {
  if (!resume) {
    return { complete: false, fileIndex: 0, offset: 0, total: 0 };
  }

  const saved = progress[collection];
  if (saved?.complete) {
    return {
      complete: true,
      fileIndex: saved.files ?? 0,
      offset: saved.offset ?? saved.rows ?? 0,
      total: saved.rows ?? 0,
      uniqueContentIds: saved.uniqueContentIds ?? null,
      skippedRows: saved.skippedRows ?? 0,
    };
  }

  const chunkFiles = listChunkFiles(outputDir, collection);
  if (!chunkFiles.length) {
    return { complete: false, fileIndex: 0, offset: 0, total: 0 };
  }

  const total = countRowsInChunkFiles(chunkFiles);
  console.log(
    `  → Resuming ${collection} from chunk ${chunkFiles.length + 1} (${total} rows already on disk)`
  );

  return {
    complete: false,
    fileIndex: chunkFiles.length,
    offset: saved?.offset ?? total,
    total,
    uniqueContentIds: saved?.uniqueContentIds ?? null,
    skippedRows: saved?.skippedRows ?? 0,
  };
}

function resumePostsState(outputDir, resume, progress) {
  if (!resume) {
    return {
      complete: false,
      files: 0,
      total: 0,
      postsWithImageUrl: 0,
      offset: 0,
    };
  }

  const saved = progress.posts;
  if (saved?.complete) {
    return {
      complete: true,
      files: saved.files ?? 0,
      total: saved.rows ?? 0,
      postsWithImageUrl: saved.postsWithImageUrl ?? 0,
      offset: saved.offset ?? saved.rows ?? 0,
      skippedPosts: saved.skippedPosts ?? 0,
    };
  }

  const chunkFiles = listChunkFiles(outputDir, "posts");
  if (!chunkFiles.length) {
    return {
      complete: false,
      files: 0,
      total: 0,
      postsWithImageUrl: 0,
      offset: 0,
    };
  }

  let total = 0;
  let postsWithImageUrl = 0;
  for (const file of chunkFiles) {
    const rows = JSON.parse(fs.readFileSync(file, "utf8"));
    total += rows.length;
    for (const row of rows) {
      if (typeof row.imageUrl === "string" && row.imageUrl.trim() !== "") {
        postsWithImageUrl += 1;
      }
    }
  }

  console.log(
    `  → Resuming posts from chunk ${chunkFiles.length + 1} (${total} rows already on disk)`
  );

  return {
    complete: false,
    files: chunkFiles.length,
    total,
    postsWithImageUrl,
    offset: saved?.offset ?? total,
    skippedPosts: saved?.skippedPosts ?? 0,
  };
}

function trackUniqueContentIds(rows, uniqueIds) {
  trackUniqueFieldIds(rows, "contentId", uniqueIds);
}

function trackUniqueFieldIds(rows, field, uniqueIds) {
  if (!uniqueIds || !field) {
    return;
  }

  for (const row of rows) {
    if (row?.[field] != null) {
      uniqueIds.add(row[field]);
    }
  }
}

function sqlOffsetPage(orderBy, offset, batchSize = CHUNK_SIZE) {
  const start = Math.max(0, Number(offset) || 0);
  const size = Math.max(1, Number(batchSize) || CHUNK_SIZE);
  return `
ORDER BY ${orderBy}
OFFSET ${start} ROWS FETCH NEXT ${size} ROWS ONLY`;
}

function paginateJsonQuery(baseQuery, orderBy, offset, batchSize = CHUNK_SIZE) {
  let trimmed = baseQuery.trim().replace(/FOR JSON PATH;\s*$/i, "");
  trimmed = trimmed.replace(/\nORDER BY[\s\S]*$/i, "");
  return `${trimmed}${sqlOffsetPage(orderBy, offset, batchSize)}\nFOR JSON PATH;`;
}

function fetchPagedBackOrFront(
  connectionProfile,
  sourceMode,
  label,
  backBaseQuery,
  frontBaseQuery,
  offset,
  batchSize,
  backOrderBy,
  frontOrderBy = backOrderBy
) {
  return runBackOrFront(
    connectionProfile,
    sourceMode,
    label,
    paginateJsonQuery(backBaseQuery, backOrderBy, offset, batchSize),
    frontBaseQuery
      ? paginateJsonQuery(frontBaseQuery, frontOrderBy, offset, batchSize)
      : null
  );
}

function fetchPagedBackOnly(
  connectionProfile,
  sourceMode,
  label,
  baseQuery,
  database,
  offset,
  batchSize,
  orderBy
) {
  return runBackOnly(
    connectionProfile,
    sourceMode,
    label,
    paginateJsonQuery(baseQuery, orderBy, offset, batchSize),
    database
  );
}

function fetchCollectionPageSafely(fetchPage, label, offset, size) {
  try {
    return {
      rows: fetchPage(offset, size),
      skipped: 0,
    };
  } catch (err) {
    if (!isSqlCorruptionError(err) && !isBackCorruptionError(err)) {
      throw err;
    }

    if (size <= MIN_POST_BATCH_SIZE) {
      console.warn(`    ! skipped unreadable ${label} row at SQL offset ${offset} (Msg 605/823/824)`);
      return { rows: [], skipped: 1 };
    }

    const leftSize = Math.floor(size / 2);
    const rightSize = size - leftSize;
    console.warn(
      `    ! ${label} batch at SQL offset ${offset} failed (Msg 605/823/824); retrying as ${leftSize}+${rightSize}`
    );

    const left = fetchCollectionPageSafely(fetchPage, label, offset, leftSize);
    const right = fetchCollectionPageSafely(
      fetchPage,
      label,
      offset + leftSize,
      rightSize
    );

    return {
      rows: [...left.rows, ...right.rows],
      skipped: left.skipped + right.skipped,
    };
  }
}

function exportBatchedCollection({
  outputDir,
  collection,
  label,
  fullExport,
  sampleRows,
  legacyFilename,
  fetchPage,
  trackUnique = false,
  trackUniqueField = "contentId",
  resume = true,
  progress = {},
}) {
  if (!fullExport) {
    writeJson(outputDir, legacyFilename, sampleRows);
    const uniqueIds = trackUnique ? new Set() : null;
    trackUniqueFieldIds(sampleRows, trackUniqueField, uniqueIds);
    return {
      files: 1,
      rows: sampleRows.length,
      uniqueContentIds: uniqueIds ? uniqueIds.size : null,
      chunked: false,
    };
  }

  const resumed = resumeOffsetCollection(outputDir, collection, resume, progress);
  if (resumed.complete) {
    console.log(`  → ${label} (resume: already complete, ${resumed.total} rows)`);
    return {
      files: resumed.fileIndex,
      rows: resumed.total,
      uniqueContentIds: resumed.uniqueContentIds,
      chunked: resumed.fileIndex > 0,
    };
  }

  console.log(`  → ${label} (batched ${CHUNK_SIZE}/chunk → ${collection}/)`);
  let index = resumed.fileIndex;
  let total = resumed.total;
  let offset = resumed.offset;
  let skippedRows = resumed.skippedRows ?? 0;
  const uniqueIds = trackUnique ? new Set() : null;

  if (trackUnique && resumed.fileIndex > 0) {
    for (const file of listChunkFiles(outputDir, collection)) {
      trackUniqueFieldIds(JSON.parse(fs.readFileSync(file, "utf8")), trackUniqueField, uniqueIds);
    }
  }

  while (true) {
    const result = fetchCollectionPageSafely(fetchPage, label, offset, CHUNK_SIZE);
    const rows = result.rows;
    const skippedInBatch = result.skipped;

    if (!rows.length) {
      if (skippedInBatch > 0) {
        offset += CHUNK_SIZE;
        skippedRows += skippedInBatch;
        saveExportProgress(outputDir, collection, {
          complete: false,
          files: index,
          rows: total,
          offset,
          skippedRows,
          uniqueContentIds: uniqueIds ? uniqueIds.size : null,
        });
        console.log(
          `    ${collection} skipped ${skippedInBatch} unreadable row(s) at offset ${offset - CHUNK_SIZE} (total ${total})`
        );
        continue;
      }

      saveExportProgress(outputDir, collection, {
        complete: true,
        files: index,
        rows: total,
        offset,
        skippedRows,
        uniqueContentIds: uniqueIds ? uniqueIds.size : null,
      });
      break;
    }

    index += 1;
    writeChunkFile(outputDir, collection, index, rows);
    total += rows.length;
    skippedRows += skippedInBatch;
    trackUniqueFieldIds(rows, trackUniqueField, uniqueIds);
    saveExportProgress(outputDir, collection, {
      complete: false,
      files: index,
      rows: total,
      offset: offset + CHUNK_SIZE,
      skippedRows,
      uniqueContentIds: uniqueIds ? uniqueIds.size : null,
    });
    console.log(
      `    ${collection} chunk ${index}: +${rows.length} (total ${total})${skippedInBatch ? `, skipped ${skippedInBatch}` : ""}`
    );
    offset += CHUNK_SIZE;

    if (rows.length + skippedInBatch < CHUNK_SIZE) {
      saveExportProgress(outputDir, collection, {
        complete: true,
        files: index,
        rows: total,
        offset,
        skippedRows,
        uniqueContentIds: uniqueIds ? uniqueIds.size : null,
      });
      break;
    }
  }

  return {
    files: index,
    rows: total,
    uniqueContentIds: uniqueIds ? uniqueIds.size : null,
    chunked: index > 0,
  };
}

const FRONT_HOME_SECTIONS = [
  { table: "MainSlider", file: "main-slider", hasRowId: true, hasHitCount: false },
  { table: "MainTicker", file: "main-ticker", hasRowId: true, hasHitCount: false },
  { table: "MainTopHits", file: "main-top-hits", hasRowId: true, hasHitCount: false },
  { table: "Parsik", file: "parsik", hasRowId: true, hasHitCount: false },
  { table: "SpecialEvents", file: "special-events", hasRowId: true, hasHitCount: false },
  { table: "TopHits", file: "top-hits", hasRowId: false, hasHitCount: true },
];

function frontSectionRefQuery(section) {
  const columns = [];
  if (section.hasRowId) {
    columns.push("RowId AS rowId");
  }
  columns.push("ContentId AS contentId");
  columns.push("Periority AS priority");
  if (section.hasHitCount) {
    columns.push("HitCount AS hitCount");
  }

  return `
SET NOCOUNT ON;
SELECT
  ${columns.join(",\n  ")}
FROM dbo.${section.table}
ORDER BY Periority DESC, PublishTime DESC, ContentId DESC
FOR JSON PATH;
`;
}

function exportFrontHomeSections(connectionProfile, outputDir) {
  const sectionsDir = path.join(outputDir, "front-sections");
  const exported = {};
  const contentIds = new Set();

  for (const section of FRONT_HOME_SECTIONS) {
    console.log(`  → front-sections/${section.file}.json (${section.table})`);
    let rows = [];
    try {
      rows = runJsonQuery(
        connectionProfile,
        "AsreKhodroFront",
        frontSectionRefQuery(section)
      );
    } catch (err) {
      console.warn(`    ! skipped ${section.table}: ${err.message || err}`);
    }

    writeJson(sectionsDir, `${section.file}.json`, rows);
    exported[section.file] = rows.length;
    for (const row of rows) {
      if (row?.contentId != null) {
        contentIds.add(Number(row.contentId));
      }
    }
  }

  return {
    exported,
    contentIds: [...contentIds].filter((id) => Number.isFinite(id) && id > 0),
  };
}

function postsByContentIdsFrontQuery(idList) {
  return `
SET NOCOUNT ON;
SELECT
  sc.ContentId AS contentId,
  sc.DomainId AS domainId,
  CAST(NULL AS int) AS contentTypeId,
  CAST(3 AS tinyint) AS statusId,
  sc.Title AS title,
  sc.OverTitle AS overTitle,
  sc.UnderTitle AS underTitle,
  sc.ShortBody AS excerpt,
  sc.Body AS body,
  sc.Footer AS footer,
  sc.Author AS author,
  sc.PublishTime AS publishTime,
  sc.PublishTime AS contentTime,
  CAST(NULL AS datetime) AS expireTime,
  ${sqlFrontImageUrlColumn("sc")}
FROM dbo.SingleContent sc
WHERE sc.ContentId IN (${idList})
FOR JSON PATH;
`;
}

function postsByContentIdsBackQuery(idList) {
  return `
SET NOCOUNT ON;
SELECT
  ci.Id AS contentId,
  ci.DomainId AS domainId,
  ci.ContentTypeId AS contentTypeId,
  ci.StatusId AS statusId,
  ci.Title AS title,
  cc.OverTitle AS overTitle,
  cc.UnderTitle AS underTitle,
  cc.ShortBody AS excerpt,
  cc.BodyText AS body,
  cc.Footer AS footer,
  cc.Author AS author,
  cc.PublishTime AS publishTime,
  cc.ContentTime AS contentTime,
  cc.ExpireTime AS expireTime,
  ${sqlPostImageUrlExpression()} AS imageUrl
FROM dbo.ContentInitialize ci
INNER JOIN dbo.ContentCommonInfo cc ON ci.Id = cc.ContentId
LEFT JOIN AsreKhodroFront.dbo.SingleContent sc ON sc.ContentId = ci.Id
LEFT JOIN AsreKhodroFront.dbo.MainLastContents mlc ON mlc.ContentId = ci.Id
WHERE ci.Id IN (${idList})
  AND ci.StatusId IN (1, 3)
FOR JSON PATH;
`;
}

function fetchPostsByContentIds(connectionProfile, sourceMode, contentIds) {
  const ids = normalizeContentIds(contentIds);
  if (!ids.length) {
    return [];
  }

  const idList = ids.join(",");
  return runBackOrFront(
    connectionProfile,
    sourceMode,
    "posts-by-content-id",
    postsByContentIdsBackQuery(idList),
    postsByContentIdsFrontQuery(idList)
  );
}

function mergeRequiredPosts(posts, requiredContentIds, connectionProfile, sourceMode) {
  const existing = new Set(
    posts
      .map((post) => Number(post?.contentId))
      .filter((id) => Number.isFinite(id) && id > 0)
  );
  const missing = normalizeContentIds(requiredContentIds).filter((id) => !existing.has(id));
  if (!missing.length) {
    return posts;
  }

  const extra = fetchPostsByContentIds(connectionProfile, sourceMode, missing);
  enrichPostImageUrls(extra);
  console.log(`  → merged ${extra.length} front-section post(s) into posts.json`);
  return [...posts, ...extra];
}

function exportPosts(connectionProfile, sourceMode, limit, outputDir, options = {}) {
  const { resume = true, progress = {} } = options;

  if (limit > 0) {
    const posts = runBackOrFront(
      connectionProfile,
      sourceMode,
      "posts",
      postsBackQuery(sqlTop(limit), ""),
      postsFrontQuery(sqlTop(limit), "")
    );
    enrichPostImageUrls(posts);
    return {
      posts,
      chunked: false,
      postCount: posts.length,
      postFiles: 1,
      postsWithImageUrl: posts.filter(
        (p) => typeof p.imageUrl === "string" && p.imageUrl.trim() !== ""
      ).length,
    };
  }

  const resumed = resumePostsState(outputDir, resume, progress);
  if (resumed.complete) {
    console.log(`  → posts (resume: already complete, ${resumed.total} rows)`);
    return {
      posts: [],
      chunked: resumed.files > 0,
      postCount: resumed.total,
      postFiles: resumed.files,
      postsWithImageUrl: resumed.postsWithImageUrl,
    };
  }

  const useFrontOnly = sourceMode === "front" || backUnavailable;
  console.log(
    `  → posts (batched ${CHUNK_SIZE}/chunk → posts/, ${useFrontOnly ? "AsreKhodroFront" : "AsreKhodroBack → Front fallback"})`
  );

  let total = resumed.total;
  let postsWithImageUrl = resumed.postsWithImageUrl;
  let offset = resumed.offset;
  let files = resumed.files;
  let skippedPosts = resumed.skippedPosts ?? 0;

  while (true) {
    let rows;
    let skippedInBatch = 0;

    try {
      if (sourceMode === "front" || backUnavailable) {
        const result = fetchFrontPostsPageSafely(
          connectionProfile,
          offset,
          CHUNK_SIZE
        );
        rows = result.rows;
        skippedInBatch = result.skipped;
      } else {
        const page = sqlPage(offset, CHUNK_SIZE);
        try {
          rows = runJsonQuery(
            connectionProfile,
            "AsreKhodroBack",
            postsBackQuery("", page),
            { viaFile: true }
          );
        } catch (err) {
          if (sourceMode === "auto" && isBackCorruptionError(err)) {
            markBackUnavailable("posts");
            const result = fetchFrontPostsPageSafely(
              connectionProfile,
              offset,
              CHUNK_SIZE
            );
            rows = result.rows;
            skippedInBatch = result.skipped;
          } else {
            throw enrichSqlcmdError(err);
          }
        }
      }
    } catch (err) {
      throw enrichSqlcmdError(err);
    }

    if (!rows.length) {
      if (skippedInBatch > 0) {
        offset += CHUNK_SIZE;
        skippedPosts += skippedInBatch;
        saveExportProgress(outputDir, "posts", {
          complete: false,
          files,
          rows: total,
          offset,
          skippedPosts,
          postsWithImageUrl,
        });
        console.log(
          `    posts skipped ${skippedInBatch} unreadable row(s) at offset ${offset - CHUNK_SIZE} (total ${total})`
        );
        continue;
      }

      saveExportProgress(outputDir, "posts", {
        complete: true,
        files,
        rows: total,
        offset,
        skippedPosts,
        postsWithImageUrl,
      });
      break;
    }

    for (const row of rows) {
      delete row.publishSort;
    }
    enrichPostImageUrls(rows);
    for (const row of rows) {
      if (typeof row.imageUrl === "string" && row.imageUrl.trim() !== "") {
        postsWithImageUrl += 1;
      }
    }

    files += 1;
    writeChunkFile(outputDir, "posts", files, rows);
    total += rows.length;
    skippedPosts += skippedInBatch;
    saveExportProgress(outputDir, "posts", {
      complete: false,
      files,
      rows: total,
      offset: offset + CHUNK_SIZE,
      skippedPosts,
      postsWithImageUrl,
    });
    console.log(
      `    posts chunk ${files}: +${rows.length} (total ${total})${skippedInBatch ? `, skipped ${skippedInBatch}` : ""}`
    );
    offset += CHUNK_SIZE;

    if (rows.length + skippedInBatch < CHUNK_SIZE) {
      saveExportProgress(outputDir, "posts", {
        complete: true,
        files,
        rows: total,
        offset,
        skippedPosts,
        postsWithImageUrl,
      });
      break;
    }
  }

  return {
    posts: [],
    chunked: files > 0,
    postCount: total,
    postFiles: files,
    postsWithImageUrl,
  };
}

/** 0 or negative = export all rows (no SQL TOP). */
function sqlTop(limit) {
  const n = Number(limit);
  if (!Number.isFinite(n) || n <= 0) {
    return "";
  }

  return `TOP (${Math.floor(n)}) `;
}

function sqlPage(offset, batchSize) {
  const start = Math.max(0, Number(offset) || 0);
  const size = Math.max(1, Number(batchSize) || CHUNK_SIZE);
  return `
ORDER BY publishSort DESC, contentId DESC
OFFSET ${start} ROWS FETCH NEXT ${size} ROWS ONLY`;
}

function fetchFrontPostsPage(connectionProfile, offset, size) {
  return runJsonQuery(
    connectionProfile,
    "AsreKhodroFront",
    postsFrontQuery("", sqlPage(offset, size)),
    { viaFile: true }
  );
}

function fetchFrontPostsPageSafely(connectionProfile, offset, size) {
  try {
    return {
      rows: fetchFrontPostsPage(connectionProfile, offset, size),
      skipped: 0,
    };
  } catch (err) {
    if (!isSqlCorruptionError(err)) {
      throw err;
    }

    if (size <= MIN_POST_BATCH_SIZE) {
      console.warn(`    ! skipped unreadable post at SQL offset ${offset} (Msg 823)`);
      return { rows: [], skipped: 1 };
    }

    const leftSize = Math.floor(size / 2);
    const rightSize = size - leftSize;
    console.warn(
      `    ! post batch at SQL offset ${offset} failed (Msg 823); retrying as ${leftSize}+${rightSize}`
    );

    const left = fetchFrontPostsPageSafely(connectionProfile, offset, leftSize);
    const right = fetchFrontPostsPageSafely(
      connectionProfile,
      offset + leftSize,
      rightSize
    );

    return {
      rows: [...left.rows, ...right.rows],
      skipped: left.skipped + right.skipped,
    };
  }
}

function sqlFrontMainImageFileUrl(contentIdExpr, scAlias = "sc", database = "dbo") {
  const cfTable =
    database === "dbo" ? "dbo.ContentFiles" : `${database}.dbo.ContentFiles`;

  return `(
    SELECT TOP 1 NULLIF(LTRIM(RTRIM(cf.URL)), '')
    FROM ${cfTable} cf
    WHERE cf.ContentId = ${contentIdExpr}
      AND cf.URL IS NOT NULL
      AND LTRIM(RTRIM(cf.URL)) <> ''
    ORDER BY
      CASE
        WHEN ${scAlias}.MainImageId IS NOT NULL AND cf.RowId = ${scAlias}.MainImageId THEN 0
        ELSE 1
      END,
      CASE WHEN cf.IsMain = 1 THEN 0 ELSE 1 END,
      cf.ImageDimensionId DESC,
      cf.RowId DESC
  )`;
}

function sqlPostImageUrlExpression({
  contentIdExpr = "ci.Id",
  scAlias = "sc",
  mlcAlias = "mlc",
  includeMlc = true,
} = {}) {
  const parts = [
    sqlFrontMainImageFileUrl(contentIdExpr, scAlias, "AsreKhodroFront"),
    `NULLIF(LTRIM(RTRIM(${scAlias}.ImageURL)), '')`,
  ];

  if (includeMlc) {
    parts.push(`NULLIF(LTRIM(RTRIM(${mlcAlias}.ImageURL)), '')`);
  }

  return `COALESCE(
    ${parts.join(",\n    ")}
  )`;
}

function sqlFrontBestContentFileUrl(scAlias = "sc", database = "dbo") {
  const cfTable =
    database === "dbo" ? "dbo.ContentFiles" : `${database}.dbo.ContentFiles`;

  return `(
    SELECT TOP 1 NULLIF(LTRIM(RTRIM(cf.URL)), '')
    FROM ${cfTable} cf
    WHERE cf.ContentId = ${scAlias}.ContentId
      AND cf.URL IS NOT NULL
      AND LTRIM(RTRIM(cf.URL)) <> ''
    ORDER BY
      CASE
        WHEN ${scAlias}.MainImageId IS NOT NULL AND cf.RowId = ${scAlias}.MainImageId THEN 0
        ELSE 1
      END,
      CASE WHEN cf.IsMain = 1 THEN 0 ELSE 1 END,
      cf.ImageDimensionId DESC,
      cf.RowId DESC
  )`;
}

function sqlFrontPostImageUrlExpression(scAlias = "sc") {
  return `COALESCE(
    ${sqlFrontBestContentFileUrl(scAlias)},
    NULLIF(LTRIM(RTRIM(${scAlias}.ImageURL)), '')
  )`;
}

/** @deprecated use sqlFrontPostImageUrlExpression */
function sqlFrontImageUrlColumn(scAlias = "sc") {
  return `${sqlFrontPostImageUrlExpression(scAlias)} AS imageUrl`;
}

function scoreImagePath(path) {
  const normalized = String(path).toLowerCase();
  if (/(?:^|\/)(thumb|thumbnail|thumbs|small|mini|list|preview)(?:\/|$)/.test(normalized)) {
    return 1;
  }
  if (/(?:^|\/)(large|original|full|max)(?:\/|$)/.test(normalized)) {
    return 100000 + path.length;
  }
  const dimensions = normalized.match(/(\d{2,4})[xX](\d{2,4})/);
  if (dimensions) {
    return Number(dimensions[1]) * Number(dimensions[2]);
  }
  return 1000 + path.length;
}

function extractImagesFromBody(body) {
  if (typeof body !== "string" || body.trim() === "") {
    return [];
  }

  const found = [];
  for (const match of body.matchAll(/<img[^>]+src=["']([^"']+)["']/gi)) {
    found.push(match[1]);
  }
  for (const match of body.matchAll(
    /(\/Uploaded\/Image\/[^\s"'<>&]+\.(?:jpe?g|png|gif|webp))/gi
  )) {
    found.push(match[1]);
  }
  return found;
}

function pickBestImageUrl(candidates) {
  let best = "";
  let bestScore = -1;

  for (const candidate of new Set(candidates)) {
    const trimmed = String(candidate).trim();
    if (!trimmed) {
      continue;
    }

    const score = scoreImagePath(trimmed);
    if (score > bestScore) {
      bestScore = score;
      best = trimmed;
    }
  }

  return best;
}

function resolveBestPostImageUrl(row) {
  if (typeof row.imageUrl === "string" && row.imageUrl.trim() !== "") {
    return row.imageUrl.trim();
  }

  return pickBestImageUrl(extractImagesFromBody(row.body));
}

function enrichPostImageUrls(rows) {
  for (const row of rows) {
    row.imageUrl = resolveBestPostImageUrl(row);
  }
  return rows;
}

function chunkArray(items, size) {
  const chunks = [];
  for (let i = 0; i < items.length; i += size) {
    chunks.push(items.slice(i, i + size));
  }
  return chunks;
}

function normalizeContentIds(contentIds) {
  return [
    ...new Set(
      contentIds
        .map((id) => Number(id))
        .filter((id) => Number.isFinite(id) && id > 0)
    ),
  ];
}

function isSqlCorruptionError(err) {
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

function contentFilesImagesQuery(contentIds) {
  const ids = normalizeContentIds(contentIds);
  if (!ids.length) {
    return "";
  }

  return `
SET NOCOUNT ON;
SELECT
  cf.ContentId AS contentId,
  cf.URL AS url,
  cf.RowId AS rowId,
  cf.ImageDimensionId AS imageDimensionId,
  cf.IsMain AS isMain,
  sc.MainImageId AS mainImageId
FROM dbo.ContentFiles cf
INNER JOIN dbo.SingleContent sc ON sc.ContentId = cf.ContentId
WHERE cf.ContentId IN (${ids.join(",")})
  AND cf.URL IS NOT NULL
  AND LTRIM(RTRIM(cf.URL)) <> ''
FOR JSON PATH;
`;
}

function scoreContentFileCandidate(row) {
  const url = typeof row.url === "string" ? row.url.trim() : "";
  if (!url) {
    return -1;
  }

  let score = scoreImagePath(url);
  if (
    row.mainImageId != null &&
    row.rowId != null &&
    Number(row.rowId) === Number(row.mainImageId)
  ) {
    score += 1_000_000_000;
  }
  if (row.isMain === 1 || row.isMain === true) {
    score += 100_000_000;
  }
  score += (Number(row.imageDimensionId) || 0) * 10_000;
  return score;
}

function buildContentFileImageMap(fileRows) {
  const bestRow = new Map();

  for (const row of fileRows) {
    const contentId = Number(row.contentId);
    if (!contentId || typeof row.url !== "string" || row.url.trim() === "") {
      continue;
    }

    const previous = bestRow.get(contentId);
    if (!previous || scoreContentFileCandidate(row) > scoreContentFileCandidate(previous)) {
      bestRow.set(contentId, row);
    }
  }

  const map = new Map();
  for (const [contentId, row] of bestRow) {
    map.set(contentId, row.url.trim());
  }
  return map;
}

function fetchContentFileImageRows(connectionProfile, contentIds, stats = null) {
  const ids = normalizeContentIds(contentIds);
  if (!ids.length) {
    return [];
  }

  try {
    return runJsonQuery(
      connectionProfile,
      "AsreKhodroFront",
      contentFilesImagesQuery(ids),
      { viaFile: true }
    );
  } catch (err) {
    if (isSqlCorruptionError(err) && ids.length > CONTENT_FILES_MIN_BATCH) {
      const mid = Math.ceil(ids.length / 2);
      return [
        ...fetchContentFileImageRows(connectionProfile, ids.slice(0, mid), stats),
        ...fetchContentFileImageRows(connectionProfile, ids.slice(mid), stats),
      ];
    }
    if (isSqlCorruptionError(err)) {
      console.warn(`    ! ContentFiles skipped for ${ids.length} content id(s) — Msg 605/823/824`);
      if (stats) {
        stats.skippedBatches += 1;
      }
      return [];
    }
    throw enrichSqlcmdError(err);
  }
}

function applyBestPostImage(post, contentFileUrl) {
  if (contentFileUrl) {
    post.imageUrl = contentFileUrl;
    return;
  }

  post.imageUrl = resolveBestPostImageUrl(post);
}

function countPostsWithImageUrlInRows(rows) {
  return rows.filter(
    (row) => typeof row.imageUrl === "string" && row.imageUrl.trim() !== ""
  ).length;
}

function enrichPostsRowsFromContentFiles(connectionProfile, rows, stats) {
  const ids = normalizeContentIds(rows.map((row) => row.contentId));
  const imageMap = new Map();

  for (const batch of chunkArray(ids, CONTENT_FILES_ID_BATCH)) {
    const fileRows = fetchContentFileImageRows(connectionProfile, batch, stats);
    for (const [contentId, url] of buildContentFileImageMap(fileRows)) {
      imageMap.set(contentId, url);
    }
  }

  for (const row of rows) {
    const contentId = Number(row.contentId);
    const contentFileUrl = imageMap.get(contentId);
    const before =
      typeof row.imageUrl === "string" ? row.imageUrl.trim() : "";
    applyBestPostImage(row, contentFileUrl);
    const after = typeof row.imageUrl === "string" ? row.imageUrl.trim() : "";
    if (contentFileUrl && after !== before) {
      stats.updated += 1;
    }
  }
}

function enrichExportedPostImages(
  connectionProfile,
  sourceMode,
  outputDir,
  postExport,
  options = {}
) {
  const { resume = true, progress = {} } = options;
  const useFront = sourceMode === "front" || backUnavailable;
  if (!useFront) {
    return { postsWithImageUrl: postExport.postsWithImageUrl, updated: 0 };
  }

  const saved = progress.postContentFileImages;
  if (resume && saved?.complete && saved?.version === CONTENT_FILES_ENRICH_VERSION) {
    console.log(
      `  → post images from ContentFiles (resume: already complete, ${saved.updated ?? 0} upgraded)`
    );
    return {
      postsWithImageUrl: saved.postsWithImageUrl ?? postExport.postsWithImageUrl,
      updated: saved.updated ?? 0,
    };
  }

  console.log(
    `  → post images from ContentFiles (AsreKhodroFront, batched ${CONTENT_FILES_ID_BATCH} ids/query)`
  );

  const stats = { updated: 0, skippedBatches: 0, postsWithImageUrl: 0 };

  if (postExport.chunked) {
    const chunkFiles = listChunkFiles(outputDir, "posts");
    if (!chunkFiles.length) {
      return { postsWithImageUrl: 0, updated: 0 };
    }

    const startChunk = resume && saved?.chunksDone ? saved.chunksDone : 0;

    for (let i = startChunk; i < chunkFiles.length; i += 1) {
      const file = chunkFiles[i];
      const rows = JSON.parse(fs.readFileSync(file, "utf8"));
      const beforeUpdated = stats.updated;
      enrichPostsRowsFromContentFiles(connectionProfile, rows, stats);
      fs.writeFileSync(file, `${JSON.stringify(rows, null, 2)}\n`, "utf8");

      saveExportProgress(outputDir, "postContentFileImages", {
        complete: false,
        version: CONTENT_FILES_ENRICH_VERSION,
        chunksDone: i + 1,
        totalChunks: chunkFiles.length,
        updated: stats.updated,
        skippedBatches: stats.skippedBatches,
      });

      console.log(
        `    posts chunk ${i + 1}/${chunkFiles.length}: +${stats.updated - beforeUpdated} image upgrades`
      );
    }

    for (const file of chunkFiles) {
      const rows = JSON.parse(fs.readFileSync(file, "utf8"));
      stats.postsWithImageUrl += countPostsWithImageUrlInRows(rows);
    }
  } else {
    let rows = postExport.posts;
    const postsPath = path.join(outputDir, "posts.json");
    if (!rows?.length && fs.existsSync(postsPath)) {
      rows = JSON.parse(fs.readFileSync(postsPath, "utf8"));
    }

    if (rows?.length) {
      enrichPostsRowsFromContentFiles(connectionProfile, rows, stats);
      stats.postsWithImageUrl = countPostsWithImageUrlInRows(rows);
      postExport.posts = rows;
      writeJson(outputDir, "posts.json", rows);
    }
  }

  saveExportProgress(outputDir, "postContentFileImages", {
    complete: true,
    version: CONTENT_FILES_ENRICH_VERSION,
    chunksDone: postExport.chunked
      ? listChunkFiles(outputDir, "posts").length
      : 1,
    updated: stats.updated,
    skippedBatches: stats.skippedBatches,
    postsWithImageUrl: stats.postsWithImageUrl,
  });

  console.log(
    `  → post ContentFiles enrichment: ${stats.updated} upgraded, ${stats.skippedBatches} batch(es) skipped (Msg 605/823/824)`
  );

  return { postsWithImageUrl: stats.postsWithImageUrl, updated: stats.updated };
}

function postsFrontQuery(topClause, pageClause) {
  if (pageClause) {
    return `
SET NOCOUNT ON;
SELECT
  contentId,
  domainId,
  contentTypeId,
  statusId,
  title,
  overTitle,
  underTitle,
  excerpt,
  body,
  footer,
  author,
  publishTime,
  contentTime,
  expireTime,
  imageUrl
FROM (
  SELECT
    sc.ContentId AS contentId,
    sc.DomainId AS domainId,
    CAST(NULL AS int) AS contentTypeId,
    CAST(3 AS tinyint) AS statusId,
    sc.Title AS title,
    sc.OverTitle AS overTitle,
    sc.UnderTitle AS underTitle,
    sc.ShortBody AS excerpt,
    sc.Body AS body,
    sc.Footer AS footer,
    sc.Author AS author,
    sc.PublishTime AS publishTime,
    sc.PublishTime AS contentTime,
    CAST(NULL AS datetime) AS expireTime,
    sc.PublishTime AS publishSort,
    ${sqlFrontImageUrlColumn("sc")}
  FROM dbo.SingleContent sc
) AS ranked
${pageClause}
FOR JSON PATH;
`;
  }

  return `
SET NOCOUNT ON;
SELECT ${topClause}
  sc.ContentId AS contentId,
  sc.DomainId AS domainId,
  CAST(NULL AS int) AS contentTypeId,
  CAST(3 AS tinyint) AS statusId,
  sc.Title AS title,
  sc.OverTitle AS overTitle,
  sc.UnderTitle AS underTitle,
  sc.ShortBody AS excerpt,
  sc.Body AS body,
  sc.Footer AS footer,
  sc.Author AS author,
  sc.PublishTime AS publishTime,
  sc.PublishTime AS contentTime,
  CAST(NULL AS datetime) AS expireTime,
  ${sqlFrontImageUrlColumn("sc")}
FROM dbo.SingleContent sc
ORDER BY sc.PublishTime DESC
FOR JSON PATH;
`;
}

function postsBackQuery(topClause, pageClause) {
  if (pageClause) {
    return `
SET NOCOUNT ON;
SELECT
  contentId,
  domainId,
  contentTypeId,
  statusId,
  title,
  overTitle,
  underTitle,
  excerpt,
  body,
  footer,
  author,
  publishTime,
  contentTime,
  expireTime,
  imageUrl
FROM (
  SELECT
    ci.Id AS contentId,
    ci.DomainId AS domainId,
    ci.ContentTypeId AS contentTypeId,
    ci.StatusId AS statusId,
    ci.Title AS title,
    cc.OverTitle AS overTitle,
    cc.UnderTitle AS underTitle,
    cc.ShortBody AS excerpt,
    cc.BodyText AS body,
    cc.Footer AS footer,
    cc.Author AS author,
    cc.PublishTime AS publishTime,
    cc.ContentTime AS contentTime,
    cc.ExpireTime AS expireTime,
    cc.PublishTime AS publishSort,
    ${sqlPostImageUrlExpression()} AS imageUrl
  FROM dbo.ContentInitialize ci
  INNER JOIN dbo.ContentCommonInfo cc ON ci.Id = cc.ContentId
  LEFT JOIN AsreKhodroFront.dbo.SingleContent sc ON sc.ContentId = ci.Id
  LEFT JOIN AsreKhodroFront.dbo.MainLastContents mlc ON mlc.ContentId = ci.Id
  WHERE ci.StatusId IN (1, 3)
) AS ranked
${pageClause}
FOR JSON PATH;
`;
  }

  return `
SET NOCOUNT ON;
SELECT ${topClause}
  ci.Id AS contentId,
  ci.DomainId AS domainId,
  ci.ContentTypeId AS contentTypeId,
  ci.StatusId AS statusId,
  ci.Title AS title,
  cc.OverTitle AS overTitle,
  cc.UnderTitle AS underTitle,
  cc.ShortBody AS excerpt,
  cc.BodyText AS body,
  cc.Footer AS footer,
  cc.Author AS author,
  cc.PublishTime AS publishTime,
  cc.ContentTime AS contentTime,
  cc.ExpireTime AS expireTime,
  ${sqlPostImageUrlExpression()} AS imageUrl
FROM dbo.ContentInitialize ci
INNER JOIN dbo.ContentCommonInfo cc ON ci.Id = cc.ContentId
LEFT JOIN AsreKhodroFront.dbo.SingleContent sc ON sc.ContentId = ci.Id
LEFT JOIN AsreKhodroFront.dbo.MainLastContents mlc ON mlc.ContentId = ci.Id
WHERE ci.StatusId IN (1, 3)
ORDER BY cc.PublishTime DESC
FOR JSON PATH;
`;
}

function parseSqlcmdJson(raw) {
  const jsonText = raw
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter((line) => line && !/^\(\d+ rows affected\)/.test(line))
    .join("");

  if (!jsonText) {
    return [];
  }

  if (jsonText.startsWith("Msg ")) {
    throw new Error(jsonText.split("\n")[0]);
  }

  try {
    return JSON.parse(jsonText);
  } catch (err) {
    throw new Error(
      `Failed to parse SQL JSON (${jsonText.length} chars). Batch may be too large — retry with a smaller batch. ${err.message}`
    );
  }
}

function sleep(ms) {
  Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, ms);
}

function isTransientSqlcmdError(err) {
  const text = [err.stdout, err.stderr, err.message].filter(Boolean).join("\n");
  return (
    err.status === 10053 ||
    err.status === 10054 ||
    /forcibly closed|connection.*closed|transport-level error|timeout|timed out|10053|10054/i.test(
      text
    )
  );
}

function execSqlcmdWithRetries(args, options = {}) {
  const { retries = SQLCMD_RETRIES, label = "query", ...execOptions } = options;

  for (let attempt = 1; attempt <= retries + 1; attempt += 1) {
    try {
      return execFileSync("sqlcmd", args, execOptions);
    } catch (err) {
      if (!isTransientSqlcmdError(err) || attempt > retries) {
        throw err;
      }

      const delayMs = Math.min(30_000, 2_000 * attempt);
      console.warn(
        `  ! sqlcmd ${label} disconnected (status=${err.status ?? "unknown"}), retry ${attempt}/${retries} in ${Math.round(delayMs / 1000)}s`
      );
      sleep(delayMs);
    }
  }

  throw new Error(`sqlcmd ${label} failed after retries`);
}

function runJsonQuery(connectionProfile, database, query, options = {}) {
  const { viaFile = false, maxBuffer = 512 * 1024 * 1024 } = options;
  const args = [
    ...buildSqlcmdBaseArgs(connectionProfile),
    "-d",
    database,
    "-y",
    "0",
    "-Q",
    query,
    "-f",
    "o:65001",
  ];

  if (viaFile) {
    const tmpFile = path.join(
      projectRoot,
      `.tmp-export-${process.pid}-${Date.now()}.json`
    );
    try {
      execSqlcmdWithRetries([...args, "-o", tmpFile], {
        encoding: "utf8",
        maxBuffer: 64 * 1024 * 1024,
        label: `${database} export`,
      });
      return parseSqlcmdJson(fs.readFileSync(tmpFile, "utf8"));
    } finally {
      try {
        fs.unlinkSync(tmpFile);
      } catch {
        /* ignore */
      }
    }
  }

  const stdout = execSqlcmdWithRetries(args, {
    encoding: "utf8",
    maxBuffer,
    label: `${database} query`,
  });
  return parseSqlcmdJson(stdout);
}

function enrichSqlcmdError(err) {
  const detail = err.stdout || err.stderr || err.message || String(err);
  if (detail.includes("Msg 823")) {
    err.message =
      "AsreKhodroBack database file is corrupted on the server (Msg 823). " +
      "Re-run with --source=front to export published content from AsreKhodroFront, " +
      "or ask the DBA to restore AsreKhodroBack from backup.\n\n" +
      detail.trim();
  }
  return err;
}

function isBackCorruptionError(err) {
  const text = [err.stdout, err.stderr, err.message].filter(Boolean).join("\n");
  return (
    err.status === 605 ||
    err.status === 823 ||
    err.status === 824 ||
    text.includes("Msg 605") ||
    text.includes("Msg 823") ||
    text.includes("Msg 824") ||
    text.includes("AsreKhodroBack.mdf")
  );
}

function runBackOrFront(connectionProfile, sourceMode, label, backQuery, frontQuery) {
  if (sourceMode === "front" || (backUnavailable && frontQuery)) {
    if (!frontQuery) {
      console.warn(`  [skip] ${label} — not available from AsreKhodroFront`);
      return [];
    }
    console.log(`  → ${label} (AsreKhodroFront)`);
    return runJsonQuery(connectionProfile, "AsreKhodroFront", frontQuery);
  }

  try {
    console.log(`  → ${label} (AsreKhodroBack)`);
    return runJsonQuery(connectionProfile, "AsreKhodroBack", backQuery);
  } catch (err) {
    if (sourceMode === "auto" && frontQuery && isBackCorruptionError(err)) {
      markBackUnavailable(label);
      return runJsonQuery(connectionProfile, "AsreKhodroFront", frontQuery);
    }
    throw enrichSqlcmdError(err);
  }
}

function runBackOnly(connectionProfile, sourceMode, label, backQuery, database = "AsreKhodroBack") {
  if (sourceMode === "front" && database === "AsreKhodroBack") {
    console.warn(`  [skip] ${label} — requires ${database}`);
    return [];
  }

  if (backUnavailable && database === "AsreKhodroBack") {
    console.warn(`  [skip] ${label} — ${database} unavailable (Msg 823)`);
    return [];
  }

  try {
    console.log(`  → ${label} (${database})`);
    return runJsonQuery(connectionProfile, database, backQuery);
  } catch (err) {
    if (sourceMode === "auto" && isBackCorruptionError(err)) {
      markBackUnavailable(label);
      console.warn(`  [skip] ${label} — ${database} unavailable (Msg 823)`);
      return [];
    }
    throw enrichSqlcmdError(err);
  }
}

function writeJson(outputDir, name, data) {
  fs.mkdirSync(outputDir, { recursive: true });
  fs.writeFileSync(
    path.join(outputDir, name),
    `${JSON.stringify(data, null, 2)}\n`,
    "utf8"
  );
}

const {
  profile: profileName,
  server: serverOverride,
  source: sourceMode,
  exportAll,
  resume,
  enrichImagesOnly,
  skipContentFileImages,
  limit,
  reviewLimit,
  magazineLimit,
  output,
} = parseArgs(process.argv);

const exportProgress = clearEmptyBackOnlyProgress(
  loadExportProgress(output),
  sourceMode
);

const { name: activeProfileName, profile: connectionProfile } = serverOverride
  ? { name: "cli", profile: { server: serverOverride } }
  : resolveConnectionProfile(profileName);

if (enrichImagesOnly) {
  console.log(
    `Enriching post images from ContentFiles (profile=${activeProfileName}, server=${connectionProfile.server}, source=${sourceMode}, resume=${resume}) → ${output}`
  );
  const chunkFiles = listChunkFiles(output, "posts");
  const postsPath = path.join(output, "posts.json");
  const postExport = {
    chunked: chunkFiles.length > 0,
    posts:
      chunkFiles.length > 0 || !fs.existsSync(postsPath)
        ? []
        : JSON.parse(fs.readFileSync(postsPath, "utf8")),
    postCount: chunkFiles.length
      ? countRowsInChunkFiles(chunkFiles)
      : fs.existsSync(postsPath)
        ? JSON.parse(fs.readFileSync(postsPath, "utf8")).length
        : 0,
    postsWithImageUrl: 0,
  };

  if (postExport.postCount === 0) {
    console.error("No post JSON found. Export posts first.");
    process.exit(1);
  }

  const imageResult = enrichExportedPostImages(
    connectionProfile,
    sourceMode,
    output,
    postExport,
    { resume, progress: exportProgress }
  );
  console.log(
    `Done: ${imageResult.updated} image upgrades, ${imageResult.postsWithImageUrl} posts with imageUrl`
  );
  process.exit(0);
}

console.log(
  `Exporting WordPress data (profile=${activeProfileName}, server=${connectionProfile.server}, source=${sourceMode}, mode=${exportAll || limit <= 0 ? "all" : "sample"}, resume=${resume}, limit=${limit || "all"}, review-limit=${reviewLimit || "all"}, magazine-limit=${magazineLimit || "all"}) → ${output}`
);

const categoriesBackQuery = `
SET NOCOUNT ON;
SELECT
  Id AS id,
  DomainId AS domainId,
  ParentId AS parentId,
  Title AS title,
  [Description] AS [description],
  Periority AS priority,
  StatusId AS statusId
FROM dbo.Categories
ORDER BY Id
FOR JSON PATH;
`;

const categoriesFrontQuery = `
SET NOCOUNT ON;
SELECT
  Id AS id,
  DomainId AS domainId,
  ParentId AS parentId,
  Title AS title,
  [Description] AS [description],
  Periority AS priority,
  StatusId AS statusId
FROM dbo.Categories
ORDER BY Id
FOR JSON PATH;
`;

console.log("Querying SQL Server…");

if (!resume) {
  const progress = loadExportProgress(output);
  if (progress.postContentFileImages) {
    delete progress.postContentFileImages;
    fs.writeFileSync(
      progressPath(output),
      `${JSON.stringify(progress, null, 2)}\n`,
      "utf8"
    );
  }
}

const categoriesPath = path.join(output, "categories.json");
let categories;
if (resume && fs.existsSync(categoriesPath)) {
  categories = JSON.parse(fs.readFileSync(categoriesPath, "utf8"));
  console.log(`  → categories (resumed from disk: ${categories.length})`);
} else {
  categories = runBackOrFront(
    connectionProfile,
    sourceMode,
    "categories",
    categoriesBackQuery,
    categoriesFrontQuery
  );
  writeJson(output, "categories.json", categories);
  saveExportProgress(output, "categories", {
    complete: true,
    rows: categories.length,
  });
}

console.log("Exporting front homepage sections…");
const frontSectionsExport = exportFrontHomeSections(connectionProfile, output);

const postExport = exportPosts(connectionProfile, sourceMode, limit, output, {
  resume,
  progress: exportProgress,
});
let posts = postExport.posts;
let postsWithImageUrl = postExport.postsWithImageUrl;

if (!postExport.chunked && limit > 0 && frontSectionsExport.contentIds.length) {
  posts = mergeRequiredPosts(
    posts,
    frontSectionsExport.contentIds,
    connectionProfile,
    sourceMode
  );
  postExport.posts = posts;
  postExport.postCount = posts.length;
  postExport.postsWithImageUrl = posts.filter(
    (post) => typeof post.imageUrl === "string" && post.imageUrl.trim() !== ""
  ).length;
  postsWithImageUrl = postExport.postsWithImageUrl;
  writeJson(output, "posts.json", posts);
}

if (!skipContentFileImages) {
  const imageResult = enrichExportedPostImages(
    connectionProfile,
    sourceMode,
    output,
    postExport,
    { resume, progress: exportProgress }
  );
  postsWithImageUrl = imageResult.postsWithImageUrl || postsWithImageUrl;
  if (!postExport.chunked) {
    posts = postExport.posts;
  }
}

if (postExport.postCount === 0) {
  console.error("No posts exported. Check SQL Server connection and data.");
  process.exit(1);
}

const postCount = postExport.postCount;
const fullExport = limit <= 0;
const idList = fullExport ? "" : posts.map((p) => p.contentId).join(",");

const postCategoriesBackBase = `
SET NOCOUNT ON;
SELECT
  ContentId AS contentId,
  CategoryId AS categoryId,
  IsMain AS isMain
FROM dbo.ContentCategories
${fullExport ? "" : `WHERE ContentId IN (${idList})`}
FOR JSON PATH;
`;

const postCategoriesFrontBase = `
SET NOCOUNT ON;
SELECT
  ContentId AS contentId,
  CategoryId AS categoryId,
  IsMain AS isMain
FROM dbo.ContentCategories
${fullExport ? "" : `WHERE ContentId IN (${idList})`}
FOR JSON PATH;
`;

const tagsBackBase = `
SET NOCOUNT ON;
SELECT
  kc.ContentId AS contentId,
  kc.KeywordId AS keywordId,
  k.Keyword AS tag
FROM dbo.KeywordsContent kc
INNER JOIN dbo.Keywords k ON kc.KeywordId = k.Id
${fullExport ? "" : `WHERE kc.ContentId IN (${idList})`}
FOR JSON PATH;
`;

const tagsFrontBase = `
SET NOCOUNT ON;
SELECT
  ContentId AS contentId,
  KeywordId AS keywordId,
  KeywordTitle AS tag
FROM dbo.KeywordsContent
${fullExport ? "" : `WHERE ContentId IN (${idList})`}
FOR JSON PATH;
`;

const postCategoriesSample = fullExport
  ? []
  : runBackOrFront(
      connectionProfile,
      sourceMode,
      "post-categories",
      postCategoriesBackBase,
      postCategoriesFrontBase
    );

const postCategoryExport = exportBatchedCollection({
  outputDir: output,
  collection: "post-categories",
  label: "post-categories",
  fullExport,
  resume,
  progress: exportProgress,
  sampleRows: postCategoriesSample,
  legacyFilename: "post-categories.json",
  trackUnique: true,
  fetchPage: (offset, size) =>
    fetchPagedBackOrFront(
      connectionProfile,
      sourceMode,
      "post-categories",
      postCategoriesBackBase,
      postCategoriesFrontBase,
      offset,
      size,
      "ContentId, CategoryId"
    ),
});

const tagsSample = fullExport
  ? []
  : runBackOrFront(
      connectionProfile,
      sourceMode,
      "tags",
      tagsBackBase,
      tagsFrontBase
    );

const tagExport = exportBatchedCollection({
  outputDir: output,
  collection: "tags",
  label: "tags",
  fullExport,
  resume,
  progress: exportProgress,
  sampleRows: tagsSample,
  legacyFilename: "tags.json",
  trackUnique: true,
  fetchPage: (offset, size) =>
    fetchPagedBackOrFront(
      connectionProfile,
      sourceMode,
      "tags",
      tagsBackBase,
      tagsFrontBase,
      offset,
      size,
      "ContentId, KeywordId"
    ),
});

const postRelationsFilter = fullExport
  ? ""
  : `AND ParentContentId IN (${idList}) AND ChildContentId IN (${idList})`;

const postRelationsBackBase = `
SET NOCOUNT ON;
SELECT
  ParentContentId AS parentContentId,
  ChildContentId AS childContentId
FROM dbo.ContentRelation
WHERE IsActive = 1
${postRelationsFilter}
FOR JSON PATH;
`;

const postRelationsFrontBase = `
SET NOCOUNT ON;
SELECT
  ParentContentId AS parentContentId,
  ChildContentId AS childContentId
FROM dbo.ContentsRelation
WHERE IsActive = 1
${postRelationsFilter}
FOR JSON PATH;
`;

const postRelationsSample = fullExport
  ? []
  : runBackOrFront(
      connectionProfile,
      sourceMode,
      "post-relations",
      postRelationsBackBase,
      postRelationsFrontBase
    );

const postRelationExport = exportBatchedCollection({
  outputDir: output,
  collection: "post-relations",
  label: "post-relations",
  fullExport,
  resume,
  progress: exportProgress,
  sampleRows: postRelationsSample,
  legacyFilename: "post-relations.json",
  trackUnique: true,
  trackUniqueField: "parentContentId",
  fetchPage: (offset, size) =>
    fetchPagedBackOrFront(
      connectionProfile,
      sourceMode,
      "post-relations",
      postRelationsBackBase,
      postRelationsFrontBase,
      offset,
      size,
      "ParentContentId, ChildContentId"
    ),
});

const commentsBackBase = `
SET NOCOUNT ON;
SELECT
  ci.Id AS commentId,
  ci.ObjectId AS contentId,
  ci.ParentId AS parentId,
  ci.UserId AS userId,
  ci.StatusId AS statusId,
  ci.CreateTime AS createdAt,
  cc.Message AS content,
  u.AliasName AS authorName,
  u.Email AS authorEmail
FROM dbo.CommentInitialize ci
INNER JOIN dbo.CommentCommonInfo cc ON ci.Id = cc.CommentId
LEFT JOIN dbo.Users u ON ci.UserId = u.Id
${fullExport ? "" : `WHERE ci.ObjectId IN (${idList})`}
FOR JSON PATH;
`;

const commentsSample = fullExport
  ? []
  : runBackOnly(
      connectionProfile,
      sourceMode,
      "comments",
      commentsBackBase,
      "AsreKhodroComments"
    );

const commentExport = exportBatchedCollection({
  outputDir: output,
  collection: "comments",
  label: "comments",
  fullExport,
  resume,
  progress: exportProgress,
  sampleRows: commentsSample,
  legacyFilename: "comments.json",
  fetchPage: (offset, size) =>
    fetchPagedBackOnly(
      connectionProfile,
      sourceMode,
      "comments",
      commentsBackBase,
      "AsreKhodroComments",
      offset,
      size,
      "ci.Id"
    ),
});

const menuPositions = runBackOnly(
  connectionProfile,
  sourceMode,
  "menu-positions",
  `
SET NOCOUNT ON;
SELECT
  Id AS id,
  Name AS name,
  Description AS [description]
FROM dbo.MenuPosition
ORDER BY Id
FOR JSON PATH;
`
);

const adsBackBase = `
SET NOCOUNT ON;
SELECT
  a.Id AS id,
  a.DomainId AS domainId,
  a.MenuPositionId AS menuPositionId,
  mp.Name AS menuPositionName,
  a.Title AS title,
  a.LinkAddress AS link,
  a.HTML AS html,
  a.Width AS width,
  a.Height AS height,
  a.Periority AS priority,
  a.CreateTime AS createTime,
  CASE WHEN a.isActive = 1 THEN 1 ELSE 0 END AS isActive,
  COALESCE(
    (
      SELECT TOP 1 fft.Url
      FROM dbo.FilesFiletypes fft
      WHERE fft.FileId = a.FileId
        AND fft.Url IS NOT NULL
        AND LTRIM(RTRIM(fft.Url)) <> ''
      ORDER BY fft.Id DESC
    ),
    (
      SELECT TOP 1 fa.FileURL
      FROM AsreKhodroFront.dbo.Advertisements fa
      WHERE fa.Id = a.Id
        AND fa.FileURL IS NOT NULL
        AND LTRIM(RTRIM(fa.FileURL)) <> ''
    )
  ) AS imageUrl
FROM dbo.Advertisments a
INNER JOIN dbo.MenuPosition mp ON mp.Id = a.MenuPositionId
WHERE a.isActive = 1
FOR JSON PATH;
`;

const adsFrontBase = `
SET NOCOUNT ON;
SELECT
  a.Id AS id,
  a.DomainId AS domainId,
  a.PositionId AS menuPositionId,
  CAST(a.PositionId AS nvarchar(50)) AS menuPositionName,
  a.Title AS title,
  a.Link AS link,
  CAST('' AS nvarchar(max)) AS html,
  a.Width AS width,
  a.Height AS height,
  a.Periority AS priority,
  a.CreateTime AS createTime,
  CASE WHEN a.isActive = 1 THEN 1 ELSE 0 END AS isActive,
  NULLIF(LTRIM(RTRIM(a.FileURL)), '') AS imageUrl
FROM dbo.Advertisements a
WHERE a.isActive = 1
FOR JSON PATH;
`;

const adsSample = fullExport
  ? []
  : runBackOrFront(
      connectionProfile,
      sourceMode,
      "ads",
      adsBackBase.replace(
        /FOR JSON PATH;/,
        "ORDER BY a.MenuPositionId, a.Periority, a.Id\nFOR JSON PATH;"
      ),
      adsFrontBase.replace(
        /FOR JSON PATH;/,
        "ORDER BY a.PositionId, a.Periority, a.Id\nFOR JSON PATH;"
      )
    );

const adExport = exportBatchedCollection({
  outputDir: output,
  collection: "ads",
  label: "ads",
  fullExport,
  resume,
  progress: exportProgress,
  sampleRows: adsSample,
  legacyFilename: "ads.json",
  fetchPage: (offset, size) =>
    fetchPagedBackOrFront(
      connectionProfile,
      sourceMode,
      "ads",
      adsBackBase,
      adsFrontBase,
      offset,
      size,
      "a.MenuPositionId, a.Periority, a.Id",
      "a.PositionId, a.Periority, a.Id"
    ),
});

const videosBackBase = `
SET NOCOUNT ON;
SELECT
  ci.Id AS contentId,
  ci.DomainId AS domainId,
  ci.ContentTypeId AS contentTypeId,
  ci.StatusId AS statusId,
  ci.Title AS title,
  cc.OverTitle AS overTitle,
  cc.UnderTitle AS underTitle,
  cc.ShortBody AS excerpt,
  cc.BodyText AS body,
  cc.Footer AS footer,
  cc.Author AS author,
  cc.PublishTime AS publishTime,
  cc.ContentTime AS contentTime,
  cc.ExpireTime AS expireTime,
  COALESCE(
    (
      SELECT TOP 1 fft.Url
      FROM AsreKhodroBack.dbo.ContentFiles bcf
      INNER JOIN AsreKhodroBack.dbo.FilesFiletypes fft ON fft.FileId = bcf.FileId
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
  ) AS videoUrl,
  COALESCE(
    (
      SELECT TOP 1 fft.Url
      FROM AsreKhodroBack.dbo.ContentFiles bcf
      INNER JOIN AsreKhodroBack.dbo.FilesFiletypes fft ON fft.FileId = bcf.FileId
      LEFT JOIN AsreKhodroBack.dbo.ImageDimension idim ON idim.Id = fft.ImageDimensionId
      WHERE bcf.ContentId = ci.Id
        AND fft.Url IS NOT NULL
        AND LTRIM(RTRIM(fft.Url)) <> ''
        AND fft.Url NOT LIKE '%/Uploaded/Video/%'
      ORDER BY
        CASE WHEN sc.MainImageId IS NOT NULL AND bcf.FileId = sc.MainImageId THEN 0 ELSE 1 END,
        CASE WHEN bcf.IsMain = 1 THEN 0 ELSE 1 END,
        ISNULL(idim.Width, 0) DESC,
        bcf.Periority,
        fft.Id DESC
    ),
    (
      SELECT TOP 1 cf.URL
      FROM AsreKhodroFront.dbo.ContentFiles cf
      LEFT JOIN AsreKhodroBack.dbo.ImageDimension idim ON idim.Id = cf.ImageDimensionId
      WHERE cf.ContentId = ci.Id
        AND cf.URL IS NOT NULL
        AND LTRIM(RTRIM(cf.URL)) <> ''
        AND cf.URL NOT LIKE '%/Uploaded/Video/%'
      ORDER BY
        CASE WHEN sc.MainImageId IS NOT NULL AND cf.FileId = sc.MainImageId THEN 0 ELSE 1 END,
        CASE WHEN cf.IsMain = 1 THEN 0 ELSE 1 END,
        ISNULL(idim.Width, 0) DESC,
        cf.PeriorityInContent,
        cf.RowId DESC
    ),
    (
      SELECT TOP 1 fft.Url
      FROM AsreKhodroBack.dbo.FilesFiletypes fft
      LEFT JOIN AsreKhodroBack.dbo.ImageDimension idim ON idim.Id = fft.ImageDimensionId
      WHERE sc.MainImageId IS NOT NULL
        AND fft.FileId = sc.MainImageId
        AND fft.Url IS NOT NULL
        AND LTRIM(RTRIM(fft.Url)) <> ''
        AND fft.Url NOT LIKE '%/Uploaded/Video/%'
      ORDER BY ISNULL(idim.Width, 0) DESC, fft.Id DESC
    ),
    NULLIF(LTRIM(RTRIM(sc.ImageURL)), ''),
    NULLIF(LTRIM(RTRIM(mlc.ImageURL)), '')
  ) AS imageUrl
FROM dbo.ContentInitialize ci
INNER JOIN dbo.ContentCommonInfo cc ON ci.Id = cc.ContentId
LEFT JOIN AsreKhodroFront.dbo.SingleContent sc ON sc.ContentId = ci.Id
LEFT JOIN AsreKhodroFront.dbo.MainLastContents mlc ON mlc.ContentId = ci.Id
WHERE ci.ContentTypeId = 16
  AND ci.StatusId IN (1, 3)
FOR JSON PATH;
`;

const videosSample = fullExport
  ? []
  : runBackOnly(
      connectionProfile,
      sourceMode,
      "videos",
      videosBackBase.replace(/FOR JSON PATH;/, "ORDER BY cc.PublishTime DESC, ci.Id DESC\nFOR JSON PATH;")
    );

const videoExport = exportBatchedCollection({
  outputDir: output,
  collection: "videos",
  label: "videos",
  fullExport,
  resume,
  progress: exportProgress,
  sampleRows: videosSample,
  legacyFilename: "videos.json",
  fetchPage: (offset, size) =>
    fetchPagedBackOnly(
      connectionProfile,
      sourceMode,
      "videos",
      videosBackBase,
      "AsreKhodroBack",
      offset,
      size,
      "cc.PublishTime DESC, ci.Id DESC"
    ),
});

const videoCategoriesBackBase = `
SET NOCOUNT ON;
SELECT
  cc.ContentId AS contentId,
  cc.CategoryId AS categoryId,
  cc.IsMain AS isMain
FROM dbo.ContentCategories cc
INNER JOIN dbo.ContentInitialize ci ON ci.Id = cc.ContentId
WHERE ci.ContentTypeId = 16
  AND ci.StatusId IN (1, 3)
FOR JSON PATH;
`;

const videoCategoriesSample = fullExport
  ? []
  : runBackOnly(connectionProfile, sourceMode, "video-categories", videoCategoriesBackBase);

const videoCategoryExport = exportBatchedCollection({
  outputDir: output,
  collection: "video-categories",
  label: "video-categories",
  fullExport,
  resume,
  progress: exportProgress,
  sampleRows: videoCategoriesSample,
  legacyFilename: "video-categories.json",
  trackUnique: true,
  fetchPage: (offset, size) =>
    fetchPagedBackOnly(
      connectionProfile,
      sourceMode,
      "video-categories",
      videoCategoriesBackBase,
      "AsreKhodroBack",
      offset,
      size,
      "cc.ContentId, cc.CategoryId"
    ),
});

const reviewsBackBase = `
SET NOCOUNT ON;
SELECT ${fullExport ? "" : sqlTop(reviewLimit)}
  ci.Id AS contentId,
  ci.DomainId AS domainId,
  ci.ContentTypeId AS contentTypeId,
  ci.StatusId AS statusId,
  ci.Title AS title,
  cc.OverTitle AS overTitle,
  cc.UnderTitle AS underTitle,
  cc.ShortBody AS excerpt,
  cc.BodyText AS body,
  cc.Footer AS footer,
  cc.Author AS author,
  cc.PublishTime AS publishTime,
  cc.ContentTime AS contentTime,
  cc.ExpireTime AS expireTime,
  COALESCE(
    (
      SELECT TOP 1 fft.Url
      FROM AsreKhodroBack.dbo.ContentFiles bcf
      INNER JOIN AsreKhodroBack.dbo.FilesFiletypes fft ON fft.FileId = bcf.FileId
      LEFT JOIN AsreKhodroBack.dbo.ImageDimension idim ON idim.Id = fft.ImageDimensionId
      WHERE bcf.ContentId = ci.Id
        AND fft.Url IS NOT NULL
        AND LTRIM(RTRIM(fft.Url)) <> ''
        AND fft.Url NOT LIKE '%/Uploaded/Video/%'
      ORDER BY
        CASE WHEN sc.MainImageId IS NOT NULL AND bcf.FileId = sc.MainImageId THEN 0 ELSE 1 END,
        CASE WHEN bcf.IsMain = 1 THEN 0 ELSE 1 END,
        ISNULL(idim.Width, 0) DESC,
        bcf.Periority,
        fft.Id DESC
    ),
    (
      SELECT TOP 1 cf.URL
      FROM AsreKhodroFront.dbo.ContentFiles cf
      LEFT JOIN AsreKhodroBack.dbo.ImageDimension idim ON idim.Id = cf.ImageDimensionId
      WHERE cf.ContentId = ci.Id
        AND cf.URL IS NOT NULL
        AND LTRIM(RTRIM(cf.URL)) <> ''
        AND cf.URL NOT LIKE '%/Uploaded/Video/%'
      ORDER BY
        CASE WHEN sc.MainImageId IS NOT NULL AND cf.FileId = sc.MainImageId THEN 0 ELSE 1 END,
        CASE WHEN cf.IsMain = 1 THEN 0 ELSE 1 END,
        ISNULL(idim.Width, 0) DESC,
        cf.PeriorityInContent,
        cf.RowId DESC
    ),
    (
      SELECT TOP 1 fft.Url
      FROM AsreKhodroBack.dbo.FilesFiletypes fft
      LEFT JOIN AsreKhodroBack.dbo.ImageDimension idim ON idim.Id = fft.ImageDimensionId
      WHERE sc.MainImageId IS NOT NULL
        AND fft.FileId = sc.MainImageId
        AND fft.Url IS NOT NULL
        AND LTRIM(RTRIM(fft.Url)) <> ''
        AND fft.Url NOT LIKE '%/Uploaded/Video/%'
      ORDER BY ISNULL(idim.Width, 0) DESC, fft.Id DESC
    ),
    NULLIF(LTRIM(RTRIM(sc.ImageURL)), ''),
    NULLIF(LTRIM(RTRIM(mlc.ImageURL)), '')
  ) AS imageUrl
FROM dbo.ContentInitialize ci
INNER JOIN dbo.ContentCommonInfo cc ON ci.Id = cc.ContentId
LEFT JOIN AsreKhodroFront.dbo.SingleContent sc ON sc.ContentId = ci.Id
LEFT JOIN AsreKhodroFront.dbo.MainLastContents mlc ON mlc.ContentId = ci.Id
WHERE ci.ContentTypeId = 8
  AND ci.StatusId IN (1, 3)
FOR JSON PATH;
`;

const reviewsSample = fullExport
  ? []
  : runBackOnly(
      connectionProfile,
      sourceMode,
      "reviews",
      reviewsBackBase.replace(/FOR JSON PATH;/, "ORDER BY cc.PublishTime DESC, ci.Id DESC\nFOR JSON PATH;")
    );

const reviewExport = exportBatchedCollection({
  outputDir: output,
  collection: "reviews",
  label: "reviews",
  fullExport,
  resume,
  progress: exportProgress,
  sampleRows: reviewsSample,
  legacyFilename: "reviews.json",
  fetchPage: (offset, size) =>
    fetchPagedBackOnly(
      connectionProfile,
      sourceMode,
      "reviews",
      reviewsBackBase,
      "AsreKhodroBack",
      offset,
      size,
      "cc.PublishTime DESC, ci.Id DESC"
    ),
});

const reviewCategoriesBackBase = `
SET NOCOUNT ON;
SELECT
  cc.ContentId AS contentId,
  cc.CategoryId AS categoryId,
  cc.IsMain AS isMain
FROM dbo.ContentCategories cc
INNER JOIN dbo.ContentInitialize ci ON ci.Id = cc.ContentId
WHERE ci.ContentTypeId = 8
  AND ci.StatusId IN (1, 3)
FOR JSON PATH;
`;

const reviewCategoriesSample = fullExport
  ? []
  : runBackOnly(connectionProfile, sourceMode, "review-categories", reviewCategoriesBackBase);

const reviewCategoryExport = exportBatchedCollection({
  outputDir: output,
  collection: "review-categories",
  label: "review-categories",
  fullExport,
  resume,
  progress: exportProgress,
  sampleRows: reviewCategoriesSample,
  legacyFilename: "review-categories.json",
  fetchPage: (offset, size) =>
    fetchPagedBackOnly(
      connectionProfile,
      sourceMode,
      "review-categories",
      reviewCategoriesBackBase,
      "AsreKhodroBack",
      offset,
      size,
      "cc.ContentId, cc.CategoryId"
    ),
});

const magazinesBackBase = `
SET NOCOUNT ON;
SELECT ${fullExport ? "" : sqlTop(magazineLimit)}
  fi.Id AS fileId,
  fi.DomainId AS domainId,
  fi.StatusId AS statusId,
  fi.Title AS title,
  fi.Periority AS priority,
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
FOR JSON PATH;
`;

const magazinesSample = fullExport
  ? []
  : runBackOnly(
      connectionProfile,
      sourceMode,
      "magazines",
      magazinesBackBase.replace(/FOR JSON PATH;/, "ORDER BY fpi.CreateTime DESC, fi.Id DESC\nFOR JSON PATH;")
    );

const magazineExport = exportBatchedCollection({
  outputDir: output,
  collection: "magazines",
  label: "magazines",
  fullExport,
  resume,
  progress: exportProgress,
  sampleRows: magazinesSample,
  legacyFilename: "magazines.json",
  fetchPage: (offset, size) =>
    fetchPagedBackOnly(
      connectionProfile,
      sourceMode,
      "magazines",
      magazinesBackBase,
      "AsreKhodroBack",
      offset,
      size,
      "fpi.CreateTime DESC, fi.Id DESC"
    ),
});

const ads = adExport.chunked ? [] : adsSample;
const videos = videoExport.chunked ? [] : videosSample;
const videoCategories = videoCategoryExport.chunked ? [] : videoCategoriesSample;
const reviews = reviewExport.chunked ? [] : reviewsSample;
const reviewCategories = reviewCategoryExport.chunked ? [] : reviewCategoriesSample;
const magazines = magazineExport.chunked ? [] : magazinesSample;
const postCategories = postCategoryExport.chunked ? [] : postCategoriesSample;
const tags = tagExport.chunked ? [] : tagsSample;
const postRelations = postRelationExport.chunked ? [] : postRelationsSample;
const comments = commentExport.chunked ? [] : commentsSample;

const postsWithImageUrlCount = postsWithImageUrl;

const postCategoryCount = postCategoryExport.rows;
const tagCount = tagExport.rows;
const postRelationCount = postRelationExport.rows;
const commentCount = commentExport.rows;
const adCount = adExport.rows;
const videoCount = videoExport.rows;
const videoCategoryCount = videoCategoryExport.rows;
const reviewCount = reviewExport.rows;
const reviewCategoryCount = reviewCategoryExport.rows;
const magazineCount = magazineExport.rows;

const manifest = {
  exportedAt: new Date().toISOString(),
  format: fullExport ? "chunked" : "legacy",
  chunkSize: CHUNK_SIZE,
  connectionProfile: activeProfileName,
  sourceMode,
  limit,
  chunks: fullExport
    ? {
        posts: { files: postExport.postFiles, rows: postCount },
        "post-categories": {
          files: postCategoryExport.files,
          rows: postCategoryCount,
          uniqueContentIds: postCategoryExport.uniqueContentIds,
        },
        tags: {
          files: tagExport.files,
          rows: tagCount,
          uniqueContentIds: tagExport.uniqueContentIds,
        },
        "post-relations": {
          files: postRelationExport.files,
          rows: postRelationCount,
          uniqueContentIds: postRelationExport.uniqueContentIds,
        },
        comments: { files: commentExport.files, rows: commentCount },
        ads: { files: adExport.files, rows: adCount },
        videos: { files: videoExport.files, rows: videoCount },
        "video-categories": {
          files: videoCategoryExport.files,
          rows: videoCategoryCount,
          uniqueContentIds: videoCategoryExport.uniqueContentIds,
        },
        reviews: { files: reviewExport.files, rows: reviewCount },
        "review-categories": { files: reviewCategoryExport.files, rows: reviewCategoryCount },
        magazines: { files: magazineExport.files, rows: magazineCount },
      }
    : undefined,
  source: {
    mode:
      sourceMode === "auto"
        ? "Try AsreKhodroBack first; fall back to AsreKhodroFront on Msg 823"
        : sourceMode === "front"
          ? "AsreKhodroFront only (published content)"
          : "AsreKhodroBack only",
    posts:
      sourceMode === "front"
        ? "AsreKhodroFront.SingleContent"
        : "AsreKhodroBack (ContentInitialize + ContentCommonInfo) with Front fallback",
    categories: "AsreKhodroBack.Categories",
    postCategories: "AsreKhodroBack.ContentCategories",
    tags: "AsreKhodroBack.KeywordsContent",
    postRelations:
      "AsreKhodroFront.ContentsRelation (Back: ContentRelation) where IsActive = 1",
    frontSections:
      "AsreKhodroFront homepage caches (MainSlider, MainTicker, MainTopHits, Parsik, SpecialEvents, TopHits) as contentId references; full posts merged into posts.json",
    comments: "AsreKhodroComments",
    ads: "AsreKhodroBack.Advertisments (active) + FilesFiletypes / Front.Advertisements",
    videos: "AsreKhodroBack.ContentInitialize where ContentTypeId = 16 (Video)",
    videoCategories: "AsreKhodroBack.ContentCategories for video content",
    reviews: "AsreKhodroBack.ContentInitialize where ContentTypeId = 8 (Photo report / car reviews)",
    reviewCategories: "AsreKhodroBack.ContentCategories for exported reviews",
    magazines: `AsreKhodroBack.FileInitialize in category ${KIOSK_CATEGORY_ID} (دکه مطبوعات / Kiosk)`,
    imageUrl:
      "SingleContent.MainImageId → AsreKhodroFront.ContentFiles.RowId, then SingleContent.ImageURL / MainLastContents.ImageURL fallback",
  },
  counts: {
    categories: categories.length,
    posts: postCount,
    postsWithImageUrl: postsWithImageUrlCount,
    postCategories: postCategoryCount,
    postCategoryPosts: postCategoryExport.uniqueContentIds ?? postCategories.length,
    tags: tagCount,
    tagPosts: tagExport.uniqueContentIds ?? tags.length,
    postRelations: postRelationCount,
    postRelationPosts:
      postRelationExport.uniqueContentIds ??
      new Set( postRelations.map( ( row ) => row.parentContentId ) ).size,
    frontSections: Object.values(frontSectionsExport.exported).reduce(
      (sum, count) => sum + count,
      0
    ),
    frontSectionPosts: frontSectionsExport.contentIds.length,
    comments: commentCount,
    ads: adCount,
    adsWithImageUrl: fullExport
      ? null
      : ads.filter((a) => typeof a.imageUrl === "string" && a.imageUrl.trim() !== "").length,
    videos: videoCount,
    videosWithVideoUrl: fullExport
      ? null
      : videos.filter((v) => typeof v.videoUrl === "string" && v.videoUrl.trim() !== "").length,
    videosWithImageUrl: fullExport
      ? null
      : videos.filter((v) => typeof v.imageUrl === "string" && v.imageUrl.trim() !== "").length,
    videoCategories: videoCategoryCount,
    videoCategoryPosts: videoCategoryExport.uniqueContentIds ?? videoCategories.length,
    reviews: reviewCount,
    reviewsWithImageUrl: fullExport
      ? null
      : reviews.filter((r) => typeof r.imageUrl === "string" && r.imageUrl.trim() !== "").length,
    reviewCategories: reviewCategoryCount,
    magazines: magazineCount,
    magazinesWithImageUrl: fullExport
      ? null
      : magazines.filter((m) => typeof m.imageUrl === "string" && m.imageUrl.trim() !== "").length,
  },
  statusMap: {
    "0": "draft",
    "1": "publish",
    "3": "publish",
    "4": "skip",
    "5": "draft",
  },
};

writeJson(output, "manifest.json", manifest);
if (!postExport.chunked) {
  writeJson(output, "posts.json", posts);
}
writeJson(output, "menu-positions.json", menuPositions);

console.log("Export complete:");
console.log(`  categories:       ${categories.length}`);
console.log(`  posts:            ${postCount}${postExport.chunked ? ` (${postExport.postFiles} chunks)` : ""}`);
console.log(`  posts w/ image:   ${postsWithImageUrlCount} / ${postCount}`);
console.log(`  post-categories:  ${postCategoryCount}${postCategoryExport.chunked ? ` (${postCategoryExport.files} chunks)` : ""}`);
console.log(`  tags:             ${tagCount}${tagExport.chunked ? ` (${tagExport.files} chunks)` : ""}`);
console.log(`  post-relations:   ${postRelationCount}${postRelationExport.chunked ? ` (${postRelationExport.files} chunks)` : ""}`);
console.log(
  `  front-sections:   ${Object.values(frontSectionsExport.exported).reduce((sum, count) => sum + count, 0)} refs (${frontSectionsExport.contentIds.length} unique posts)`
);
console.log(`  videos:           ${videoCount}${videoExport.chunked ? ` (${videoExport.files} chunks)` : ""}`);
console.log(`  video-categories: ${videoCategoryCount}${videoCategoryExport.chunked ? ` (${videoCategoryExport.files} chunks)` : ""}`);
console.log(`  reviews:          ${reviewCount}${reviewExport.chunked ? ` (${reviewExport.files} chunks)` : ""}`);
console.log(`  review-categories:${reviewCategoryCount}${reviewCategoryExport.chunked ? ` (${reviewCategoryExport.files} chunks)` : ""}`);
console.log(`  magazines:        ${magazineCount}${magazineExport.chunked ? ` (${magazineExport.files} chunks)` : ""}`);
console.log(`  comments:         ${commentCount}${commentExport.chunked ? ` (${commentExport.files} chunks)` : ""}`);
console.log(`  ads:              ${adCount}${adExport.chunked ? ` (${adExport.files} chunks)` : ""}`);
console.log(`\nImport folder: ${output}`);
console.log("Next: WP Admin → Tools → AsreKhodro Import → Run Import");
