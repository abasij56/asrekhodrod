using System.Text;
using System.Text.Json.Nodes;
using Microsoft.Data.SqlClient;
using SqlServerExporter.Config;
using SqlServerExporter.Json;

namespace SqlServerExporter.Services;

internal sealed class SqlJsonQueryService(ConnectionProfile profile)
{
    public List<JsonObject> RunJsonQuery(string database, string query)
    {
        for (var attempt = 1; attempt <= ExportConstants.SqlRetries + 1; attempt++)
        {
            try
            {
                return ExecuteOnce(database, query);
            }
            catch (Exception ex)
            {
                if (SqlErrorHelper.IsSqlCorruptionError(ex))
                {
                    throw;
                }

                if (!IsTransient(ex) || attempt > ExportConstants.SqlRetries)
                {
                    throw;
                }

                var delayMs = Math.Min(30_000, 2_000 * attempt);
                Console.WriteLine(
                    $"  ! SQL {database} disconnected, retry {attempt}/{ExportConstants.SqlRetries} in {delayMs / 1000}s");
                Console.Out.Flush();
                Thread.Sleep(delayMs);
            }
        }

        throw new InvalidOperationException($"SQL query failed after retries ({database}).");
    }

    private List<JsonObject> ExecuteOnce(string database, string query)
    {
        if (!IsPerPostImageLookup(query))
        {
            ExportStageLog.Detail(
                $"SQL connect {database} ({profile.Server}, timeout {ExportConstants.SqlConnectTimeoutSeconds}s)...");
        }

        using var connection = new SqlConnection(ConnectionConfigLoader.ToConnectionString(profile, database));
        connection.Open();

        if (!IsPerPostImageLookup(query))
        {
            ExportStageLog.Detail($"SQL connected {database}, executing query...");
        }

        using var command = connection.CreateCommand();
        command.CommandText = query;
        command.CommandTimeout = 0;

        var builder = new StringBuilder();
        using var reader = command.ExecuteReader();
        while (reader.Read())
        {
            if (!reader.IsDBNull(0))
            {
                builder.Append(reader.GetString(0));
            }
        }

        return JsonRowHelper.ParseJsonRows(builder.ToString());
    }

    private static bool IsPerPostImageLookup(string query) =>
        query.Contains("FROM dbo.ContentFiles cf", StringComparison.Ordinal)
        && query.Contains("cf.FileId =", StringComparison.Ordinal);

    private static bool IsTransient(Exception ex)
    {
        if (SqlErrorHelper.IsSqlCorruptionError(ex))
        {
            return false;
        }

        var text = ex.ToString();
        return ex is SqlException sqlEx && (
                   sqlEx.Number is 10053 or 10054 ||
                   text.Contains("transport-level error", StringComparison.OrdinalIgnoreCase) ||
                   text.Contains("forcibly closed", StringComparison.OrdinalIgnoreCase) ||
                   text.Contains("timeout", StringComparison.OrdinalIgnoreCase));
    }
}

internal static class SqlErrorHelper
{
    private static readonly int[] CorruptionErrorNumbers = [605, 823, 824];

    public static bool IsBackCorruptionError(Exception ex) =>
        IsSqlCorruptionError(ex) && ExceptionText(ex).Contains("AsreKhodroBack", StringComparison.OrdinalIgnoreCase);

    public static bool IsSqlCorruptionError(Exception ex)
    {
        for (var current = ex; current is not null; current = current.InnerException)
        {
            if (current is SqlException sqlEx)
            {
                if (CorruptionErrorNumbers.Contains(sqlEx.Number))
                {
                    return true;
                }

                foreach (SqlError err in sqlEx.Errors)
                {
                    if (CorruptionErrorNumbers.Contains(err.Number))
                    {
                        return true;
                    }
                }
            }

            if (ContainsCorruptionMarkers(current.Message))
            {
                return true;
            }
        }

        return ContainsCorruptionMarkers(ExceptionText(ex));
    }

    private static bool ContainsCorruptionMarkers(string text) =>
        text.Contains("Msg 605", StringComparison.Ordinal)
        || text.Contains("Msg 823", StringComparison.Ordinal)
        || text.Contains("Msg 824", StringComparison.Ordinal)
        || text.Contains("cyclic redundancy check", StringComparison.OrdinalIgnoreCase)
        || text.Contains("Data error (cyclic redundancy check)", StringComparison.OrdinalIgnoreCase)
        || text.Contains("AsreKhodroBack.mdf", StringComparison.OrdinalIgnoreCase)
        || text.Contains("AsreKhodroFront.mdf", StringComparison.OrdinalIgnoreCase);

    private static string ExceptionText(Exception ex) => ex.ToString();

    public static Exception EnrichSqlcmdError(Exception ex)
    {
        if (!IsSqlCorruptionError(ex))
        {
            return ex;
        }

        var detail = ExceptionText(ex);
        var database = detail.Contains("AsreKhodroFront", StringComparison.OrdinalIgnoreCase)
            ? "AsreKhodroFront"
            : "AsreKhodroBack";
        var hint = database == "AsreKhodroFront"
            ? "Corrupt page(s) are skipped when --skip-batch is set; reduce --file-chunk or ask the DBA to run DBCC CHECKDB."
            : "Re-run with --source=front to export published content from AsreKhodroFront, " +
              "or ask the DBA to restore AsreKhodroBack from backup.";

        return new InvalidOperationException(
            $"{database} database file is corrupted on the server (Msg 605/823/824). {hint}\n\n" +
            detail.Trim(),
            ex);
    }
}
