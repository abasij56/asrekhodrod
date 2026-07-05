using System.Text.Json.Nodes;
using SqlServerExporter.Json;
using SqlServerExporter.Sql;

namespace SqlServerExporter.Services;

internal static class PostMainImageResolver
{
    public static int Apply(
        SqlJsonQueryService sql,
        List<JsonObject> rows,
        bool tryContentFiles,
        bool useFrontImageRules,
        int? recordOffset = null)
    {
        if (!useFrontImageRules)
        {
            foreach (var row in rows)
            {
                row.Remove("mainImageId");
            }

            return 0;
        }

        var upgraded = 0;
        var lookupTotal = 0;
        if (tryContentFiles)
        {
            foreach (var row in rows)
            {
                var contentId = JsonRowHelper.GetInt(row, "contentId") ?? 0;
                var mainImageFileId = JsonRowHelper.GetInt(row, "mainImageId");
                if (contentId > 0 && mainImageFileId is > 0)
                {
                    lookupTotal += 1;
                }
            }

            if (lookupTotal > 0)
            {
                var recordHint = recordOffset is int baseOffset ? $" at record offset {baseOffset}" : string.Empty;
                ExportStageLog.Detail($"resolve images: 0/{lookupTotal}{recordHint}");
            }
        }

        var lookupDone = 0;

        for (var i = 0; i < rows.Count; i++)
        {
            var row = rows[i];
            var contentId = JsonRowHelper.GetInt(row, "contentId") ?? 0;
            var mainImageFileId = JsonRowHelper.GetInt(row, "mainImageId");

            if (tryContentFiles && contentId > 0 && mainImageFileId is > 0)
            {
                var url = TryFetchMainImageUrl(sql, mainImageFileId.Value);
                lookupDone += 1;
                if (lookupDone == lookupTotal ||
                    lookupDone % ExportConstants.ImageLookupProgressInterval == 0)
                {
                    var recordHint = recordOffset is int baseOffset
                        ? $", record ~{baseOffset + i}"
                        : string.Empty;
                    ExportStageLog.Detail($"resolve images: {lookupDone}/{lookupTotal}{recordHint}");
                }

                if (string.IsNullOrWhiteSpace(url))
                {
                    var recordLabel = recordOffset is int baseOffset
                        ? $"error records {baseOffset + i}"
                        : "error find image";
                    Console.WriteLine($"    {recordLabel} (contentId={contentId}, fileId={mainImageFileId})");
                    Console.Out.Flush();
                }
                else
                {
                    var before = JsonRowHelper.GetString(row, "imageUrl")?.Trim() ?? string.Empty;
                    row["imageUrl"] = url;
                    if (url != before)
                    {
                        upgraded += 1;
                    }
                }
            }

            row.Remove("mainImageId");
        }

        return upgraded;
    }

    private static string? TryFetchMainImageUrl(SqlJsonQueryService sql, int fileId)
    {
        try
        {
            var fileRows = sql.RunJsonQuery(
                ExportConstants.DatabaseFront,
                SqlFragments.ContentFileMainImageByFileIdQuery(fileId));
            var url = fileRows.Count > 0 ? JsonRowHelper.GetString(fileRows[0], "url")?.Trim() : null;
            return string.IsNullOrWhiteSpace(url) ? null : url;
        }
        catch (Exception ex) when (SqlErrorHelper.IsSqlCorruptionError(ex))
        {
            return null;
        }
    }
}
