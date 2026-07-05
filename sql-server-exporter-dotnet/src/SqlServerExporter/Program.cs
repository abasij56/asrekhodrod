using SqlServerExporter.Cli;
using SqlServerExporter.Config;
using SqlServerExporter.Export;
using SqlServerExporter.Services;

ExportStageLog.Boot("process started (if dotnet is building, output appears after build finishes)");

var projectRoot = ResolveProjectRoot();
ExportStageLog.Boot($"project root: {projectRoot}");

var options = ExportOptions.Parse(args, projectRoot);
ExportStageLog.Boot($"output: {options.Output}");

try
{
    ExportStageLog.Boot("load connection.config.json...");
    var resolved = ResolveConnection(projectRoot, options);
    ExportStageLog.Boot($"SQL server: {resolved.Profile.Server} (profile={resolved.Name})");
    Environment.ExitCode = new WordPressExporter(options, resolved).Run();
}
catch (Exception ex)
{
    Console.Error.WriteLine(ex.Message);
    Environment.ExitCode = 1;
}

static string ResolveProjectRoot()
{
    var candidates = new[]
    {
        Directory.GetCurrentDirectory(),
        AppContext.BaseDirectory,
    };

    foreach (var start in candidates)
    {
        var dir = new DirectoryInfo(Path.GetFullPath(start));
        while (dir is not null)
        {
            if (File.Exists(Path.Combine(dir.FullName, "connection.config.json")) ||
                File.Exists(Path.Combine(dir.FullName, "connection.config.example.json")))
            {
                return dir.FullName;
            }

            if (dir.Name.Equals("sql-server-exporter-dotnet", StringComparison.OrdinalIgnoreCase))
            {
                return dir.FullName;
            }

            dir = dir.Parent;
        }
    }

    return Directory.GetCurrentDirectory();
}
static ResolvedConnection ResolveConnection(string projectRoot, ExportOptions options)
{
    if (!string.IsNullOrWhiteSpace(options.Server))
    {
        var config = ConnectionConfigLoader.Load(projectRoot);
        return new ResolvedConnection(
            "cli",
            new ConnectionProfile
            {
                Server = options.Server.Trim(),
            },
            config);
    }

    return ConnectionConfigLoader.Resolve(projectRoot, options.Profile);
}
