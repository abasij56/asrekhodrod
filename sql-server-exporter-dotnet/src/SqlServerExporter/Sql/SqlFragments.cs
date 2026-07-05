using System.Collections.Generic;
using System.Linq;

namespace SqlServerExporter.Sql;

internal sealed record FrontSection(string Table, bool HasRowId, bool HasHitCount);

internal static class SqlFragments
{
    /** 0 or negative = export all rows (no SQL TOP). */
    public static string SqlTop(int limit)
    {
        if (limit <= 0)
        {
            return string.Empty;
        }

        return $"TOP ({limit}) ";
    }

    public static string SqlPage(int offset, int batchSize)
    {
        var start = offset < 0 ? 0 : offset;
        var size = batchSize <= 0 ? ExportConstants.ChunkSize : batchSize;
        return $"""

                ORDER BY publishSort DESC, contentId DESC
                OFFSET {start} ROWS FETCH NEXT {size} ROWS ONLY
                """;
    }

    public static string SqlOffsetPage(string orderBy, int offset, int batchSize)
    {
        var start = offset < 0 ? 0 : offset;
        var size = batchSize <= 0 ? ExportConstants.ChunkSize : batchSize;
        return $"""

                ORDER BY {orderBy}
                OFFSET {start} ROWS FETCH NEXT {size} ROWS ONLY
                """;
    }

    public static string PaginateJsonQuery(string baseQuery, string orderBy, int offset, int batchSize)
    {
        var trimmed = baseQuery.Trim();
        trimmed = System.Text.RegularExpressions.Regex.Replace(trimmed, @"FOR JSON PATH;\s*$", string.Empty, System.Text.RegularExpressions.RegexOptions.IgnoreCase);
        trimmed = System.Text.RegularExpressions.Regex.Replace(trimmed, @"\nORDER BY[\s\S]*$", string.Empty, System.Text.RegularExpressions.RegexOptions.IgnoreCase);
        return $"{trimmed}{SqlOffsetPage(orderBy, offset, batchSize)}\nFOR JSON PATH;";
    }

    public static string SqlFrontMainImageFileUrl(string contentIdExpr, string scAlias = "sc", string database = "dbo")
    {
        var cfTable = database == "dbo" ? "dbo.ContentFiles" : $"{database}.dbo.ContentFiles";

        return $"""
                (
                    SELECT TOP 1 NULLIF(LTRIM(RTRIM(cf.URL)), '')
                    FROM {cfTable} cf
                    WHERE cf.ContentId = {contentIdExpr}
                      AND cf.URL IS NOT NULL
                      AND LTRIM(RTRIM(cf.URL)) <> ''
                    ORDER BY
                      CASE
                        WHEN {scAlias}.MainImageId IS NOT NULL AND cf.RowId = {scAlias}.MainImageId THEN 0
                        ELSE 1
                      END,
                      CASE WHEN cf.IsMain = 1 THEN 0 ELSE 1 END,
                      cf.ImageDimensionId DESC,
                      cf.RowId DESC
                  )
                """;
    }

    public static string SqlPostImageUrlExpression(
        string contentIdExpr = "ci.Id",
        string scAlias = "sc",
        string mlcAlias = "mlc",
        bool includeMlc = true)
    {
        var parts = new List<string>
        {
            SqlFrontMainImageFileUrl(contentIdExpr, scAlias, "AsreKhodroFront"),
            $"NULLIF(LTRIM(RTRIM({scAlias}.ImageURL)), '')",
        };

        if (includeMlc)
        {
            parts.Add($"NULLIF(LTRIM(RTRIM({mlcAlias}.ImageURL)), '')");
        }

        return $"""
                COALESCE(
                    {string.Join(",\n    ", parts)}
                  )
                """;
    }

    public static string SqlFrontBestContentFileUrl(string scAlias = "sc", string database = "dbo")
    {
        var cfTable = database == "dbo" ? "dbo.ContentFiles" : $"{database}.dbo.ContentFiles";

        return $"""
                (
                    SELECT TOP 1 NULLIF(LTRIM(RTRIM(cf.URL)), '')
                    FROM {cfTable} cf
                    WHERE cf.ContentId = {scAlias}.ContentId
                      AND cf.URL IS NOT NULL
                      AND LTRIM(RTRIM(cf.URL)) <> ''
                    ORDER BY
                      CASE
                        WHEN {scAlias}.MainImageId IS NOT NULL AND cf.RowId = {scAlias}.MainImageId THEN 0
                        ELSE 1
                      END,
                      CASE WHEN cf.IsMain = 1 THEN 0 ELSE 1 END,
                      cf.ImageDimensionId DESC,
                      cf.RowId DESC
                  )
                """;
    }

    public static string SqlFrontPostImageUrlExpression(string scAlias = "sc")
    {
        return $"""
                COALESCE(
                    {SqlFrontBestContentFileUrl(scAlias)},
                    NULLIF(LTRIM(RTRIM({scAlias}.ImageURL)), '')
                  )
                """;
    }

    /** @deprecated use SqlFrontPostImageUrlExpression */
    public static string SqlFrontImageUrlColumn(string scAlias = "sc")
    {
        return $"{SqlFrontPostImageUrlExpression(scAlias)} AS imageUrl";
    }

    public static string FrontSectionRefQuery(FrontSection section)
    {
        var columns = new List<string>();
        if (section.HasRowId)
        {
            columns.Add("RowId AS rowId");
        }

        columns.Add("ContentId AS contentId");
        columns.Add("Periority AS priority");
        if (section.HasHitCount)
        {
            columns.Add("HitCount AS hitCount");
        }

        return $"""
                SET NOCOUNT ON;
                SELECT
                  {string.Join(",\n  ", columns)}
                FROM dbo.{section.Table}
                ORDER BY Periority DESC, PublishTime DESC, ContentId DESC
                FOR JSON PATH;
                """;
    }

    public static string PostsFrontQuery(string topClause, string pageClause)
    {
        const string frontPostColumns = """
              sc.ContentId AS contentId,
              sc.DomainId AS domainId,
              CAST(NULL AS int) AS contentTypeId,
              CAST(3 AS tinyint) AS statusId,
              sc.Title AS title,
              sc.OverTitle AS overTitle,
              sc.UnderTitle AS underTitle,
              sc.ShortBody AS excerpt,
              sc.Body AS body,
              sc.Footer AS footer,
              sc.Author AS author,
              sc.PublishTime AS publishTime,
              sc.PublishTime AS contentTime,
              CAST(NULL AS datetime) AS expireTime,
              sc.MainImageId AS mainImageId,
              NULLIF(LTRIM(RTRIM(sc.ImageURL)), '') AS imageUrl
            """;

        if (!string.IsNullOrWhiteSpace(pageClause))
        {
            return $"""
                    SET NOCOUNT ON;
                    SELECT
                      contentId,
                      domainId,
                      contentTypeId,
                      statusId,
                      title,
                      overTitle,
                      underTitle,
                      excerpt,
                      body,
                      footer,
                      author,
                      publishTime,
                      contentTime,
                      expireTime,
                      mainImageId,
                      imageUrl
                    FROM (
                      SELECT
                        {frontPostColumns},
                        sc.PublishTime AS publishSort
                      FROM dbo.SingleContent sc
                    ) AS ranked
                    {pageClause}
                    FOR JSON PATH;
                    """;
        }

        return $"""
                SET NOCOUNT ON;
                SELECT {topClause}
                  {frontPostColumns}
                FROM dbo.SingleContent sc
                ORDER BY sc.PublishTime DESC
                FOR JSON PATH;
                """;
    }

    public static string PostsBackQuery(string topClause, string pageClause)
    {
        if (!string.IsNullOrWhiteSpace(pageClause))
        {
            return $"""
                    SET NOCOUNT ON;
                    SELECT
                      contentId,
                      domainId,
                      contentTypeId,
                      statusId,
                      title,
                      overTitle,
                      underTitle,
                      excerpt,
                      body,
                      footer,
                      author,
                      publishTime,
                      contentTime,
                      expireTime,
                      imageUrl
                    FROM (
                      SELECT
                        ci.Id AS contentId,
                        ci.DomainId AS domainId,
                        ci.ContentTypeId AS contentTypeId,
                        ci.StatusId AS statusId,
                        ci.Title AS title,
                        cc.OverTitle AS overTitle,
                        cc.UnderTitle AS underTitle,
                        cc.ShortBody AS excerpt,
                        cc.BodyText AS body,
                        cc.Footer AS footer,
                        cc.Author AS author,
                        cc.PublishTime AS publishTime,
                        cc.ContentTime AS contentTime,
                        cc.ExpireTime AS expireTime,
                        cc.PublishTime AS publishSort,
                        {SqlPostImageUrlExpression()} AS imageUrl
                      FROM dbo.ContentInitialize ci
                      INNER JOIN dbo.ContentCommonInfo cc ON ci.Id = cc.ContentId
                      LEFT JOIN AsreKhodroFront.dbo.SingleContent sc ON sc.ContentId = ci.Id
                      LEFT JOIN AsreKhodroFront.dbo.MainLastContents mlc ON mlc.ContentId = ci.Id
                      WHERE ci.StatusId IN (1, 3)
                    ) AS ranked
                    {pageClause}
                    FOR JSON PATH;
                    """;
        }

        return $"""
                SET NOCOUNT ON;
                SELECT {topClause}
                  ci.Id AS contentId,
                  ci.DomainId AS domainId,
                  ci.ContentTypeId AS contentTypeId,
                  ci.StatusId AS statusId,
                  ci.Title AS title,
                  cc.OverTitle AS overTitle,
                  cc.UnderTitle AS underTitle,
                  cc.ShortBody AS excerpt,
                  cc.BodyText AS body,
                  cc.Footer AS footer,
                  cc.Author AS author,
                  cc.PublishTime AS publishTime,
                  cc.ContentTime AS contentTime,
                  cc.ExpireTime AS expireTime,
                  {SqlPostImageUrlExpression()} AS imageUrl
                FROM dbo.ContentInitialize ci
                INNER JOIN dbo.ContentCommonInfo cc ON ci.Id = cc.ContentId
                LEFT JOIN AsreKhodroFront.dbo.SingleContent sc ON sc.ContentId = ci.Id
                LEFT JOIN AsreKhodroFront.dbo.MainLastContents mlc ON mlc.ContentId = ci.Id
                WHERE ci.StatusId IN (1, 3)
                ORDER BY cc.PublishTime DESC
                FOR JSON PATH;
                """;
    }

    public static string PostsByContentIdsFrontQuery(string idList)
    {
        return $"""
                SET NOCOUNT ON;
                SELECT
                  sc.ContentId AS contentId,
                  sc.DomainId AS domainId,
                  CAST(NULL AS int) AS contentTypeId,
                  CAST(3 AS tinyint) AS statusId,
                  sc.Title AS title,
                  sc.OverTitle AS overTitle,
                  sc.UnderTitle AS underTitle,
                  sc.ShortBody AS excerpt,
                  sc.Body AS body,
                  sc.Footer AS footer,
                  sc.Author AS author,
                  sc.PublishTime AS publishTime,
                  sc.PublishTime AS contentTime,
                  CAST(NULL AS datetime) AS expireTime,
                  sc.MainImageId AS mainImageId,
                  NULLIF(LTRIM(RTRIM(sc.ImageURL)), '') AS imageUrl
                FROM dbo.SingleContent sc
                WHERE sc.ContentId IN ({idList})
                FOR JSON PATH;
                """;
    }

    public static string PostsByContentIdsBackQuery(string idList)
    {
        return $"""
                SET NOCOUNT ON;
                SELECT
                  ci.Id AS contentId,
                  ci.DomainId AS domainId,
                  ci.ContentTypeId AS contentTypeId,
                  ci.StatusId AS statusId,
                  ci.Title AS title,
                  cc.OverTitle AS overTitle,
                  cc.UnderTitle AS underTitle,
                  cc.ShortBody AS excerpt,
                  cc.BodyText AS body,
                  cc.Footer AS footer,
                  cc.Author AS author,
                  cc.PublishTime AS publishTime,
                  cc.ContentTime AS contentTime,
                  cc.ExpireTime AS expireTime,
                  {SqlPostImageUrlExpression()} AS imageUrl
                FROM dbo.ContentInitialize ci
                INNER JOIN dbo.ContentCommonInfo cc ON ci.Id = cc.ContentId
                LEFT JOIN AsreKhodroFront.dbo.SingleContent sc ON sc.ContentId = ci.Id
                LEFT JOIN AsreKhodroFront.dbo.MainLastContents mlc ON mlc.ContentId = ci.Id
                WHERE ci.Id IN ({idList})
                  AND ci.StatusId IN (1, 3)
                FOR JSON PATH;
                """;
    }

    public static string ContentFileMainImageByFileIdQuery(int fileId) =>
        $"""
        SET NOCOUNT ON;
        SELECT TOP 1 cf.URL AS url
        FROM dbo.ContentFiles cf
        WHERE cf.FileId = {fileId}
          AND ISNULL(cf.ImageDimensionId, 0) <> 1
          AND cf.URL IS NOT NULL
          AND LTRIM(RTRIM(cf.URL)) <> ''
        ORDER BY cf.ImageDimensionId DESC, cf.RowId DESC
        FOR JSON PATH;
        """;

    public static string ContentFilesImagesQuery(IEnumerable<int> contentIds)
    {
        var ids = contentIds.Where(id => id > 0).Distinct().ToArray();
        if (ids.Length == 0)
        {
            return string.Empty;
        }

        return $"""
                SET NOCOUNT ON;
                SELECT
                  cf.ContentId AS contentId,
                  cf.URL AS url,
                  cf.RowId AS rowId,
                  cf.ImageDimensionId AS imageDimensionId,
                  cf.IsMain AS isMain,
                  sc.MainImageId AS mainImageId
                FROM dbo.ContentFiles cf
                INNER JOIN dbo.SingleContent sc ON sc.ContentId = cf.ContentId
                WHERE cf.ContentId IN ({string.Join(",", ids)})
                  AND cf.URL IS NOT NULL
                  AND LTRIM(RTRIM(cf.URL)) <> ''
                FOR JSON PATH;
                """;
    }
}
