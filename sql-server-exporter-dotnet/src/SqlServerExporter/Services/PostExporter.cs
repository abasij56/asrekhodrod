using System.Text.Json.Nodes;
using SqlServerExporter.Cli;
using SqlServerExporter.Json;
using SqlServerExporter.Sql;

namespace SqlServerExporter.Services;

internal sealed record PostExportResult(
    List<JsonObject> Posts,
    bool Chunked,
    int PostCount,
    int PostFiles,
    int PostsWithImageUrl);

internal static class PostExporter
{
    public static PostExportResult Export(
        BackOrFrontRunner runner,
        SqlJsonQueryService sql,
        string sourceMode,
        int limit,
        PostExportSettings settings,
        string outputDir,
        bool resume,
        JsonObject progress,
        ExportProgressService progressService,
        bool tryContentFileImages)
    {
        if (settings.IsWindowed)
        {
            return ExportWindowed(
                runner,
                sql,
                sourceMode,
                settings,
                outputDir,
                resume,
                progress,
                progressService,
                tryContentFileImages);
        }

        if (limit > 0)
        {
            var posts = runner.RunBackOrFront(
                "posts",
                SqlFragments.PostsBackQuery(SqlFragments.SqlTop(limit), string.Empty),
                SqlFragments.PostsFrontQuery(SqlFragments.SqlTop(limit), string.Empty));
            FinalizeExportedPostRows(sql, sourceMode, runner, posts, tryContentFileImages, recordOffset: 0);
            return new PostExportResult(
                posts,
                false,
                posts.Count,
                1,
                posts.Count(row => !string.IsNullOrWhiteSpace(JsonRowHelper.GetString(row, "imageUrl"))));
        }

        return ExportLegacyChunked(
            runner,
            sql,
            sourceMode,
            outputDir,
            resume,
            progress,
            progressService,
            settings.SkipBatch,
            tryContentFileImages);
    }

    private static void FinalizeExportedPostRows(
        SqlJsonQueryService sql,
        string sourceMode,
        BackOrFrontRunner runner,
        List<JsonObject> rows,
        bool tryContentFileImages,
        int? recordOffset = null)
    {
        if (sourceMode == "front" || runner.BackUnavailable)
        {
            PostMainImageResolver.Apply(sql, rows, tryContentFileImages, useFrontImageRules: true, recordOffset);
            return;
        }

        PostMainImageResolver.Apply(sql, rows, tryContentFiles: false, useFrontImageRules: false, recordOffset);
        JsonRowHelper.EnrichPostImageUrls(rows);
    }

    private static PostExportResult ExportWindowed(
        BackOrFrontRunner runner,
        SqlJsonQueryService sql,
        string sourceMode,
        PostExportSettings settings,
        string outputDir,
        bool resume,
        JsonObject progress,
        ExportProgressService progressService,
        bool tryContentFileImages)
    {
        var skipBatch = Math.Max(1, settings.SkipBatch);
        var resumed = ResumeWindowedState(outputDir, resume, progress, settings.Start, settings.End);

        if (resumed.Complete)
        {
            Console.WriteLine($"  → posts (resume: already complete, {resumed.Total} rows, {resumed.Files} files)");
            ExportStageLog.Phase("posts already complete (resume)");
            return new PostExportResult([], true, resumed.Total, resumed.Files, resumed.PostsWithImageUrl);
        }

        var startOffset = resumed.Start;
        var endOffset = resumed.End;
        if (endOffset is int end && end <= startOffset)
        {
            throw new InvalidOperationException($"--end ({end}) must be greater than start ({startOffset}).");
        }

        var endLabel = endOffset is int endValue ? $", end={endValue}" : string.Empty;
        var continueForced = endOffset is not null ? " (continue forced 0 by --end)" : string.Empty;
        Console.WriteLine(
            $"  → posts (windowed: start={startOffset}{endLabel}, window={settings.Window}, file-chunk={settings.FileChunk}, skip-batch={skipBatch}, continue={(settings.Continue ? 1 : 0)}{continueForced} → posts/)");

        var windowIndex = resumed.WindowIndex;
        var positionInWindow = resumed.PositionInWindow;
        var files = resumed.Files;
        var total = resumed.Total;
        var postsWithImageUrl = resumed.PostsWithImageUrl;
        var skippedTotal = resumed.SkippedPosts;

        while (true)
        {
            var stageBase = startOffset + (windowIndex * settings.Window);
            var consumedInWindow = positionInWindow;
            var windowEndOfData = false;
            var stageLimit = ResolveStageLimit(settings, stageBase, endOffset);

            Console.WriteLine(
                $"  → stage {windowIndex + 1} (record offset {stageBase + consumedInWindow}, read up to {stageLimit} rows)");

            while (consumedInWindow < stageLimit)
            {
                var batchSize = Math.Min(settings.FileChunk, stageLimit - consumedInWindow);
                var fetchOffset = stageBase + consumedInWindow;

                List<JsonObject> rows;
                int skippedInBatch;

                ExportStageLog.Detail(
                    $"query SingleContent offset={fetchOffset} count={batchSize} (stage {windowIndex + 1}, progress {consumedInWindow}/{stageLimit})...");
                var result = FetchPostsPageSafely(
                    sql,
                    runner,
                    sourceMode,
                    fetchOffset,
                    batchSize,
                    skipBatch);
                rows = result.Rows;
                skippedInBatch = result.Skipped;
                ExportStageLog.Detail($"fetched {rows.Count} post(s) at offset {fetchOffset}");

                if (rows.Count == 0 && skippedInBatch == 0)
                {
                    windowEndOfData = true;
                    break;
                }

                if (rows.Count == 0 && skippedInBatch > 0)
                {
                    consumedInWindow += skippedInBatch;
                    skippedTotal += skippedInBatch;
                    SaveWindowedProgress(
                        progressService,
                        false,
                        startOffset,
                        endOffset,
                        windowIndex,
                        consumedInWindow,
                        files,
                        total,
                        skippedTotal,
                        postsWithImageUrl);
                    Console.WriteLine(
                        $"    posts skipped {skippedInBatch} unreadable row(s) at offset {fetchOffset} (stage {windowIndex + 1})");
                    continue;
                }

                foreach (var row in rows)
                {
                    row.Remove("publishSort");
                }

                if (tryContentFileImages)
                {
                    ExportStageLog.Detail($"resolve images for {rows.Count} post(s)...");
                }

                FinalizeExportedPostRows(sql, sourceMode, runner, rows, tryContentFileImages, fetchOffset);
                postsWithImageUrl += rows.Count(row => !string.IsNullOrWhiteSpace(JsonRowHelper.GetString(row, "imageUrl")));

                files += 1;
                ExportStageLog.Detail($"write posts chunk {files} ({rows.Count} rows)...");
                ChunkFileService.WriteChunkFile(outputDir, "posts", files, rows);
                total += rows.Count;
                consumedInWindow += rows.Count + skippedInBatch;
                skippedTotal += skippedInBatch;

                SaveWindowedProgress(
                    progressService,
                    false,
                    startOffset,
                    endOffset,
                    windowIndex,
                    consumedInWindow,
                    files,
                    total,
                    skippedTotal,
                    postsWithImageUrl);

                Console.WriteLine(
                    $"    posts chunk {files}: +{rows.Count} (total {total}, stage progress {consumedInWindow}/{stageLimit}){(skippedInBatch > 0 ? $", skipped {skippedInBatch}" : string.Empty)}");
            }

            positionInWindow = 0;

            if (!settings.Continue)
            {
                SaveWindowedProgress(
                    progressService,
                    true,
                    startOffset,
                    endOffset,
                    windowIndex,
                    consumedInWindow,
                    files,
                    total,
                    skippedTotal,
                    postsWithImageUrl);
                break;
            }

            if (windowEndOfData && consumedInWindow == 0)
            {
                SaveWindowedProgress(
                    progressService,
                    true,
                    startOffset,
                    endOffset,
                    windowIndex,
                    0,
                    files,
                    total,
                    skippedTotal,
                    postsWithImageUrl);
                break;
            }

            if (windowEndOfData)
            {
                SaveWindowedProgress(
                    progressService,
                    true,
                    startOffset,
                    endOffset,
                    windowIndex,
                    consumedInWindow,
                    files,
                    total,
                    skippedTotal,
                    postsWithImageUrl);
                break;
            }

            windowIndex += 1;
        }

        return new PostExportResult([], files > 0, total, files, postsWithImageUrl);
    }

    private static PostExportResult ExportLegacyChunked(
        BackOrFrontRunner runner,
        SqlJsonQueryService sql,
        string sourceMode,
        string outputDir,
        bool resume,
        JsonObject progress,
        ExportProgressService progressService,
        int skipBatch,
        bool tryContentFileImages)
    {
        var resumed = ResumePostsState(outputDir, resume, progress);
        if (resumed.Complete)
        {
            Console.WriteLine($"  → posts (resume: already complete, {resumed.Total} rows)");
            return new PostExportResult([], resumed.Files > 0, resumed.Total, resumed.Files, resumed.PostsWithImageUrl);
        }

        var useFrontOnly = sourceMode == "front" || runner.BackUnavailable;
        Console.WriteLine(
            $"  → posts (batched {ExportConstants.ChunkSize}/chunk → posts/, {(useFrontOnly ? "AsreKhodroFront" : "AsreKhodroBack → Front fallback")})");

        var total = resumed.Total;
        var postsWithImageUrl = resumed.PostsWithImageUrl;
        var offset = resumed.Offset;
        var files = resumed.Files;
        var skippedPosts = resumed.SkippedPosts;
        var effectiveSkipBatch = Math.Max(1, skipBatch);

        while (true)
        {
            List<JsonObject> rows;
            var skippedInBatch = 0;

            ExportStageLog.Detail($"query SingleContent offset={offset} count={ExportConstants.ChunkSize}...");
            try
            {
                var result = FetchPostsPageSafely(
                    sql,
                    runner,
                    sourceMode,
                    offset,
                    ExportConstants.ChunkSize,
                    effectiveSkipBatch);
                rows = result.Rows;
                skippedInBatch = result.Skipped;
            }
            catch (Exception ex)
            {
                throw SqlErrorHelper.EnrichSqlcmdError(ex);
            }

            ExportStageLog.Detail($"fetched {rows.Count} post(s) at offset {offset}");

            if (rows.Count == 0)
            {
                if (skippedInBatch > 0)
                {
                    offset += ExportConstants.ChunkSize;
                    skippedPosts += skippedInBatch;
                    SavePostsProgress(progressService, false, files, total, offset, skippedPosts, postsWithImageUrl);
                    Console.WriteLine(
                        $"    posts skipped {skippedInBatch} unreadable row(s) at offset {offset - ExportConstants.ChunkSize} (total {total})");
                    continue;
                }

                SavePostsProgress(progressService, true, files, total, offset, skippedPosts, postsWithImageUrl);
                break;
            }

            foreach (var row in rows)
            {
                row.Remove("publishSort");
            }

            if (tryContentFileImages)
            {
                ExportStageLog.Detail($"resolve images for {rows.Count} post(s)...");
            }

            FinalizeExportedPostRows(sql, sourceMode, runner, rows, tryContentFileImages, offset);
            postsWithImageUrl += rows.Count(row => !string.IsNullOrWhiteSpace(JsonRowHelper.GetString(row, "imageUrl")));

            files += 1;
            ExportStageLog.Detail($"write posts chunk {files} ({rows.Count} rows)...");
            ChunkFileService.WriteChunkFile(outputDir, "posts", files, rows);
            total += rows.Count;
            skippedPosts += skippedInBatch;
            SavePostsProgress(progressService, false, files, total, offset + ExportConstants.ChunkSize, skippedPosts, postsWithImageUrl);
            Console.WriteLine(
                $"    posts chunk {files}: +{rows.Count} (total {total}){(skippedInBatch > 0 ? $", skipped {skippedInBatch}" : string.Empty)}");
            offset += ExportConstants.ChunkSize;

            if (rows.Count + skippedInBatch < ExportConstants.ChunkSize)
            {
                SavePostsProgress(progressService, true, files, total, offset, skippedPosts, postsWithImageUrl);
                break;
            }
        }

        return new PostExportResult([], files > 0, total, files, postsWithImageUrl);
    }

    public static List<int> CollectContentIdsFromChunks(string outputDir)
    {
        var ids = new HashSet<int>();
        foreach (var file in ChunkFileService.ListChunkFiles(outputDir, "posts"))
        {
            foreach (var row in JsonRowHelper.ReadRowsFile(file))
            {
                var id = JsonRowHelper.GetInt(row, "contentId");
                if (id is > 0)
                {
                    ids.Add(id.Value);
                }
            }
        }

        return ids.OrderBy(id => id).ToList();
    }

    public static List<JsonObject> MergeRequiredPosts(
        List<JsonObject> posts,
        IReadOnlyList<int> requiredContentIds,
        BackOrFrontRunner runner)
    {
        var existing = posts
            .Select(row => JsonRowHelper.GetInt(row, "contentId") ?? 0)
            .Where(id => id > 0)
            .ToHashSet();

        var missing = JsonRowHelper.NormalizeContentIds(requiredContentIds.Select(id => (int?)id))
            .Where(id => !existing.Contains(id))
            .ToList();

        if (missing.Count == 0)
        {
            return posts;
        }

        var idList = string.Join(",", missing);
        var extra = runner.RunBackOrFront(
            "posts-by-content-id",
            SqlFragments.PostsByContentIdsBackQuery(idList),
            SqlFragments.PostsByContentIdsFrontQuery(idList));
        JsonRowHelper.EnrichPostImageUrls(extra);
        Console.WriteLine($"  → merged {extra.Count} front-section post(s) into posts.json");
        return posts.Concat(extra).ToList();
    }

    private static void SavePostsProgress(
        ExportProgressService progressService,
        bool complete,
        int files,
        int total,
        int offset,
        int skippedPosts,
        int postsWithImageUrl)
    {
        progressService.Save("posts", new JsonObject
        {
            ["mode"] = "legacy",
            ["complete"] = complete,
            ["files"] = files,
            ["rows"] = total,
            ["offset"] = offset,
            ["skippedPosts"] = skippedPosts,
            ["postsWithImageUrl"] = postsWithImageUrl,
        });
    }

    private static int ResolveStageLimit(PostExportSettings settings, int stageBase, int? endOffset)
    {
        if (endOffset is int end)
        {
            return Math.Max(0, end - stageBase);
        }

        return settings.Window;
    }

    private static void SaveWindowedProgress(
        ExportProgressService progressService,
        bool complete,
        int start,
        int? end,
        int windowIndex,
        int positionInWindow,
        int files,
        int total,
        int skippedPosts,
        int postsWithImageUrl)
    {
        var node = new JsonObject
        {
            ["mode"] = "windowed",
            ["complete"] = complete,
            ["start"] = start,
            ["windowIndex"] = windowIndex,
            ["positionInWindow"] = positionInWindow,
            ["files"] = files,
            ["rows"] = total,
            ["skippedPosts"] = skippedPosts,
            ["postsWithImageUrl"] = postsWithImageUrl,
        };

        if (end is not null)
        {
            node["end"] = end.Value;
        }

        progressService.Save("posts", node);
    }

    private sealed record PostsResumeState(
        bool Complete,
        int Files,
        int Total,
        int PostsWithImageUrl,
        int Offset,
        int SkippedPosts);

    private sealed record WindowedResumeState(
        bool Complete,
        int Start,
        int? End,
        int WindowIndex,
        int PositionInWindow,
        int Files,
        int Total,
        int PostsWithImageUrl,
        int SkippedPosts);

    private static WindowedResumeState ResumeWindowedState(
        string outputDir,
        bool resume,
        JsonObject progress,
        int cliStart,
        int? cliEnd)
    {
        if (!resume)
        {
            return new WindowedResumeState(false, cliStart, cliEnd, 0, 0, 0, 0, 0, 0);
        }

        if (progress["posts"] is JsonObject saved)
        {
            var mode = saved["mode"]?.GetValue<string>();
            var savedStart = saved["start"]?.GetValue<int>() ?? cliStart;
            var savedEnd = saved.TryGetPropertyValue("end", out var endNode) && endNode is not null
                ? endNode.GetValue<int>()
                : cliEnd;
            if (mode == "windowed" && (saved["complete"]?.GetValue<bool>() ?? false))
            {
                return new WindowedResumeState(
                    true,
                    savedStart,
                    savedEnd,
                    saved["windowIndex"]?.GetValue<int>() ?? 0,
                    saved["positionInWindow"]?.GetValue<int>() ?? 0,
                    saved["files"]?.GetValue<int>() ?? 0,
                    saved["rows"]?.GetValue<int>() ?? 0,
                    saved["postsWithImageUrl"]?.GetValue<int>() ?? 0,
                    saved["skippedPosts"]?.GetValue<int>() ?? 0);
            }

            if (mode == "windowed")
            {
                var files = saved["files"]?.GetValue<int>() ?? ChunkFileService.ListChunkFiles(outputDir, "posts").Count;
                var total = saved["rows"]?.GetValue<int>() ?? 0;
                if (total == 0 && files > 0)
                {
                    total = JsonRowHelper.CountRowsInFiles(ChunkFileService.ListChunkFiles(outputDir, "posts"));
                }

                var endLabel = savedEnd is int endValue ? $", end={endValue}" : string.Empty;
                Console.WriteLine(
                    $"  → Resuming windowed posts at start={savedStart}{endLabel}, stage {(saved["windowIndex"]?.GetValue<int>() ?? 0) + 1} ({total} rows on disk)");
                return new WindowedResumeState(
                    false,
                    savedStart,
                    savedEnd,
                    saved["windowIndex"]?.GetValue<int>() ?? 0,
                    saved["positionInWindow"]?.GetValue<int>() ?? 0,
                    files,
                    total,
                    saved["postsWithImageUrl"]?.GetValue<int>() ?? 0,
                    saved["skippedPosts"]?.GetValue<int>() ?? 0);
            }
        }

        var chunkFiles = ChunkFileService.ListChunkFiles(outputDir, "posts");
        if (chunkFiles.Count == 0)
        {
            return new WindowedResumeState(false, cliStart, cliEnd, 0, 0, 0, 0, 0, 0);
        }

        var rowTotal = JsonRowHelper.CountRowsInFiles(chunkFiles);
        Console.WriteLine($"  → Resuming posts from chunk {chunkFiles.Count + 1} ({rowTotal} rows on disk, legacy→windowed)");
        return new WindowedResumeState(false, cliStart, cliEnd, 0, 0, chunkFiles.Count, rowTotal, 0, 0);
    }

    private static PostsResumeState ResumePostsState(string outputDir, bool resume, JsonObject progress)
    {
        if (!resume)
        {
            return new PostsResumeState(false, 0, 0, 0, 0, 0);
        }

        if (progress["posts"] is JsonObject saved && saved["mode"]?.GetValue<string>() == "windowed")
        {
            return new PostsResumeState(false, 0, 0, 0, 0, 0);
        }

        if (progress["posts"] is JsonObject legacySaved && (legacySaved["complete"]?.GetValue<bool>() ?? false))
        {
            return new PostsResumeState(
                true,
                legacySaved["files"]?.GetValue<int>() ?? 0,
                legacySaved["rows"]?.GetValue<int>() ?? 0,
                legacySaved["postsWithImageUrl"]?.GetValue<int>() ?? 0,
                legacySaved["offset"]?.GetValue<int>() ?? legacySaved["rows"]?.GetValue<int>() ?? 0,
                legacySaved["skippedPosts"]?.GetValue<int>() ?? 0);
        }

        var chunkFiles = ChunkFileService.ListChunkFiles(outputDir, "posts");
        if (chunkFiles.Count == 0)
        {
            return new PostsResumeState(false, 0, 0, 0, 0, 0);
        }

        var total = 0;
        var postsWithImageUrl = 0;
        foreach (var file in chunkFiles)
        {
            var rows = JsonRowHelper.ReadRowsFile(file);
            total += rows.Count;
            postsWithImageUrl += rows.Count(row => !string.IsNullOrWhiteSpace(JsonRowHelper.GetString(row, "imageUrl")));
        }

        Console.WriteLine($"  → Resuming posts from chunk {chunkFiles.Count + 1} ({total} rows already on disk)");
        return new PostsResumeState(
            false,
            chunkFiles.Count,
            total,
            postsWithImageUrl,
            progress["posts"]?["offset"]?.GetValue<int>() ?? total,
            progress["posts"]?["skippedPosts"]?.GetValue<int>() ?? 0);
    }

    private sealed record SafePageResult(List<JsonObject> Rows, int Skipped);

    private static SafePageResult FetchPostsPageSafely(
        SqlJsonQueryService sql,
        BackOrFrontRunner runner,
        string sourceMode,
        int offset,
        int size,
        int skipBatch)
    {
        try
        {
            if (sourceMode == "front" || runner.BackUnavailable)
            {
                return new SafePageResult(
                    sql.RunJsonQuery(
                        ExportConstants.DatabaseFront,
                        SqlFragments.PostsFrontQuery(string.Empty, SqlFragments.SqlPage(offset, size))),
                    0);
            }

            try
            {
                return new SafePageResult(
                    sql.RunJsonQuery(
                        ExportConstants.DatabaseBack,
                        SqlFragments.PostsBackQuery(string.Empty, SqlFragments.SqlPage(offset, size))),
                    0);
            }
            catch (Exception ex) when (sourceMode == "auto" && SqlErrorHelper.IsSqlCorruptionError(ex))
            {
                runner.MarkBackUnavailable("posts");
                return new SafePageResult(
                    sql.RunJsonQuery(
                        ExportConstants.DatabaseFront,
                        SqlFragments.PostsFrontQuery(string.Empty, SqlFragments.SqlPage(offset, size))),
                    0);
            }
        }
        catch (Exception ex) when (SqlErrorHelper.IsSqlCorruptionError(ex))
        {
            if (size <= skipBatch)
            {
                Console.WriteLine(
                    $"    ! skipped {skipBatch} unreadable post(s) at SQL offset {offset} (Msg 605/823/824)");
                return new SafePageResult([], skipBatch);
            }

            var leftSize = size / 2;
            var rightSize = size - leftSize;
            Console.WriteLine(
                $"    ! post batch at SQL offset {offset} failed (Msg 605/823/824); retrying as {leftSize}+{rightSize}");

            var left = FetchPostsPageSafely(sql, runner, sourceMode, offset, leftSize, skipBatch);
            var right = FetchPostsPageSafely(sql, runner, sourceMode, offset + leftSize, rightSize, skipBatch);
            return new SafePageResult(left.Rows.Concat(right.Rows).ToList(), left.Skipped + right.Skipped);
        }
    }
}
