using System.Text.Json.Nodes;
using SqlServerExporter.Json;

namespace SqlServerExporter.Services;

internal sealed record ImageEnrichResult(int PostsWithImageUrl, int Updated);

/// <summary>
/// Re-run main-image resolution on already-exported post JSON (--enrich-images-only).
/// Normal export resolves images inline in <see cref="PostExporter"/>.
/// </summary>
internal static class PostImageEnricher
{
    public static ImageEnrichResult EnrichExportedPostImages(
        SqlJsonQueryService sql,
        string sourceMode,
        bool backUnavailable,
        string outputDir,
        PostExportResult postExport,
        bool resume,
        JsonObject progress,
        ExportProgressService progressService)
    {
        var useFront = sourceMode == "front" || backUnavailable;
        if (!useFront)
        {
            return new ImageEnrichResult(postExport.PostsWithImageUrl, 0);
        }

        Console.WriteLine(
            "  → post images: per-post ContentFiles lookup (MainImageId=FileId, ImageDimensionId≠1), else SingleContent.ImageURL");

        var updated = 0;

        if (postExport.Chunked)
        {
            var chunkFiles = ChunkFileService.ListChunkFiles(outputDir, "posts");
            foreach (var file in chunkFiles)
            {
                var rows = JsonRowHelper.ReadRowsFile(file);
                updated += PostMainImageResolver.Apply(sql, rows, tryContentFiles: true, useFrontImageRules: true);
                JsonRowHelper.WriteRowsFile(Path.GetDirectoryName(file)!, Path.GetFileName(file), rows);
            }

            var withImage = chunkFiles.Sum(file =>
                JsonRowHelper.ReadRowsFile(file).Count(row =>
                    !string.IsNullOrWhiteSpace(JsonRowHelper.GetString(row, "imageUrl"))));

            return new ImageEnrichResult(withImage, updated);
        }

        var inlineRows = postExport.Posts;
        var postsPath = Path.Combine(outputDir, "posts.json");
        if (inlineRows.Count == 0 && File.Exists(postsPath))
        {
            inlineRows = JsonRowHelper.ReadRowsFile(postsPath);
        }

        if (inlineRows.Count > 0)
        {
            updated += PostMainImageResolver.Apply(sql, inlineRows, tryContentFiles: true, useFrontImageRules: true);
            JsonRowHelper.WriteRowsFile(outputDir, "posts.json", inlineRows);
        }

        var postsWithImageUrl = inlineRows.Count(row =>
            !string.IsNullOrWhiteSpace(JsonRowHelper.GetString(row, "imageUrl")));

        return new ImageEnrichResult(postsWithImageUrl, updated);
    }
}
