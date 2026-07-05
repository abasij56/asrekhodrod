using System.Text.Json;
using System.Text.Json.Serialization;

namespace SqlServerExporter;

internal static class JsonOptions
{
    public static readonly JsonSerializerOptions Config = new()
    {
        PropertyNameCaseInsensitive = true,
        ReadCommentHandling = JsonCommentHandling.Skip,
        AllowTrailingCommas = true,
    };

    public static readonly JsonSerializerOptions WriteIndented = new()
    {
        WriteIndented = true,
        DefaultIgnoreCondition = JsonIgnoreCondition.WhenWritingNull,
    };
}
