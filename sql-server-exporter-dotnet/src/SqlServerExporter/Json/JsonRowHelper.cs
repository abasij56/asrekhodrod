using System.Text.Json.Nodes;
using System.Text.RegularExpressions;

namespace SqlServerExporter.Json;

internal static class JsonRowHelper
{
    public static List<JsonObject> ParseJsonRows(string? raw)
    {
        if (string.IsNullOrWhiteSpace(raw))
        {
            return [];
        }

        var trimmed = raw.Trim();
        if (trimmed.StartsWith("Msg ", StringComparison.Ordinal))
        {
            throw new InvalidOperationException(trimmed.Split('\n')[0]);
        }

        try
        {
            var node = JsonNode.Parse(trimmed);
            if (node is JsonArray array)
            {
                return array.Select(item => item!.AsObject()).ToList();
            }

            if (node is JsonObject single)
            {
                return [single];
            }

            return [];
        }
        catch (Exception ex)
        {
            throw new InvalidOperationException(
                $"Failed to parse SQL JSON ({trimmed.Length} chars). Batch may be too large — retry with a smaller batch. {ex.Message}",
                ex);
        }
    }

    public static void WriteJsonFile(string directory, string fileName, JsonNode? data)
    {
        Directory.CreateDirectory(directory);
        var path = Path.Combine(directory, fileName);
        File.WriteAllText(path, $"{data?.ToJsonString(JsonOptions.WriteIndented) ?? "[]"}\n");
    }

    public static void WriteRowsFile(string directory, string fileName, IReadOnlyList<JsonObject> rows)
    {
        var array = new JsonArray();
        foreach (var row in rows)
        {
            array.Add(row.DeepClone());
        }

        WriteJsonFile(directory, fileName, array);
    }

    public static string? GetString(JsonObject row, string key)
    {
        if (!row.TryGetPropertyValue(key, out var node) || node is null)
        {
            return null;
        }

        return node.GetValueKind() switch
        {
            System.Text.Json.JsonValueKind.String => node.GetValue<string>(),
            System.Text.Json.JsonValueKind.Number => node.ToJsonString(),
            System.Text.Json.JsonValueKind.True => "true",
            System.Text.Json.JsonValueKind.False => "false",
            _ => node.ToJsonString(),
        };
    }

    public static int? GetInt(JsonObject row, string key)
    {
        if (!row.TryGetPropertyValue(key, out var node) || node is null)
        {
            return null;
        }

        if (node is JsonValue value)
        {
            if (value.TryGetValue<int>(out var i))
            {
                return i;
            }

            if (value.TryGetValue<long>(out var l))
            {
                return (int)l;
            }
        }

        return int.TryParse(node.ToJsonString().Trim('"'), out var parsed) ? parsed : null;
    }

    public static List<JsonObject> ReadRowsFile(string path)
    {
        if (!File.Exists(path))
        {
            return [];
        }

        return ParseJsonRows(File.ReadAllText(path));
    }

    public static int CountRowsInFiles(IEnumerable<string> files) =>
        files.Sum(file => ReadRowsFile(file).Count);

    public static IEnumerable<string> ExtractImagesFromBody(string? body)
    {
        if (string.IsNullOrWhiteSpace(body))
        {
            yield break;
        }

        foreach (Match match in Regex.Matches(body, """<img[^>]+src=["']([^"']+)["']""", RegexOptions.IgnoreCase))
        {
            yield return match.Groups[1].Value;
        }

        foreach (Match match in Regex.Matches(
                     body,
                     """(/Uploaded/Image/[^\s"'<>&]+\.(?:jpe?g|png|gif|webp))""",
                     RegexOptions.IgnoreCase))
        {
            yield return match.Groups[1].Value;
        }
    }

    public static int ScoreImagePath(string path)
    {
        var normalized = path.ToLowerInvariant();
        if (Regex.IsMatch(normalized, @"(?:^|/)(thumb|thumbnail|thumbs|small|mini|list|preview)(?:/|$)"))
        {
            return 1;
        }

        if (Regex.IsMatch(normalized, @"(?:^|/)(large|original|full|max)(?:/|$)"))
        {
            return 100_000 + path.Length;
        }

        var dimensions = Regex.Match(normalized, @"(\d{2,4})[xX](\d{2,4})");
        if (dimensions.Success &&
            int.TryParse(dimensions.Groups[1].Value, out var w) &&
            int.TryParse(dimensions.Groups[2].Value, out var h))
        {
            return w * h;
        }

        return 1000 + path.Length;
    }

    public static string PickBestImageUrl(IEnumerable<string> candidates)
    {
        var best = string.Empty;
        var bestScore = -1;

        foreach (var candidate in candidates.Distinct())
        {
            var trimmed = candidate.Trim();
            if (trimmed.Length == 0)
            {
                continue;
            }

            var score = ScoreImagePath(trimmed);
            if (score > bestScore)
            {
                bestScore = score;
                best = trimmed;
            }
        }

        return best;
    }

    public static string ResolveBestPostImageUrl(JsonObject row)
    {
        var imageUrl = GetString(row, "imageUrl");
        if (!string.IsNullOrWhiteSpace(imageUrl))
        {
            return imageUrl.Trim();
        }

        return PickBestImageUrl(ExtractImagesFromBody(GetString(row, "body")));
    }

    public static void EnrichPostImageUrls(IEnumerable<JsonObject> rows)
    {
        foreach (var row in rows)
        {
            row["imageUrl"] = ResolveBestPostImageUrl(row);
        }
    }

    public static List<int> NormalizeContentIds(IEnumerable<int?> ids) =>
        ids
            .Select(id => id ?? 0)
            .Where(id => id > 0)
            .Distinct()
            .ToList();

    public static List<int> NormalizeContentIdsFromRows(IEnumerable<JsonObject> rows, string field = "contentId") =>
        NormalizeContentIds(rows.Select(row => GetInt(row, field)));

    public static IEnumerable<List<T>> ChunkArray<T>(IReadOnlyList<T> items, int size)
    {
        for (var i = 0; i < items.Count; i += size)
        {
            yield return items.Skip(i).Take(size).ToList();
        }
    }
}
