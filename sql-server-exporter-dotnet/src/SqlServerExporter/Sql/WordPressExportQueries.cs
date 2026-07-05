namespace SqlServerExporter.Sql;

internal static class WordPressExportQueries
{
    public static string CategoriesBackQuery => """
                                               SET NOCOUNT ON;
                                               SELECT
                                                 Id AS id,
                                                 DomainId AS domainId,
                                                 ParentId AS parentId,
                                                 Title AS title,
                                                 [Description] AS [description],
                                                 Periority AS priority,
                                                 StatusId AS statusId
                                               FROM dbo.Categories
                                               ORDER BY Id
                                               FOR JSON PATH;
                                               """;

    public static string CategoriesFrontQuery => """
                                                SET NOCOUNT ON;
                                                SELECT
                                                  Id AS id,
                                                  DomainId AS domainId,
                                                  ParentId AS parentId,
                                                  Title AS title,
                                                  [Description] AS [description],
                                                  Periority AS priority,
                                                  StatusId AS statusId
                                                FROM dbo.Categories
                                                ORDER BY Id
                                                FOR JSON PATH;
                                                """;

    public static string PostCategoriesBackBase(string? idList = null) => $"""
                                                                                             SET NOCOUNT ON;
                                                                                             SELECT
                                                                                               ContentId AS contentId,
                                                                                               CategoryId AS categoryId,
                                                                                               IsMain AS isMain
                                                                                             FROM dbo.ContentCategories
                                                                                             {(!string.IsNullOrWhiteSpace(idList) ? $"WHERE ContentId IN ({idList})" : string.Empty)}
                                                                                             FOR JSON PATH;
                                                                                             """;

    public static string PostCategoriesFrontBase(string? idList = null) => $"""
                                                                                              SET NOCOUNT ON;
                                                                                              SELECT
                                                                                                ContentId AS contentId,
                                                                                                CategoryId AS categoryId,
                                                                                                IsMain AS isMain
                                                                                              FROM dbo.ContentCategories
                                                                                              {(!string.IsNullOrWhiteSpace(idList) ? $"WHERE ContentId IN ({idList})" : string.Empty)}
                                                                                              FOR JSON PATH;
                                                                                              """;

    public static string TagsBackBase(string? idList = null) => $"""
                                                                                   SET NOCOUNT ON;
                                                                                   SELECT
                                                                                     kc.ContentId AS contentId,
                                                                                     kc.KeywordId AS keywordId,
                                                                                     k.Keyword AS tag
                                                                                   FROM dbo.KeywordsContent kc
                                                                                   INNER JOIN dbo.Keywords k ON kc.KeywordId = k.Id
                                                                                   {(!string.IsNullOrWhiteSpace(idList) ? $"WHERE kc.ContentId IN ({idList})" : string.Empty)}
                                                                                   FOR JSON PATH;
                                                                                   """;

    public static string TagsFrontBase(string? idList = null) => $"""
                                                                                    SET NOCOUNT ON;
                                                                                    SELECT
                                                                                      ContentId AS contentId,
                                                                                      KeywordId AS keywordId,
                                                                                      KeywordTitle AS tag
                                                                                    FROM dbo.KeywordsContent
                                                                                    {(!string.IsNullOrWhiteSpace(idList) ? $"WHERE ContentId IN ({idList})" : string.Empty)}
                                                                                    FOR JSON PATH;
                                                                                    """;

    public static string PostRelationsBackBase(string? idList = null)
    {
        var postRelationsFilter = !string.IsNullOrWhiteSpace(idList)
            ? $"AND ParentContentId IN ({idList}) AND ChildContentId IN ({idList})"
            : string.Empty;

        return PostRelationsBackQuery(postRelationsFilter);
    }

    public static string PostRelationsFrontBase(string? idList = null)
    {
        var postRelationsFilter = !string.IsNullOrWhiteSpace(idList)
            ? $"AND ParentContentId IN ({idList}) AND ChildContentId IN ({idList})"
            : string.Empty;

        return PostRelationsFrontQuery(postRelationsFilter);
    }

    public static string PostRelationsBackByParentIds(string idList) =>
        PostRelationsBackQuery($"AND ParentContentId IN ({idList})");

    public static string PostRelationsFrontByParentIds(string idList) =>
        PostRelationsFrontQuery($"AND ParentContentId IN ({idList})");

    private static string PostRelationsBackQuery(string extraFilter) => $"""
                SET NOCOUNT ON;
                SELECT
                  ParentContentId AS parentContentId,
                  ChildContentId AS childContentId
                FROM dbo.ContentRelation
                WHERE IsActive = 1
                {extraFilter}
                FOR JSON PATH;
                """;

    private static string PostRelationsFrontQuery(string extraFilter) => $"""
                SET NOCOUNT ON;
                SELECT
                  ParentContentId AS parentContentId,
                  ChildContentId AS childContentId
                FROM dbo.ContentsRelation
                WHERE IsActive = 1
                {extraFilter}
                FOR JSON PATH;
                """;

    public static string CommentsBackBase(string? idList = null) => $"""
                                                                                       SET NOCOUNT ON;
                                                                                       SELECT
                                                                                         ci.Id AS commentId,
                                                                                         ci.ObjectId AS contentId,
                                                                                         ci.ParentId AS parentId,
                                                                                         ci.UserId AS userId,
                                                                                         ci.StatusId AS statusId,
                                                                                         ci.CreateTime AS createdAt,
                                                                                         cc.Message AS content,
                                                                                         u.AliasName AS authorName,
                                                                                         u.Email AS authorEmail
                                                                                       FROM dbo.CommentInitialize ci
                                                                                       INNER JOIN dbo.CommentCommonInfo cc ON ci.Id = cc.CommentId
                                                                                       LEFT JOIN dbo.Users u ON ci.UserId = u.Id
                                                                                       {(!string.IsNullOrWhiteSpace(idList) ? $"WHERE ci.ObjectId IN ({idList})" : string.Empty)}
                                                                                       FOR JSON PATH;
                                                                                       """;

    public static string MenuPositions => """
                                               SET NOCOUNT ON;
                                               SELECT
                                                 Id AS id,
                                                 Name AS name,
                                                 Description AS [description]
                                               FROM dbo.MenuPosition
                                               ORDER BY Id
                                               FOR JSON PATH;
                                               """;

    public static string AdsBackBase => """
                                         SET NOCOUNT ON;
                                         SELECT
                                           a.Id AS id,
                                           a.DomainId AS domainId,
                                           a.MenuPositionId AS menuPositionId,
                                           mp.Name AS menuPositionName,
                                           a.Title AS title,
                                           a.LinkAddress AS link,
                                           a.HTML AS html,
                                           a.Width AS width,
                                           a.Height AS height,
                                           a.Periority AS priority,
                                           a.CreateTime AS createTime,
                                           CASE WHEN a.isActive = 1 THEN 1 ELSE 0 END AS isActive,
                                           COALESCE(
                                             (
                                               SELECT TOP 1 fft.Url
                                               FROM dbo.FilesFiletypes fft
                                               WHERE fft.FileId = a.FileId
                                                 AND fft.Url IS NOT NULL
                                                 AND LTRIM(RTRIM(fft.Url)) <> ''
                                               ORDER BY fft.Id DESC
                                             ),
                                             (
                                               SELECT TOP 1 fa.FileURL
                                               FROM AsreKhodroFront.dbo.Advertisements fa
                                               WHERE fa.Id = a.Id
                                                 AND fa.FileURL IS NOT NULL
                                                 AND LTRIM(RTRIM(fa.FileURL)) <> ''
                                             )
                                           ) AS imageUrl
                                         FROM dbo.Advertisments a
                                         INNER JOIN dbo.MenuPosition mp ON mp.Id = a.MenuPositionId
                                         WHERE a.isActive = 1
                                         FOR JSON PATH;
                                         """;

    public static string AdsFrontBase => """
                                          SET NOCOUNT ON;
                                          SELECT
                                            a.Id AS id,
                                            a.DomainId AS domainId,
                                            a.PositionId AS menuPositionId,
                                            CAST(a.PositionId AS nvarchar(50)) AS menuPositionName,
                                            a.Title AS title,
                                            a.Link AS link,
                                            CAST('' AS nvarchar(max)) AS html,
                                            a.Width AS width,
                                            a.Height AS height,
                                            a.Periority AS priority,
                                            a.CreateTime AS createTime,
                                            CASE WHEN a.isActive = 1 THEN 1 ELSE 0 END AS isActive,
                                            NULLIF(LTRIM(RTRIM(a.FileURL)), '') AS imageUrl
                                          FROM dbo.Advertisements a
                                          WHERE a.isActive = 1
                                          FOR JSON PATH;
                                          """;

    public static string VideosBackBase => """
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
                                              COALESCE(
                                                (
                                                  SELECT TOP 1 fft.Url
                                                  FROM AsreKhodroBack.dbo.ContentFiles bcf
                                                  INNER JOIN AsreKhodroBack.dbo.FilesFiletypes fft ON fft.FileId = bcf.FileId
                                                  WHERE bcf.ContentId = ci.Id
                                                    AND fft.Url IS NOT NULL
                                                    AND LTRIM(RTRIM(fft.Url)) <> ''
                                                    AND fft.Url LIKE '%/Uploaded/Video/%'
                                                  ORDER BY
                                                    CASE WHEN bcf.IsMain = 1 THEN 0 ELSE 1 END,
                                                    bcf.Periority,
                                                    fft.Id DESC
                                                ),
                                                NULL
                                              ) AS videoUrl,
                                              COALESCE(
                                                (
                                                  SELECT TOP 1 fft.Url
                                                  FROM AsreKhodroBack.dbo.ContentFiles bcf
                                                  INNER JOIN AsreKhodroBack.dbo.FilesFiletypes fft ON fft.FileId = bcf.FileId
                                                  LEFT JOIN AsreKhodroBack.dbo.ImageDimension idim ON idim.Id = fft.ImageDimensionId
                                                  WHERE bcf.ContentId = ci.Id
                                                    AND fft.Url IS NOT NULL
                                                    AND LTRIM(RTRIM(fft.Url)) <> ''
                                                    AND fft.Url NOT LIKE '%/Uploaded/Video/%'
                                                  ORDER BY
                                                    CASE WHEN sc.MainImageId IS NOT NULL AND bcf.FileId = sc.MainImageId THEN 0 ELSE 1 END,
                                                    CASE WHEN bcf.IsMain = 1 THEN 0 ELSE 1 END,
                                                    ISNULL(idim.Width, 0) DESC,
                                                    bcf.Periority,
                                                    fft.Id DESC
                                                ),
                                                (
                                                  SELECT TOP 1 cf.URL
                                                  FROM AsreKhodroFront.dbo.ContentFiles cf
                                                  LEFT JOIN AsreKhodroBack.dbo.ImageDimension idim ON idim.Id = cf.ImageDimensionId
                                                  WHERE cf.ContentId = ci.Id
                                                    AND cf.URL IS NOT NULL
                                                    AND LTRIM(RTRIM(cf.URL)) <> ''
                                                    AND cf.URL NOT LIKE '%/Uploaded/Video/%'
                                                  ORDER BY
                                                    CASE WHEN sc.MainImageId IS NOT NULL AND cf.FileId = sc.MainImageId THEN 0 ELSE 1 END,
                                                    CASE WHEN cf.IsMain = 1 THEN 0 ELSE 1 END,
                                                    ISNULL(idim.Width, 0) DESC,
                                                    cf.PeriorityInContent,
                                                    cf.RowId DESC
                                                ),
                                                (
                                                  SELECT TOP 1 fft.Url
                                                  FROM AsreKhodroBack.dbo.FilesFiletypes fft
                                                  LEFT JOIN AsreKhodroBack.dbo.ImageDimension idim ON idim.Id = fft.ImageDimensionId
                                                  WHERE sc.MainImageId IS NOT NULL
                                                    AND fft.FileId = sc.MainImageId
                                                    AND fft.Url IS NOT NULL
                                                    AND LTRIM(RTRIM(fft.Url)) <> ''
                                                    AND fft.Url NOT LIKE '%/Uploaded/Video/%'
                                                  ORDER BY ISNULL(idim.Width, 0) DESC, fft.Id DESC
                                                ),
                                                NULLIF(LTRIM(RTRIM(sc.ImageURL)), ''),
                                                NULLIF(LTRIM(RTRIM(mlc.ImageURL)), '')
                                              ) AS imageUrl
                                            FROM dbo.ContentInitialize ci
                                            INNER JOIN dbo.ContentCommonInfo cc ON ci.Id = cc.ContentId
                                            LEFT JOIN AsreKhodroFront.dbo.SingleContent sc ON sc.ContentId = ci.Id
                                            LEFT JOIN AsreKhodroFront.dbo.MainLastContents mlc ON mlc.ContentId = ci.Id
                                            WHERE ci.ContentTypeId = 16
                                              AND ci.StatusId IN (1, 3)
                                            FOR JSON PATH;
                                            """;

    public static string VideoCategoriesBackBase => """
                                                     SET NOCOUNT ON;
                                                     SELECT
                                                       cc.ContentId AS contentId,
                                                       cc.CategoryId AS categoryId,
                                                       cc.IsMain AS isMain
                                                     FROM dbo.ContentCategories cc
                                                     INNER JOIN dbo.ContentInitialize ci ON ci.Id = cc.ContentId
                                                     WHERE ci.ContentTypeId = 16
                                                       AND ci.StatusId IN (1, 3)
                                                     FOR JSON PATH;
                                                     """;

    public static string ReviewsBackBase(int? reviewLimit = null) => $"""
                                                                                SET NOCOUNT ON;
                                                                                SELECT {(reviewLimit.HasValue && reviewLimit.Value > 0 ? SqlFragments.SqlTop(reviewLimit.Value) : string.Empty)}
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
                                                                                  COALESCE(
                                                                                    (
                                                                                      SELECT TOP 1 fft.Url
                                                                                      FROM AsreKhodroBack.dbo.ContentFiles bcf
                                                                                      INNER JOIN AsreKhodroBack.dbo.FilesFiletypes fft ON fft.FileId = bcf.FileId
                                                                                      LEFT JOIN AsreKhodroBack.dbo.ImageDimension idim ON idim.Id = fft.ImageDimensionId
                                                                                      WHERE bcf.ContentId = ci.Id
                                                                                        AND fft.Url IS NOT NULL
                                                                                        AND LTRIM(RTRIM(fft.Url)) <> ''
                                                                                        AND fft.Url NOT LIKE '%/Uploaded/Video/%'
                                                                                      ORDER BY
                                                                                        CASE WHEN sc.MainImageId IS NOT NULL AND bcf.FileId = sc.MainImageId THEN 0 ELSE 1 END,
                                                                                        CASE WHEN bcf.IsMain = 1 THEN 0 ELSE 1 END,
                                                                                        ISNULL(idim.Width, 0) DESC,
                                                                                        bcf.Periority,
                                                                                        fft.Id DESC
                                                                                    ),
                                                                                    (
                                                                                      SELECT TOP 1 cf.URL
                                                                                      FROM AsreKhodroFront.dbo.ContentFiles cf
                                                                                      LEFT JOIN AsreKhodroBack.dbo.ImageDimension idim ON idim.Id = cf.ImageDimensionId
                                                                                      WHERE cf.ContentId = ci.Id
                                                                                        AND cf.URL IS NOT NULL
                                                                                        AND LTRIM(RTRIM(cf.URL)) <> ''
                                                                                        AND cf.URL NOT LIKE '%/Uploaded/Video/%'
                                                                                      ORDER BY
                                                                                        CASE WHEN sc.MainImageId IS NOT NULL AND cf.FileId = sc.MainImageId THEN 0 ELSE 1 END,
                                                                                        CASE WHEN cf.IsMain = 1 THEN 0 ELSE 1 END,
                                                                                        ISNULL(idim.Width, 0) DESC,
                                                                                        cf.PeriorityInContent,
                                                                                        cf.RowId DESC
                                                                                    ),
                                                                                    (
                                                                                      SELECT TOP 1 fft.Url
                                                                                      FROM AsreKhodroBack.dbo.FilesFiletypes fft
                                                                                      LEFT JOIN AsreKhodroBack.dbo.ImageDimension idim ON idim.Id = fft.ImageDimensionId
                                                                                      WHERE sc.MainImageId IS NOT NULL
                                                                                        AND fft.FileId = sc.MainImageId
                                                                                        AND fft.Url IS NOT NULL
                                                                                        AND LTRIM(RTRIM(fft.Url)) <> ''
                                                                                        AND fft.Url NOT LIKE '%/Uploaded/Video/%'
                                                                                      ORDER BY ISNULL(idim.Width, 0) DESC, fft.Id DESC
                                                                                    ),
                                                                                    NULLIF(LTRIM(RTRIM(sc.ImageURL)), ''),
                                                                                    NULLIF(LTRIM(RTRIM(mlc.ImageURL)), '')
                                                                                  ) AS imageUrl
                                                                                FROM dbo.ContentInitialize ci
                                                                                INNER JOIN dbo.ContentCommonInfo cc ON ci.Id = cc.ContentId
                                                                                LEFT JOIN AsreKhodroFront.dbo.SingleContent sc ON sc.ContentId = ci.Id
                                                                                LEFT JOIN AsreKhodroFront.dbo.MainLastContents mlc ON mlc.ContentId = ci.Id
                                                                                WHERE ci.ContentTypeId = 8
                                                                                  AND ci.StatusId IN (1, 3)
                                                                                FOR JSON PATH;
                                                                                """;

    public static string ReviewCategoriesBackBase => """
                                                      SET NOCOUNT ON;
                                                      SELECT
                                                        cc.ContentId AS contentId,
                                                        cc.CategoryId AS categoryId,
                                                        cc.IsMain AS isMain
                                                      FROM dbo.ContentCategories cc
                                                      INNER JOIN dbo.ContentInitialize ci ON ci.Id = cc.ContentId
                                                      WHERE ci.ContentTypeId = 8
                                                        AND ci.StatusId IN (1, 3)
                                                      FOR JSON PATH;
                                                      """;

    public static string MagazinesBackBase(int? magazineLimit = null) => $"""
                                                                                    SET NOCOUNT ON;
                                                                                    SELECT {(magazineLimit.HasValue && magazineLimit.Value > 0 ? SqlFragments.SqlTop(magazineLimit.Value) : string.Empty)}
                                                                                      fi.Id AS fileId,
                                                                                      fi.DomainId AS domainId,
                                                                                      fi.StatusId AS statusId,
                                                                                      fi.Title AS title,
                                                                                      fi.Periority AS priority,
                                                                                      {ExportConstants.KioskCategoryId} AS categoryId,
                                                                                      fpi.CreateTime AS publishTime,
                                                                                      fci.Description AS description,
                                                                                      COALESCE(
                                                                                        (
                                                                                          SELECT TOP 1 fft.Url
                                                                                          FROM dbo.FilesFiletypes fft
                                                                                          WHERE fft.FileId = fi.Id
                                                                                            AND fft.Url IS NOT NULL
                                                                                            AND LTRIM(RTRIM(fft.Url)) <> ''
                                                                                          ORDER BY fft.Id DESC
                                                                                        ),
                                                                                        NULL
                                                                                      ) AS imageUrl
                                                                                    FROM dbo.FileCategories fcat
                                                                                    INNER JOIN dbo.FileInitialize fi ON fi.Id = fcat.FileId
                                                                                    LEFT JOIN dbo.FilePrivateInfo fpi ON fpi.FileId = fi.Id
                                                                                    LEFT JOIN dbo.FileCommonInfo fci ON fci.FileId = fi.Id
                                                                                    WHERE fcat.CategoryId = {ExportConstants.KioskCategoryId}
                                                                                      AND fi.StatusId IN (1, 3)
                                                                                    FOR JSON PATH;
                                                                                    """;
}
