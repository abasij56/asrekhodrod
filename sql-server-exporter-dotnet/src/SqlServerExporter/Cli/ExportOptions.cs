namespace SqlServerExporter.Cli;

public sealed class ExportOptions
{
    public string? Profile { get; init; }
    public string? Server { get; init; }
    public string Source { get; init; } = "auto";
    public bool ExportAll { get; init; }
    public bool Resume { get; init; } = true;
    public bool EnrichImagesOnly { get; init; }
    public bool GalleryOnly { get; init; }
    public bool SkipContentFileImages { get; init; }
    public int Limit { get; init; } = 100;
    public int ReviewLimit { get; init; } = 50;
    public int MagazineLimit { get; init; } = 50;
    public int Start { get; init; }
    public int Window { get; init; }
    public int FileChunk { get; init; }
    public int SkipBatch { get; init; } = 1;
    public bool Continue { get; init; }
    public int? End { get; init; }
    public required string Output { get; init; }
    public required string ProjectRoot { get; init; }

    public bool UseWindowedPosts => Window > 0 && FileChunk > 0;

    public static ExportOptions Parse(string[] args, string projectRoot)
    {
        var exportAll = args.Contains("--all");
        var limit = ParseInt(GetArg(args, "limit", exportAll ? "0" : "100"), exportAll ? 0 : 100);
        var reviewLimit = ParseInt(GetArg(args, "review-limit", exportAll ? "0" : "50"), exportAll ? 0 : 50);
        var magazineLimit = ParseInt(GetArg(args, "magazine-limit", exportAll ? "0" : "50"), exportAll ? 0 : 50);
        var start = Math.Max(0, ParseInt(GetArg(args, "start", "0"), 0));
        var window = ParseInt(GetArg(args, "window", "0"), 0);
        var fileChunk = ParseInt(GetArg(args, "file-chunk", "0"), 0);
        var skipBatch = ParseInt(GetArg(args, "skip-batch", "1"), 1);
        var continueWindows = ParseInt(GetArg(args, "continue", "0"), 0) == 1;
        int? end = GetArg(args, "end", null) is { } endRaw
            ? Math.Max(0, ParseInt(endRaw, 0))
            : null;

        var defaultOutput = Path.GetFullPath(Path.Combine(projectRoot, "..", "awp", "wp-content", "asrekhodro-import"));

        return new ExportOptions
        {
            Profile = GetArg(args, "profile", null),
            Server = GetArg(args, "server", null),
            Source = GetArg(args, "source", "auto") ?? "auto",
            ExportAll = exportAll,
            Resume = !args.Contains("--no-resume"),
            EnrichImagesOnly = args.Contains("--enrich-images-only"),
            GalleryOnly = args.Contains("--gallery-only"),
            SkipContentFileImages = args.Contains("--skip-content-file-images"),
            Limit = limit,
            ReviewLimit = reviewLimit,
            MagazineLimit = magazineLimit,
            Start = start,
            Window = window,
            FileChunk = fileChunk,
            SkipBatch = Math.Max(1, skipBatch),
            Continue = continueWindows,
            End = end,
            Output = Path.GetFullPath(GetArg(args, "output", defaultOutput) ?? defaultOutput),
            ProjectRoot = projectRoot,
        };
    }

    private static string? GetArg(string[] args, string name, string? fallback)
    {
        var flag = $"--{name}";
        var eqPrefix = $"{flag}=";

        foreach (var arg in args)
        {
            if (arg.StartsWith(eqPrefix, StringComparison.Ordinal))
            {
                var value = arg[eqPrefix.Length..];
                return value.Length > 0 ? value : fallback;
            }
        }

        var index = Array.IndexOf(args, flag);
        if (index >= 0 && index + 1 < args.Length && !args[index + 1].StartsWith('-'))
        {
            return args[index + 1];
        }

        return fallback;
    }

    private static int ParseInt(string? raw, int fallback) =>
        int.TryParse(raw, out var value) ? value : fallback;
}
