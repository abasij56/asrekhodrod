using System.Text.Json.Nodes;
using SqlServerExporter.Json;

namespace SqlServerExporter.Services;

internal sealed record CollectionExportResult(
    int Files,
    int Rows,
    int? UniqueContentIds,
    bool Chunked);

internal static class BatchedCollectionExporter
{
    public static CollectionExportResult Export(
        string outputDir,
        string collection,
        string label,
        bool fullExport,
        IReadOnlyList<JsonObject> sampleRows,
        string legacyFilename,
        Func<int, int, List<JsonObject>> fetchPage,
        bool resume,
        JsonObject progress,
        ExportProgressService progressService,
        bool trackUnique = false,
        string trackUniqueField = "contentId")
    {
        if (!fullExport)
        {
            JsonRowHelper.WriteRowsFile(outputDir, legacyFilename, sampleRows);
            var uniqueIds = trackUnique ? TrackUnique(sampleRows, trackUniqueField) : null;
            return new CollectionExportResult(1, sampleRows.Count, uniqueIds?.Count, false);
        }

        var resumed = ResumeOffsetCollection(outputDir, collection, resume, progress);
        if (resumed.Complete)
        {
            Console.WriteLine($"  → {label} (resume: already complete, {resumed.Total} rows)");
            return new CollectionExportResult(resumed.FileIndex, resumed.Total, resumed.UniqueContentIds, resumed.FileIndex > 0);
        }

        Console.WriteLine($"  → {label} (batched {ExportConstants.ChunkSize}/chunk → {collection}/)");
        var index = resumed.FileIndex;
        var total = resumed.Total;
        var offset = resumed.Offset;
        var skippedRows = resumed.SkippedRows;
        HashSet<int>? uniqueIdsSet = trackUnique ? new HashSet<int>() : null;

        if (trackUnique && resumed.FileIndex > 0)
        {
            foreach (var file in ChunkFileService.ListChunkFiles(outputDir, collection))
            {
                TrackUnique(JsonRowHelper.ReadRowsFile(file), trackUniqueField, uniqueIdsSet!);
            }
        }

        while (true)
        {
            var result = FetchCollectionPageSafely(fetchPage, label, offset, ExportConstants.ChunkSize);
            var rows = result.Rows;
            var skippedInBatch = result.Skipped;

            if (rows.Count == 0)
            {
                if (skippedInBatch > 0)
                {
                    offset += ExportConstants.ChunkSize;
                    skippedRows += skippedInBatch;
                    SaveProgress(progressService, outputDir, collection, false, index, total, offset, skippedRows, uniqueIdsSet);
                    Console.WriteLine(
                        $"    {collection} skipped {skippedInBatch} unreadable row(s) at offset {offset - ExportConstants.ChunkSize} (total {total})");
                    continue;
                }

                SaveProgress(progressService, outputDir, collection, true, index, total, offset, skippedRows, uniqueIdsSet);
                break;
            }

            index += 1;
            ChunkFileService.WriteChunkFile(outputDir, collection, index, rows);
            total += rows.Count;
            skippedRows += skippedInBatch;
            TrackUnique(rows, trackUniqueField, uniqueIdsSet);
            SaveProgress(progressService, outputDir, collection, false, index, total, offset + ExportConstants.ChunkSize, skippedRows, uniqueIdsSet);
            Console.WriteLine(
                $"    {collection} chunk {index}: +{rows.Count} (total {total}){(skippedInBatch > 0 ? $", skipped {skippedInBatch}" : string.Empty)}");
            offset += ExportConstants.ChunkSize;

            if (rows.Count + skippedInBatch < ExportConstants.ChunkSize)
            {
                SaveProgress(progressService, outputDir, collection, true, index, total, offset, skippedRows, uniqueIdsSet);
                break;
            }
        }

        return new CollectionExportResult(index, total, uniqueIdsSet?.Count, index > 0);
    }

    private static void SaveProgress(
        ExportProgressService progressService,
        string outputDir,
        string collection,
        bool complete,
        int files,
        int rows,
        int offset,
        int skippedRows,
        HashSet<int>? uniqueIds)
    {
        progressService.Save(collection, new JsonObject
        {
            ["complete"] = complete,
            ["files"] = files,
            ["rows"] = rows,
            ["offset"] = offset,
            ["skippedRows"] = skippedRows,
            ["uniqueContentIds"] = uniqueIds?.Count,
        });
    }

    private sealed record ResumeState(
        bool Complete,
        int FileIndex,
        int Offset,
        int Total,
        int? UniqueContentIds,
        int SkippedRows);

    private static ResumeState ResumeOffsetCollection(string outputDir, string collection, bool resume, JsonObject progress)
    {
        if (!resume)
        {
            return new ResumeState(false, 0, 0, 0, null, 0);
        }

        if (progress[collection] is JsonObject saved && (saved["complete"]?.GetValue<bool>() ?? false))
        {
            return new ResumeState(
                true,
                saved["files"]?.GetValue<int>() ?? 0,
                saved["offset"]?.GetValue<int>() ?? saved["rows"]?.GetValue<int>() ?? 0,
                saved["rows"]?.GetValue<int>() ?? 0,
                saved["uniqueContentIds"]?.GetValue<int?>(),
                saved["skippedRows"]?.GetValue<int>() ?? 0);
        }

        var chunkFiles = ChunkFileService.ListChunkFiles(outputDir, collection);
        if (chunkFiles.Count == 0)
        {
            return new ResumeState(false, 0, 0, 0, null, 0);
        }

        var total = JsonRowHelper.CountRowsInFiles(chunkFiles);
        Console.WriteLine(
            $"  → Resuming {collection} from chunk {chunkFiles.Count + 1} ({total} rows already on disk)");

        return new ResumeState(
            false,
            chunkFiles.Count,
            progress[collection]?["offset"]?.GetValue<int>() ?? total,
            total,
            progress[collection]?["uniqueContentIds"]?.GetValue<int?>(),
            progress[collection]?["skippedRows"]?.GetValue<int>() ?? 0);
    }

    private sealed record SafePageResult(List<JsonObject> Rows, int Skipped);

    private static SafePageResult FetchCollectionPageSafely(
        Func<int, int, List<JsonObject>> fetchPage,
        string label,
        int offset,
        int size)
    {
        try
        {
            return new SafePageResult(fetchPage(offset, size), 0);
        }
        catch (Exception ex) when (SqlErrorHelper.IsSqlCorruptionError(ex))
        {
            if (size <= ExportConstants.MinPostBatchSize)
            {
                Console.WriteLine($"    ! skipped unreadable {label} row at SQL offset {offset} (Msg 605/823/824)");
                return new SafePageResult([], 1);
            }

            var leftSize = size / 2;
            var rightSize = size - leftSize;
            Console.WriteLine(
                $"    ! {label} batch at SQL offset {offset} failed (Msg 605/823/824); retrying as {leftSize}+{rightSize}");

            var left = FetchCollectionPageSafely(fetchPage, label, offset, leftSize);
            var right = FetchCollectionPageSafely(fetchPage, label, offset + leftSize, rightSize);
            return new SafePageResult(left.Rows.Concat(right.Rows).ToList(), left.Skipped + right.Skipped);
        }
    }

    private static HashSet<int>? TrackUnique(IEnumerable<JsonObject> rows, string field, HashSet<int>? target = null)
    {
        target ??= new HashSet<int>();
        foreach (var row in rows)
        {
            var value = JsonRowHelper.GetInt(row, field);
            if (value is > 0)
            {
                target.Add(value.Value);
            }
        }

        return target;
    }
}
