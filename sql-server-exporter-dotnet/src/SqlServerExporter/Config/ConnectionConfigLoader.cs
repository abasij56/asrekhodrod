using System.Text.Json;

namespace SqlServerExporter.Config;

public sealed class ConnectionProfile
{
    public string? Description { get; set; }
    public required string Server { get; set; }
    public string? Database { get; set; }
    public string? User { get; set; }
    public string? Password { get; set; }
    public bool Encrypt { get; set; } = true;
    public bool TrustServerCertificate { get; set; } = true;
}

public sealed class ConnectionConfig
{
    public string Active { get; set; } = "remote";
    public Dictionary<string, ConnectionProfile> Connections { get; set; } = new();
}

public sealed record ResolvedConnection(string Name, ConnectionProfile Profile, ConnectionConfig Config);

public static class ConnectionConfigLoader
{
    public static string ConfigPath(string projectRoot) =>
        Path.Combine(projectRoot, "connection.config.json");

    public static string ExampleConfigPath(string projectRoot) =>
        Path.Combine(projectRoot, "connection.config.example.json");

    public static ConnectionConfig Load(string projectRoot)
    {
        var file = ConfigPath(projectRoot);
        if (!File.Exists(file))
        {
            throw new FileNotFoundException(
                $"Missing {Path.GetFileName(file)}. Copy {Path.GetFileName(ExampleConfigPath(projectRoot))} to {Path.GetFileName(file)} and set your credentials.",
                file);
        }

        var json = File.ReadAllText(file);
        var config = JsonSerializer.Deserialize<ConnectionConfig>(json, JsonOptions.Config)
            ?? throw new InvalidOperationException($"{Path.GetFileName(file)} is invalid.");

        if (config.Connections.Count == 0)
        {
            throw new InvalidOperationException($"{Path.GetFileName(file)} must contain a \"connections\" object.");
        }

        return config;
    }

    public static ResolvedConnection Resolve(string projectRoot, string? profileName = null)
    {
        var config = Load(projectRoot);
        var name = profileName ?? config.Active ?? "remote";
        if (!config.Connections.TryGetValue(name, out var profile))
        {
            var available = string.Join(", ", config.Connections.Keys);
            throw new InvalidOperationException($"Unknown connection profile \"{name}\". Available: {available}");
        }

        if (string.IsNullOrWhiteSpace(profile.Server))
        {
            throw new InvalidOperationException($"Connection profile \"{name}\" is missing \"server\".");
        }

        return new ResolvedConnection(name, profile, config);
    }

    public static string ToConnectionString(ConnectionProfile profile, string database)
    {
        var builder = new Microsoft.Data.SqlClient.SqlConnectionStringBuilder
        {
            DataSource = profile.Server,
            InitialCatalog = database,
            Encrypt = profile.Encrypt,
            TrustServerCertificate = profile.TrustServerCertificate,
            ConnectTimeout = ExportConstants.SqlConnectTimeoutSeconds,
        };

        if (!string.IsNullOrWhiteSpace(profile.User))
        {
            builder.UserID = profile.User;
            builder.Password = profile.Password ?? string.Empty;
        }
        else
        {
            builder.IntegratedSecurity = true;
        }

        return builder.ConnectionString;
    }
}
