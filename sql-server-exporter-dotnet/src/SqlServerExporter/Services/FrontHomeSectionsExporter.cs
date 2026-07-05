using System.Text.Json.Nodes;
using SqlServerExporter.Json;
using SqlServerExporter.Sql;

namespace SqlServerExporter.Services;

internal sealed record FrontSectionsExportResult(
    Dictionary<string, int> Exported,
    List<int> ContentIds);

internal static class FrontHomeSectionsExporter
{
    private static readonly FrontSection[] Sections =
    [
        new("MainSlider", true, false),
        new("MainTicker", true, false),
        new("MainTopHits", true, false),
        new("Parsik", true, false),
        new("SpecialEvents", true, false),
        new("TopHits", false, true),
    ];

  private static readonly Dictionary<string, string> FileNames = new()
    {
        ["MainSlider"] = "main-slider",
        ["MainTicker"] = "main-ticker",
        ["MainTopHits"] = "main-top-hits",
        ["Parsik"] = "parsik",
        ["SpecialEvents"] = "special-events",
        ["TopHits"] = "top-hits",
    };

    public static FrontSectionsExportResult Export(SqlJsonQueryService sql, string outputDir)
    {
        var sectionsDir = Path.Combine(outputDir, "front-sections");
        var exported = new Dictionary<string, int>();
        var contentIds = new HashSet<int>();

        foreach (var section in Sections)
        {
            var file = FileNames[section.Table];
            Console.WriteLine($"  → front-sections/{file}.json ({section.Table})");
            List<JsonObject> rows;
            try
            {
                rows = sql.RunJsonQuery(
                    ExportConstants.DatabaseFront,
                    SqlFragments.FrontSectionRefQuery(section));
            }
            catch (Exception ex)
            {
                Console.WriteLine($"    ! skipped {section.Table}: {ex.Message}");
                rows = [];
            }

            JsonRowHelper.WriteRowsFile(sectionsDir, $"{file}.json", rows);
            exported[file] = rows.Count;
            foreach (var row in rows)
            {
                var contentId = JsonRowHelper.GetInt(row, "contentId");
                if (contentId is > 0)
                {
                    contentIds.Add(contentId.Value);
                }
            }
        }

        return new FrontSectionsExportResult(exported, contentIds.OrderBy(id => id).ToList());
    }
}
