using System.Text.Json;
using System.Text.Json.Nodes;

namespace SqlServerExporter.Services;

internal sealed class ExportProgressService(string outputDir)
{
    private readonly string _progressPath = Path.Combine(outputDir, ".export-progress.json");

    public JsonObject Load()
    {
        if (!File.Exists(_progressPath))
        {
            return new JsonObject();
        }

        try
        {
            return JsonNode.Parse(File.ReadAllText(_progressPath))?.AsObject() ?? new JsonObject();
        }
        catch
        {
            return new JsonObject();
        }
    }

    public void Save(string collection, JsonObject patch)
    {
        var progress = Load();
        var existing = progress[collection] as JsonObject ?? new JsonObject();
        foreach (var (key, value) in patch)
        {
            existing[key] = value?.DeepClone();
        }

        existing["updatedAt"] = DateTime.UtcNow.ToString("o");
        progress[collection] = existing;

        Directory.CreateDirectory(outputDir);
        File.WriteAllText(_progressPath, progress.ToJsonString(JsonOptions.WriteIndented) + Environment.NewLine);
    }

    public JsonObject ClearEmptyBackOnlyProgress(JsonObject progress, string sourceMode)
    {
        if (sourceMode == "front")
        {
            return progress;
        }

        string[] backOnlyCollections =
        [
            "videos",
            "video-categories",
            "reviews",
            "review-categories",
            "magazines",
        ];

        foreach (var collection in backOnlyCollections)
        {
            if (progress[collection] is not JsonObject saved)
            {
                continue;
            }

            var complete = saved["complete"]?.GetValue<bool>() ?? false;
            var rows = saved["rows"]?.GetValue<int>() ?? 0;
            var files = saved["files"]?.GetValue<int>() ?? 0;
            if (complete && rows == 0 && files == 0)
            {
                progress.Remove(collection);
                Console.WriteLine(
                    $"  → {collection} (cleared empty Front-only resume marker; will query Back)");
            }
        }

        return progress;
    }
}

internal static class ChunkFileService
{
    public static string ChunkFilePath(string outputDir, string collection, int index)
    {
        var num = index.ToString("000");
        return Path.Combine(outputDir, collection, $"{collection}-{num}.json");
    }

    public static void WriteChunkFile(string outputDir, string collection, int index, IReadOnlyList<JsonObject> rows)
    {
        var path = ChunkFilePath(outputDir, collection, index);
        Directory.CreateDirectory(Path.GetDirectoryName(path)!);
        var array = new JsonArray();
        foreach (var row in rows)
        {
            array.Add(row.DeepClone());
        }

        File.WriteAllText(path, array.ToJsonString(JsonOptions.WriteIndented) + Environment.NewLine);
    }

    public static List<string> ListChunkFiles(string outputDir, string collection)
    {
        var dir = Path.Combine(outputDir, collection);
        if (!Directory.Exists(dir))
        {
            return [];
        }

        return Directory
            .GetFiles(dir, $"{collection}-*.json")
            .Where(path => System.Text.RegularExpressions.Regex.IsMatch(Path.GetFileName(path), $"^{collection}-\\d+\\.json$"))
            .OrderBy(path => path, StringComparer.Ordinal)
            .ToList();
    }
}
