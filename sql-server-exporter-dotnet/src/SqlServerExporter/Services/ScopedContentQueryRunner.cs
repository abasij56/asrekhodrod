using System.Text.Json.Nodes;
using SqlServerExporter.Json;
using SqlServerExporter.Sql;

namespace SqlServerExporter.Services;

internal static class ScopedContentQueryRunner
{
    public static List<JsonObject> RunSingleContentIdColumn(
        BackOrFrontRunner runner,
        string label,
        IReadOnlyList<int> contentIds,
        Func<string, string> backQuery,
        Func<string, string> frontQuery)
    {
        if (contentIds.Count == 0)
        {
            return [];
        }

        if (contentIds.Count <= ExportConstants.ContentIdInClauseBatchSize)
        {
            var idList = string.Join(",", contentIds);
            return runner.RunBackOrFront(label, backQuery(idList), frontQuery(idList));
        }

        var batchSize = ExportConstants.ContentIdInClauseBatchSize;
        var batchCount = (contentIds.Count + batchSize - 1) / batchSize;
        Console.WriteLine($"  → {label} ({contentIds.Count} posts, {batchCount} batches)");

        var results = new List<JsonObject>();
        for (var offset = 0; offset < contentIds.Count; offset += batchSize)
        {
            var batch = contentIds.Skip(offset).Take(batchSize).ToList();
            var idList = string.Join(",", batch);
            var batchIndex = offset / batchSize + 1;
            var rows = runner.RunBackOrFront(
                $"{label} (batch {batchIndex}/{batchCount})",
                backQuery(idList),
                frontQuery(idList));
            results.AddRange(rows);
        }

        return results;
    }

    public static List<JsonObject> RunPostRelations(
        BackOrFrontRunner runner,
        string label,
        IReadOnlyList<int> contentIds)
    {
        if (contentIds.Count == 0)
        {
            return [];
        }

        if (contentIds.Count <= ExportConstants.ContentIdInClauseBatchSize)
        {
            var idList = string.Join(",", contentIds);
            return runner.RunBackOrFront(
                label,
                WordPressExportQueries.PostRelationsBackBase(idList),
                WordPressExportQueries.PostRelationsFrontBase(idList));
        }

        var idSet = contentIds.ToHashSet();
        var batchSize = ExportConstants.ContentIdInClauseBatchSize;
        var batchCount = (contentIds.Count + batchSize - 1) / batchSize;
        Console.WriteLine($"  → {label} ({contentIds.Count} posts, {batchCount} parent batches)");

        var results = new List<JsonObject>();
        for (var offset = 0; offset < contentIds.Count; offset += batchSize)
        {
            var batch = contentIds.Skip(offset).Take(batchSize).ToList();
            var idList = string.Join(",", batch);
            var batchIndex = offset / batchSize + 1;
            var rows = runner.RunBackOrFront(
                $"{label} (batch {batchIndex}/{batchCount})",
                WordPressExportQueries.PostRelationsBackByParentIds(idList),
                WordPressExportQueries.PostRelationsFrontByParentIds(idList));

            foreach (var row in rows)
            {
                var childId = JsonRowHelper.GetInt(row, "childContentId") ?? 0;
                if (childId > 0 && idSet.Contains(childId))
                {
                    results.Add(row);
                }
            }
        }

        return results;
    }

    public static List<JsonObject> RunBackOnlySingleContentIdColumn(
        BackOrFrontRunner runner,
        string label,
        IReadOnlyList<int> contentIds,
        Func<string, string> backQuery,
        string database)
    {
        if (contentIds.Count == 0)
        {
            return [];
        }

        if (contentIds.Count <= ExportConstants.ContentIdInClauseBatchSize)
        {
            var idList = string.Join(",", contentIds);
            return runner.RunBackOnly(label, backQuery(idList), database);
        }

        var batchSize = ExportConstants.ContentIdInClauseBatchSize;
        var batchCount = (contentIds.Count + batchSize - 1) / batchSize;
        Console.WriteLine($"  → {label} ({contentIds.Count} posts, {batchCount} batches)");

        var results = new List<JsonObject>();
        for (var offset = 0; offset < contentIds.Count; offset += batchSize)
        {
            var batch = contentIds.Skip(offset).Take(batchSize).ToList();
            var idList = string.Join(",", batch);
            var batchIndex = offset / batchSize + 1;
            var rows = runner.RunBackOnly(
                $"{label} (batch {batchIndex}/{batchCount})",
                backQuery(idList),
                database);
            results.AddRange(rows);
        }

        return results;
    }
}
