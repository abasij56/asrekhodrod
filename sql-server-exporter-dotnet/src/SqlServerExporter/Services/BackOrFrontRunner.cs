using System.Text.Json.Nodes;
using SqlServerExporter.Json;
using SqlServerExporter.Sql;

namespace SqlServerExporter.Services;

internal sealed class BackOrFrontRunner(SqlJsonQueryService sql, string sourceMode)
{
    private bool _backUnavailable;

    public bool BackUnavailable => _backUnavailable;

    public void MarkBackUnavailable(string label)
    {
        if (_backUnavailable)
        {
            return;
        }

        _backUnavailable = true;
        Console.WriteLine(
            $"  ! AsreKhodroBack unavailable (Msg 823) during {label}. " +
            "Using AsreKhodroFront / skipping Back-only data for the rest of this run.");
        Console.WriteLine("    Tip: use --source=front to skip Back entirely.");
    }

    public List<JsonObject> RunBackOrFront(string label, string backQuery, string? frontQuery)
    {
        if (sourceMode == "front" || (_backUnavailable && frontQuery is not null))
        {
            if (frontQuery is null)
            {
                Console.WriteLine($"  [skip] {label} — not available from AsreKhodroFront");
                return [];
            }

            Console.WriteLine($"  → {label} (AsreKhodroFront)");
            Console.Out.Flush();
            return sql.RunJsonQuery(ExportConstants.DatabaseFront, frontQuery);
        }

        try
        {
            Console.WriteLine($"  → {label} (AsreKhodroBack)");
            return sql.RunJsonQuery(ExportConstants.DatabaseBack, backQuery);
        }
        catch (Exception ex) when (sourceMode == "auto" && frontQuery is not null && SqlErrorHelper.IsSqlCorruptionError(ex))
        {
            MarkBackUnavailable(label);
            return sql.RunJsonQuery(ExportConstants.DatabaseFront, frontQuery);
        }
        catch (Exception ex)
        {
            throw SqlErrorHelper.EnrichSqlcmdError(ex);
        }
    }

    public List<JsonObject> RunBackOnly(string label, string backQuery, string database = ExportConstants.DatabaseBack)
    {
        if (sourceMode == "front" && database == ExportConstants.DatabaseBack)
        {
            Console.WriteLine($"  [skip] {label} — requires {database}");
            return [];
        }

        if (_backUnavailable && database == ExportConstants.DatabaseBack)
        {
            Console.WriteLine($"  [skip] {label} — {database} unavailable (Msg 823)");
            return [];
        }

        try
        {
            Console.WriteLine($"  → {label} ({database})");
            return sql.RunJsonQuery(database, backQuery);
        }
        catch (Exception ex) when (sourceMode == "auto" && SqlErrorHelper.IsSqlCorruptionError(ex))
        {
            MarkBackUnavailable(label);
            Console.WriteLine($"  [skip] {label} — {database} unavailable (Msg 823)");
            return [];
        }
        catch (Exception ex)
        {
            throw SqlErrorHelper.EnrichSqlcmdError(ex);
        }
    }

    public List<JsonObject> FetchPagedBackOrFront(
        string label,
        string backBaseQuery,
        string? frontBaseQuery,
        int offset,
        int batchSize,
        string backOrderBy,
        string? frontOrderBy = null)
    {
        var frontOrder = frontOrderBy ?? backOrderBy;
        return RunBackOrFront(
            label,
            SqlFragments.PaginateJsonQuery(backBaseQuery, backOrderBy, offset, batchSize),
            frontBaseQuery is null
                ? null
                : SqlFragments.PaginateJsonQuery(frontBaseQuery, frontOrder, offset, batchSize));
    }

    public List<JsonObject> FetchPagedBackOnly(
        string label,
        string baseQuery,
        string database,
        int offset,
        int batchSize,
        string orderBy) =>
        RunBackOnly(
            label,
            SqlFragments.PaginateJsonQuery(baseQuery, orderBy, offset, batchSize),
            database);
}
