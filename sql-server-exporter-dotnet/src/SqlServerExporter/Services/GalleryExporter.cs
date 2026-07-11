using System.Text.Json.Nodes;
using SqlServerExporter.Cli;
using SqlServerExporter.Json;
using SqlServerExporter.Sql;

namespace SqlServerExporter.Services;

internal sealed record GalleryExportResult(int Files, int Rows);

/// <summary>
/// Exports per-post image galleries from AsreKhodroFront.ContentFiles (--gallery-only).
/// Posts are walked by record offset over SingleContent ordered by PublishTime DESC
/// (newest first), the same universe/ordering as the posts export. Windowing mirrors the
/// posts export: read <c>--file-chunk</c> posts per JSON file, from record <c>--start</c>
/// to <c>--end</c> (or a single <c>--window</c> when no end is given, unless <c>--continue</c>).
/// Each output record is { contentId, images: [url, ...] } with large-size gallery pictures
/// in their in-content order, excluding the main/featured image. Contents with only one
/// distinct image (same FileId across dimensions) are skipped — a gallery needs 2+ images.
/// Because one post = one source record, a post's images never split across two chunks.
/// </summary>
internal static class GalleryExporter
{
    private const string Collection = "gallery";
    private const int DefaultPageSize = 2000;

    public static GalleryExportResult Export(
        SqlJsonQueryService sql,
        ExportOptions options,
        JsonObject progress,
        ExportProgressService progressService)
    {
        var outputDir = options.Output;
        var perChunk = options.FileChunk > 0 ? options.FileChunk : DefaultPageSize;
        var stopOffset = ResolveStopOffset(options);

        var resumed = ResumeState(options.Resume, progress, options.Start);
        if (resumed.Complete)
        {
            Console.WriteLine($"  → {Collection} (resume: already complete, {resumed.Rows} posts, {resumed.Files} files)");
            return new GalleryExportResult(resumed.Files, resumed.Rows);
        }

        var startLabel = stopOffset == int.MaxValue ? "end of data" : stopOffset.ToString();
        Console.WriteLine(
            $"  → {Collection} (AsreKhodroFront, records {resumed.Offset}..{startLabel}, {perChunk} posts/file, date DESC → {Collection}/)");

        var files = resumed.Files;
        var rows = resumed.Rows;
        var offset = resumed.Offset;

        while (offset < stopOffset)
        {
            var size = Math.Min(perChunk, stopOffset - offset);
            var contentIds = FetchContentIdPage(sql, offset, size);

            if (contentIds.Count == 0)
            {
                SaveProgress(progressService, true, files, rows, offset);
                break;
            }

            var galleryRows = FetchGalleryRowsSafely(sql, contentIds);
            var grouped = GroupByContent(contentIds, galleryRows);

            if (grouped.Count > 0)
            {
                files += 1;
                ChunkFileService.WriteChunkFile(outputDir, Collection, files, grouped);
                rows += grouped.Count;
                Console.WriteLine(
                    $"    {Collection} chunk {files}: +{grouped.Count} posts (total {rows}, records {offset}..{offset + contentIds.Count})");
            }
            else
            {
                Console.WriteLine(
                    $"    {Collection} records {offset}..{offset + contentIds.Count}: no gallery images");
            }

            offset += contentIds.Count;
            var endOfData = contentIds.Count < size;
            SaveProgress(progressService, endOfData, files, rows, offset);

            if (endOfData)
            {
                break;
            }
        }

        if (offset >= stopOffset && stopOffset != int.MaxValue)
        {
            SaveProgress(progressService, true, files, rows, offset);
        }

        return new GalleryExportResult(files, rows);
    }

    private static int ResolveStopOffset(ExportOptions options)
    {
        if (options.End is int end)
        {
            return Math.Max(options.Start, end);
        }

        if (options.Window > 0 && options.FileChunk > 0 && !options.Continue)
        {
            return options.Start + options.Window;
        }

        return int.MaxValue;
    }

    private static List<int> FetchContentIdPage(SqlJsonQueryService sql, int offset, int size)
    {
        var rows = sql.RunJsonQuery(
            ExportConstants.DatabaseFront,
            WordPressExportQueries.GalleryContentIdPageQuery(offset, size));

        // Preserve the SQL order (PublishTime DESC) — do NOT re-sort.
        return rows
            .Select(row => JsonRowHelper.GetInt(row, "contentId") ?? 0)
            .Where(id => id > 0)
            .ToList();
    }

    private static List<JsonObject> FetchGalleryRowsSafely(SqlJsonQueryService sql, List<int> contentIds)
    {
        if (contentIds.Count == 0)
        {
            return [];
        }

        try
        {
            return sql.RunJsonQuery(
                ExportConstants.DatabaseFront,
                WordPressExportQueries.GalleryRowsForContentIdsQuery(string.Join(",", contentIds)));
        }
        catch (Exception ex) when (SqlErrorHelper.IsSqlCorruptionError(ex))
        {
            if (contentIds.Count <= ExportConstants.MinPostBatchSize)
            {
                Console.WriteLine(
                    $"    ! skipped unreadable gallery for contentId {contentIds[0]} (Msg 605/823/824)");
                return [];
            }

            var mid = contentIds.Count / 2;
            Console.WriteLine(
                $"    ! gallery batch of {contentIds.Count} content(s) failed (Msg 605/823/824); retrying as {mid}+{contentIds.Count - mid}");

            var left = FetchGalleryRowsSafely(sql, contentIds.Take(mid).ToList());
            var right = FetchGalleryRowsSafely(sql, contentIds.Skip(mid).ToList());
            left.AddRange(right);
            return left;
        }
    }

    /// <summary>
    /// Groups flat (contentId, fileId, url) rows into { contentId, images } records. Post order
    /// follows <paramref name="orderedContentIds"/> (date DESC of the page); within a post,
    /// image order follows the SQL ordering (PeriorityInContent, RowId). One URL per distinct
    /// FileId; contents with fewer than two distinct images are omitted (not a gallery).
    /// </summary>
    private static List<JsonObject> GroupByContent(List<int> orderedContentIds, List<JsonObject> galleryRows)
    {
        var urlsByFileId = new Dictionary<int, Dictionary<int, string>>();
        var fileIdOrder = new Dictionary<int, List<int>>();

        foreach (var row in galleryRows)
        {
            var contentId = JsonRowHelper.GetInt(row, "contentId") ?? 0;
            if (contentId <= 0)
            {
                continue;
            }

            var fileId = JsonRowHelper.GetInt(row, "fileId") ?? 0;
            var url = JsonRowHelper.GetString(row, "url")?.Trim();
            if (fileId <= 0 || string.IsNullOrEmpty(url))
            {
                continue;
            }

            if (!urlsByFileId.TryGetValue(contentId, out var byFileId))
            {
                byFileId = new Dictionary<int, string>();
                urlsByFileId[contentId] = byFileId;
                fileIdOrder[contentId] = [];
            }

            if (byFileId.ContainsKey(fileId))
            {
                continue;
            }

            byFileId[fileId] = url;
            fileIdOrder[contentId].Add(fileId);
        }

        var records = new List<JsonObject>();
        foreach (var contentId in orderedContentIds)
        {
            if (!fileIdOrder.TryGetValue(contentId, out var orderedFileIds) || orderedFileIds.Count <= 1)
            {
                continue;
            }

            var byFileId = urlsByFileId[contentId];
            var array = new JsonArray();
            foreach (var fileId in orderedFileIds)
            {
                array.Add(byFileId[fileId]);
            }

            records.Add(new JsonObject
            {
                ["contentId"] = contentId,
                ["images"] = array,
            });
        }

        return records;
    }

    private static void SaveProgress(
        ExportProgressService progressService,
        bool complete,
        int files,
        int rows,
        int offset)
    {
        progressService.Save(Collection, new JsonObject
        {
            ["complete"] = complete,
            ["files"] = files,
            ["rows"] = rows,
            ["offset"] = offset,
            ["uniqueContentIds"] = rows,
        });
    }

    private sealed record GalleryResumeState(bool Complete, int Files, int Rows, int Offset);

    private static GalleryResumeState ResumeState(bool resume, JsonObject progress, int startOffset)
    {
        if (!resume || progress[Collection] is not JsonObject saved)
        {
            return new GalleryResumeState(false, 0, 0, startOffset);
        }

        if (saved["complete"]?.GetValue<bool>() ?? false)
        {
            return new GalleryResumeState(
                true,
                saved["files"]?.GetValue<int>() ?? 0,
                saved["rows"]?.GetValue<int>() ?? 0,
                saved["offset"]?.GetValue<int>() ?? startOffset);
        }

        var offset = Math.Max(saved["offset"]?.GetValue<int>() ?? startOffset, startOffset);
        var files = saved["files"]?.GetValue<int>() ?? 0;
        var rows = saved["rows"]?.GetValue<int>() ?? 0;
        Console.WriteLine($"  → Resuming {Collection} at record {offset} ({rows} posts on disk, {files} files)");
        return new GalleryResumeState(false, files, rows, offset);
    }
}
