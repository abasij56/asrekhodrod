namespace SqlServerExporter.Cli;

/// <summary>
/// Windowed post export: start at <see cref="Start"/>, read <see cref="Window"/> rows per stage,
/// write <see cref="FileChunk"/> rows per JSON file.
/// </summary>
public sealed class PostExportSettings
{
    public int Start { get; init; }
    public int Window { get; init; }
    public int FileChunk { get; init; }
    public int SkipBatch { get; init; } = 1;
    public bool Continue { get; init; }
    public int? End { get; init; }

    public bool IsWindowed => Window > 0 && FileChunk > 0;

    public static PostExportSettings FromOptions(ExportOptions options) =>
        new()
        {
            Start = options.Start,
            Window = options.Window,
            FileChunk = options.FileChunk,
            SkipBatch = options.SkipBatch,
            End = options.End,
            Continue = options.End is not null ? false : options.Continue,
        };
}
