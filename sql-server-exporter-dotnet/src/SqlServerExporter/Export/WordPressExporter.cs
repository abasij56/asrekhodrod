using System.Text.Json.Nodes;
using SqlServerExporter.Cli;
using SqlServerExporter.Config;
using SqlServerExporter.Json;
using SqlServerExporter.Services;
using SqlServerExporter.Sql;

namespace SqlServerExporter.Export;

internal sealed class WordPressExporter(ExportOptions options, ResolvedConnection connection)
{
    public int Run()
    {
        try
        {
            ExportStageLog.Phase("initialize exporter...");
            var sourceMode = string.IsNullOrWhiteSpace(options.Source) ? "auto" : options.Source.Trim().ToLowerInvariant();
            var sql = new SqlJsonQueryService(connection.Profile);
            var runner = new BackOrFrontRunner(sql, sourceMode);
            ExportStageLog.Phase($"load progress from {options.Output}...");
            var progressService = new ExportProgressService(options.Output);
            var exportProgress = progressService.ClearEmptyBackOnlyProgress(progressService.Load(), sourceMode);

            if (options.EnrichImagesOnly)
            {
                return RunEnrichImagesOnly(sql, exportProgress, progressService, sourceMode);
            }

            return RunFullExport(sql, runner, exportProgress, progressService, sourceMode);
        }
        catch (Exception ex)
        {
            Console.Error.WriteLine(ex.Message);
            return 1;
        }
    }

    private int RunEnrichImagesOnly(
        SqlJsonQueryService sql,
        JsonObject exportProgress,
        ExportProgressService progressService,
        string sourceMode)
    {
        Console.WriteLine(
            $"Enriching post images from ContentFiles (profile={connection.Name}, server={connection.Profile.Server}, source={sourceMode}, resume={options.Resume}) → {options.Output}");

        var chunkFiles = ChunkFileService.ListChunkFiles(options.Output, "posts");
        var postsPath = Path.Combine(options.Output, "posts.json");
        var postsFromFile = chunkFiles.Count > 0 || !File.Exists(postsPath)
            ? []
            : JsonRowHelper.ReadRowsFile(postsPath);
        var postCount = chunkFiles.Count > 0
            ? JsonRowHelper.CountRowsInFiles(chunkFiles)
            : postsFromFile.Count;

        if (postCount == 0)
        {
            Console.Error.WriteLine("No post JSON found. Export posts first.");
            return 1;
        }

        var postExport = new PostExportResult(
            postsFromFile,
            chunkFiles.Count > 0,
            postCount,
            chunkFiles.Count > 0 ? chunkFiles.Count : 1,
            postsFromFile.Count(row => HasValue(row, "imageUrl")));

        var imageResult = PostImageEnricher.EnrichExportedPostImages(
            sql,
            sourceMode,
            false,
            options.Output,
            postExport,
            options.Resume,
            exportProgress,
            progressService);

        Console.WriteLine(
            $"Done: {imageResult.Updated} image upgrades, {imageResult.PostsWithImageUrl} posts with imageUrl");
        return 0;
    }

    private int RunFullExport(
        SqlJsonQueryService sql,
        BackOrFrontRunner runner,
        JsonObject exportProgress,
        ExportProgressService progressService,
        string sourceMode)
    {
        var postExportSettings = PostExportSettings.FromOptions(options);
        var fullExport = postExportSettings.IsWindowed
            ? postExportSettings.Continue
            : options.Limit <= 0;
        var exportMode = postExportSettings.IsWindowed
            ? $"windowed(start={postExportSettings.Start}{(postExportSettings.End is int end ? $", end={end}" : string.Empty)}, window={postExportSettings.Window}, file-chunk={postExportSettings.FileChunk}, continue={(postExportSettings.Continue ? 1 : 0)})"
            : fullExport ? "all" : "sample";
        Console.WriteLine(
            $"Exporting WordPress data (profile={connection.Name}, server={connection.Profile.Server}, source={sourceMode}, mode={exportMode}, resume={options.Resume}, limit={(fullExport ? "all" : options.Limit)}, review-limit={(options.ReviewLimit <= 0 ? "all" : options.ReviewLimit)}, magazine-limit={(options.MagazineLimit <= 0 ? "all" : options.MagazineLimit)}) → {options.Output}");
        Console.Out.Flush();
        ExportStageLog.Phase("connecting to SQL Server (first query)...");

        if (!options.Resume)
        {
            exportProgress.Remove("postContentFileImages");
            WriteProgressFile(options.Output, exportProgress);
        }

        var categoriesPath = Path.Combine(options.Output, "categories.json");
        List<JsonObject> categories;
        if (options.Resume && File.Exists(categoriesPath))
        {
            categories = JsonRowHelper.ReadRowsFile(categoriesPath);
            Console.WriteLine($"  → categories (resumed from disk: {categories.Count})");
        }
        else
        {
            ExportStageLog.Phase("query categories...");
            categories = runner.RunBackOrFront(
                "categories",
                WordPressExportQueries.CategoriesBackQuery,
                WordPressExportQueries.CategoriesFrontQuery);
            JsonRowHelper.WriteRowsFile(options.Output, "categories.json", categories);
            progressService.Save("categories", new JsonObject
            {
                ["complete"] = true,
                ["rows"] = categories.Count,
            });
        }

        ExportStageLog.Phase("export front homepage sections...");
        var frontSectionsExport = FrontHomeSectionsExporter.Export(sql, options.Output);

        ExportStageLog.Phase("export posts...");
        var postExport = PostExporter.Export(
            runner,
            sql,
            sourceMode,
            options.Limit,
            postExportSettings,
            options.Output,
            options.Resume,
            exportProgress,
            progressService,
            !options.SkipContentFileImages);
        var posts = postExport.Posts;
        var postChunked = postExport.Chunked;
        var postCount = postExport.PostCount;
        var postFiles = postExport.PostFiles;
        var postsWithImageUrl = postExport.PostsWithImageUrl;

        if (!postChunked && options.Limit > 0 && frontSectionsExport.ContentIds.Count > 0)
        {
            posts = PostExporter.MergeRequiredPosts(posts, frontSectionsExport.ContentIds, runner);
            postCount = posts.Count;
            postsWithImageUrl = posts.Count(row => HasValue(row, "imageUrl"));
            JsonRowHelper.WriteRowsFile(options.Output, "posts.json", posts);
        }

        if (postCount == 0)
        {
            Console.Error.WriteLine("No posts exported. Check SQL Server connection and data.");
            return 1;
        }

        List<int>? scopedContentIds = null;
        string? idList = null;
        if (!fullExport)
        {
            ExportStageLog.Phase(
                postChunked
                    ? $"collect content IDs from {postFiles} post chunk(s)..."
                    : "collect content IDs from posts...");
            scopedContentIds = postExportSettings.IsWindowed && postChunked
                ? PostExporter.CollectContentIdsFromChunks(options.Output)
                : posts
                    .Select(row => JsonRowHelper.GetInt(row, "contentId"))
                    .Where(id => id is > 0)
                    .Select(id => id!.Value)
                    .ToList();
            if (scopedContentIds.Count <= ExportConstants.ContentIdInClauseBatchSize)
            {
                idList = string.Join(",", scopedContentIds);
            }

            ExportStageLog.Phase($"scoped export: {scopedContentIds.Count} content ID(s)");
        }

        ExportStageLog.Phase("query post-categories...");
        var postCategoriesSample = fullExport
            ? []
            : ScopedContentQueryRunner.RunSingleContentIdColumn(
                runner,
                "post-categories",
                scopedContentIds!,
                WordPressExportQueries.PostCategoriesBackBase,
                WordPressExportQueries.PostCategoriesFrontBase);

        ExportStageLog.Phase("write post-categories...");
        var postCategoryExport = BatchedCollectionExporter.Export(
            options.Output,
            "post-categories",
            "post-categories",
            fullExport,
            postCategoriesSample,
            "post-categories.json",
            (offset, size) => runner.FetchPagedBackOrFront(
                "post-categories",
                WordPressExportQueries.PostCategoriesBackBase(idList),
                WordPressExportQueries.PostCategoriesFrontBase(idList),
                offset,
                size,
                "ContentId, CategoryId"),
            options.Resume,
            exportProgress,
            progressService,
            trackUnique: true);

        ExportStageLog.Phase("query tags...");
        var tagsSample = fullExport
            ? []
            : ScopedContentQueryRunner.RunSingleContentIdColumn(
                runner,
                "tags",
                scopedContentIds!,
                WordPressExportQueries.TagsBackBase,
                WordPressExportQueries.TagsFrontBase);

        ExportStageLog.Phase("write tags...");
        var tagExport = BatchedCollectionExporter.Export(
            options.Output,
            "tags",
            "tags",
            fullExport,
            tagsSample,
            "tags.json",
            (offset, size) => runner.FetchPagedBackOrFront(
                "tags",
                WordPressExportQueries.TagsBackBase(idList),
                WordPressExportQueries.TagsFrontBase(idList),
                offset,
                size,
                "ContentId, KeywordId"),
            options.Resume,
            exportProgress,
            progressService,
            trackUnique: true);

        ExportStageLog.Phase("query post-relations...");
        var postRelationsSample = fullExport
            ? []
            : ScopedContentQueryRunner.RunPostRelations(runner, "post-relations", scopedContentIds!);

        ExportStageLog.Phase("write post-relations...");
        var postRelationExport = BatchedCollectionExporter.Export(
            options.Output,
            "post-relations",
            "post-relations",
            fullExport,
            postRelationsSample,
            "post-relations.json",
            (offset, size) => runner.FetchPagedBackOrFront(
                "post-relations",
                WordPressExportQueries.PostRelationsBackBase(idList),
                WordPressExportQueries.PostRelationsFrontBase(idList),
                offset,
                size,
                "ParentContentId, ChildContentId"),
            options.Resume,
            exportProgress,
            progressService,
            trackUnique: true,
            trackUniqueField: "parentContentId");

        ExportStageLog.Phase("query comments...");
        var commentsSample = fullExport
            ? []
            : ScopedContentQueryRunner.RunBackOnlySingleContentIdColumn(
                runner,
                "comments",
                scopedContentIds!,
                WordPressExportQueries.CommentsBackBase,
                ExportConstants.DatabaseComments);

        var commentExport = BatchedCollectionExporter.Export(
            options.Output,
            "comments",
            "comments",
            fullExport,
            commentsSample,
            "comments.json",
            (offset, size) => runner.FetchPagedBackOnly(
                "comments",
                WordPressExportQueries.CommentsBackBase(idList),
                ExportConstants.DatabaseComments,
                offset,
                size,
                "ci.Id"),
            options.Resume,
            exportProgress,
            progressService);

        var menuPositions = runner.RunBackOnly("menu-positions", WordPressExportQueries.MenuPositions);

        var adsSample = fullExport
            ? []
            : runner.RunBackOrFront(
                "ads",
                AddOrderByBeforeForJson(WordPressExportQueries.AdsBackBase, "a.MenuPositionId, a.Periority, a.Id"),
                AddOrderByBeforeForJson(WordPressExportQueries.AdsFrontBase, "a.PositionId, a.Periority, a.Id"));

        var adExport = BatchedCollectionExporter.Export(
            options.Output,
            "ads",
            "ads",
            fullExport,
            adsSample,
            "ads.json",
            (offset, size) => runner.FetchPagedBackOrFront(
                "ads",
                WordPressExportQueries.AdsBackBase,
                WordPressExportQueries.AdsFrontBase,
                offset,
                size,
                "a.MenuPositionId, a.Periority, a.Id",
                "a.PositionId, a.Periority, a.Id"),
            options.Resume,
            exportProgress,
            progressService);

        var videosBase = WordPressExportQueries.VideosBackBase;
        var videosSample = fullExport
            ? []
            : runner.RunBackOnly("videos", AddOrderByBeforeForJson(videosBase, "cc.PublishTime DESC, ci.Id DESC"));

        var videoExport = BatchedCollectionExporter.Export(
            options.Output,
            "videos",
            "videos",
            fullExport,
            videosSample,
            "videos.json",
            (offset, size) => runner.FetchPagedBackOnly(
                "videos",
                videosBase,
                ExportConstants.DatabaseBack,
                offset,
                size,
                "cc.PublishTime DESC, ci.Id DESC"),
            options.Resume,
            exportProgress,
            progressService);

        var videoCategoriesBase = WordPressExportQueries.VideoCategoriesBackBase;
        var videoCategoriesSample = fullExport
            ? []
            : runner.RunBackOnly("video-categories", videoCategoriesBase);

        var videoCategoryExport = BatchedCollectionExporter.Export(
            options.Output,
            "video-categories",
            "video-categories",
            fullExport,
            videoCategoriesSample,
            "video-categories.json",
            (offset, size) => runner.FetchPagedBackOnly(
                "video-categories",
                videoCategoriesBase,
                ExportConstants.DatabaseBack,
                offset,
                size,
                "cc.ContentId, cc.CategoryId"),
            options.Resume,
            exportProgress,
            progressService,
            trackUnique: true);

        var reviewsBase = WordPressExportQueries.ReviewsBackBase(fullExport ? null : options.ReviewLimit);
        var reviewsSample = fullExport
            ? []
            : runner.RunBackOnly("reviews", AddOrderByBeforeForJson(reviewsBase, "cc.PublishTime DESC, ci.Id DESC"));

        var reviewExport = BatchedCollectionExporter.Export(
            options.Output,
            "reviews",
            "reviews",
            fullExport,
            reviewsSample,
            "reviews.json",
            (offset, size) => runner.FetchPagedBackOnly(
                "reviews",
                reviewsBase,
                ExportConstants.DatabaseBack,
                offset,
                size,
                "cc.PublishTime DESC, ci.Id DESC"),
            options.Resume,
            exportProgress,
            progressService);

        var reviewCategoriesBase = WordPressExportQueries.ReviewCategoriesBackBase;
        var reviewCategoriesSample = fullExport
            ? []
            : runner.RunBackOnly("review-categories", reviewCategoriesBase);

        var reviewCategoryExport = BatchedCollectionExporter.Export(
            options.Output,
            "review-categories",
            "review-categories",
            fullExport,
            reviewCategoriesSample,
            "review-categories.json",
            (offset, size) => runner.FetchPagedBackOnly(
                "review-categories",
                reviewCategoriesBase,
                ExportConstants.DatabaseBack,
                offset,
                size,
                "cc.ContentId, cc.CategoryId"),
            options.Resume,
            exportProgress,
            progressService);

        var magazinesBase = WordPressExportQueries.MagazinesBackBase(fullExport ? null : options.MagazineLimit);
        var magazinesSample = fullExport
            ? []
            : runner.RunBackOnly("magazines", AddOrderByBeforeForJson(magazinesBase, "fpi.CreateTime DESC, fi.Id DESC"));

        var magazineExport = BatchedCollectionExporter.Export(
            options.Output,
            "magazines",
            "magazines",
            fullExport,
            magazinesSample,
            "magazines.json",
            (offset, size) => runner.FetchPagedBackOnly(
                "magazines",
                magazinesBase,
                ExportConstants.DatabaseBack,
                offset,
                size,
                "fpi.CreateTime DESC, fi.Id DESC"),
            options.Resume,
            exportProgress,
            progressService);

        var postCategories = postCategoryExport.Chunked ? [] : postCategoriesSample;
        var tags = tagExport.Chunked ? [] : tagsSample;
        var postRelations = postRelationExport.Chunked ? [] : postRelationsSample;
        var comments = commentExport.Chunked ? [] : commentsSample;
        var ads = adExport.Chunked ? [] : adsSample;
        var videos = videoExport.Chunked ? [] : videosSample;
        var videoCategories = videoCategoryExport.Chunked ? [] : videoCategoriesSample;
        var reviews = reviewExport.Chunked ? [] : reviewsSample;
        var reviewCategories = reviewCategoryExport.Chunked ? [] : reviewCategoriesSample;
        var magazines = magazineExport.Chunked ? [] : magazinesSample;

        var frontSectionRefs = frontSectionsExport.Exported.Values.Sum();
        var manifest = BuildManifest(
            sourceMode,
            fullExport,
            postExportSettings,
            postCount,
            postFiles,
            postsWithImageUrl,
            categories,
            frontSectionsExport,
            postCategoryExport,
            tagExport,
            postRelationExport,
            commentExport,
            adExport,
            videoExport,
            videoCategoryExport,
            reviewExport,
            reviewCategoryExport,
            magazineExport,
            postCategories,
            tags,
            postRelations,
            ads,
            videos,
            videoCategories,
            reviews,
            magazines,
            frontSectionRefs);

        ExportStageLog.Phase("write manifest.json...");
        JsonRowHelper.WriteJsonFile(options.Output, "manifest.json", manifest);
        if (!postChunked)
        {
            JsonRowHelper.WriteRowsFile(options.Output, "posts.json", posts);
        }

        JsonRowHelper.WriteRowsFile(options.Output, "menu-positions.json", menuPositions);

        Console.WriteLine("Export complete:");
        Console.WriteLine($"  categories:       {categories.Count}");
        Console.WriteLine($"  posts:            {postCount}{(postChunked ? $" ({postFiles} chunks)" : string.Empty)}");
        Console.WriteLine($"  posts w/ image:   {postsWithImageUrl} / {postCount}");
        Console.WriteLine(
            $"  post-categories:  {postCategoryExport.Rows}{(postCategoryExport.Chunked ? $" ({postCategoryExport.Files} chunks)" : string.Empty)}");
        Console.WriteLine($"  tags:             {tagExport.Rows}{(tagExport.Chunked ? $" ({tagExport.Files} chunks)" : string.Empty)}");
        Console.WriteLine(
            $"  post-relations:   {postRelationExport.Rows}{(postRelationExport.Chunked ? $" ({postRelationExport.Files} chunks)" : string.Empty)}");
        Console.WriteLine(
            $"  front-sections:   {frontSectionRefs} refs ({frontSectionsExport.ContentIds.Count} unique posts)");
        Console.WriteLine($"  videos:           {videoExport.Rows}{(videoExport.Chunked ? $" ({videoExport.Files} chunks)" : string.Empty)}");
        Console.WriteLine(
            $"  video-categories: {videoCategoryExport.Rows}{(videoCategoryExport.Chunked ? $" ({videoCategoryExport.Files} chunks)" : string.Empty)}");
        Console.WriteLine($"  reviews:          {reviewExport.Rows}{(reviewExport.Chunked ? $" ({reviewExport.Files} chunks)" : string.Empty)}");
        Console.WriteLine(
            $"  review-categories:{reviewCategoryExport.Rows}{(reviewCategoryExport.Chunked ? $" ({reviewCategoryExport.Files} chunks)" : string.Empty)}");
        Console.WriteLine(
            $"  magazines:        {magazineExport.Rows}{(magazineExport.Chunked ? $" ({magazineExport.Files} chunks)" : string.Empty)}");
        Console.WriteLine(
            $"  comments:         {commentExport.Rows}{(commentExport.Chunked ? $" ({commentExport.Files} chunks)" : string.Empty)}");
        Console.WriteLine($"  ads:              {adExport.Rows}{(adExport.Chunked ? $" ({adExport.Files} chunks)" : string.Empty)}");
        Console.WriteLine();
        Console.WriteLine($"Import folder: {options.Output}");
        Console.WriteLine("Next: WP Admin -> Tools -> AsreKhodro Import -> Run Import");

        return 0;
    }

    private JsonObject BuildManifest(
        string sourceMode,
        bool fullExport,
        PostExportSettings postExportSettings,
        int postCount,
        int postFiles,
        int postsWithImageUrl,
        IReadOnlyCollection<JsonObject> categories,
        FrontSectionsExportResult frontSectionsExport,
        CollectionExportResult postCategoryExport,
        CollectionExportResult tagExport,
        CollectionExportResult postRelationExport,
        CollectionExportResult commentExport,
        CollectionExportResult adExport,
        CollectionExportResult videoExport,
        CollectionExportResult videoCategoryExport,
        CollectionExportResult reviewExport,
        CollectionExportResult reviewCategoryExport,
        CollectionExportResult magazineExport,
        IReadOnlyCollection<JsonObject> postCategories,
        IReadOnlyCollection<JsonObject> tags,
        IReadOnlyCollection<JsonObject> postRelations,
        IReadOnlyCollection<JsonObject> ads,
        IReadOnlyCollection<JsonObject> videos,
        IReadOnlyCollection<JsonObject> videoCategories,
        IReadOnlyCollection<JsonObject> reviews,
        IReadOnlyCollection<JsonObject> magazines,
        int frontSectionRefs)
    {
        var manifest = new JsonObject
        {
            ["exportedAt"] = DateTime.UtcNow.ToString("o"),
            ["format"] = postExportSettings.IsWindowed ? "windowed" : fullExport ? "chunked" : "legacy",
            ["chunkSize"] = postExportSettings.IsWindowed ? postExportSettings.FileChunk : ExportConstants.ChunkSize,
            ["connectionProfile"] = connection.Name,
            ["sourceMode"] = sourceMode,
            ["limit"] = options.Limit,
            ["postExport"] = postExportSettings.IsWindowed
                ? new JsonObject
                {
                    ["start"] = postExportSettings.Start,
                    ["end"] = postExportSettings.End,
                    ["window"] = postExportSettings.Window,
                    ["fileChunk"] = postExportSettings.FileChunk,
                    ["skipBatch"] = postExportSettings.SkipBatch,
                    ["continue"] = postExportSettings.Continue,
                }
                : null,
            ["source"] = new JsonObject
            {
                ["mode"] = sourceMode switch
                {
                    "auto" => "Try AsreKhodroBack first; fall back to AsreKhodroFront on Msg 823",
                    "front" => "AsreKhodroFront only (published content)",
                    _ => "AsreKhodroBack only",
                },
                ["posts"] = sourceMode == "front"
                    ? "AsreKhodroFront.SingleContent"
                    : "AsreKhodroBack (ContentInitialize + ContentCommonInfo) with Front fallback",
                ["categories"] = "AsreKhodroBack.Categories",
                ["postCategories"] = "AsreKhodroBack.ContentCategories",
                ["tags"] = "AsreKhodroBack.KeywordsContent",
                ["postRelations"] = "AsreKhodroFront.ContentsRelation (Back: ContentRelation) where IsActive = 1",
                ["frontSections"] = "AsreKhodroFront homepage caches as contentId references; full posts merged into posts.json",
                ["comments"] = "AsreKhodroComments",
                ["ads"] = "AsreKhodroBack.Advertisments (active) + FilesFiletypes / Front.Advertisements",
                ["videos"] = "AsreKhodroBack.ContentInitialize where ContentTypeId = 16 (Video)",
                ["videoCategories"] = "AsreKhodroBack.ContentCategories for video content",
                ["reviews"] = "AsreKhodroBack.ContentInitialize where ContentTypeId = 8 (Photo report / car reviews)",
                ["reviewCategories"] = "AsreKhodroBack.ContentCategories for exported reviews",
                ["magazines"] = $"AsreKhodroBack.FileInitialize in category {ExportConstants.KioskCategoryId} (Kiosk)",
                ["imageUrl"] = "SingleContent.MainImageId -> AsreKhodroFront.ContentFiles.RowId, then SingleContent.ImageURL / MainLastContents.ImageURL fallback",
            },
            ["counts"] = new JsonObject
            {
                ["categories"] = categories.Count,
                ["posts"] = postCount,
                ["postsWithImageUrl"] = postsWithImageUrl,
                ["postCategories"] = postCategoryExport.Rows,
                ["postCategoryPosts"] = postCategoryExport.UniqueContentIds ?? postCategories.Count,
                ["tags"] = tagExport.Rows,
                ["tagPosts"] = tagExport.UniqueContentIds ?? tags.Count,
                ["postRelations"] = postRelationExport.Rows,
                ["postRelationPosts"] = postRelationExport.UniqueContentIds
                    ?? postRelations.Select(row => JsonRowHelper.GetInt(row, "parentContentId") ?? 0).Where(id => id > 0).Distinct().Count(),
                ["frontSections"] = frontSectionRefs,
                ["frontSectionPosts"] = frontSectionsExport.ContentIds.Count,
                ["comments"] = commentExport.Rows,
                ["ads"] = adExport.Rows,
                ["adsWithImageUrl"] = fullExport ? null : ads.Count(row => HasValue(row, "imageUrl")),
                ["videos"] = videoExport.Rows,
                ["videosWithVideoUrl"] = fullExport ? null : videos.Count(row => HasValue(row, "videoUrl")),
                ["videosWithImageUrl"] = fullExport ? null : videos.Count(row => HasValue(row, "imageUrl")),
                ["videoCategories"] = videoCategoryExport.Rows,
                ["videoCategoryPosts"] = videoCategoryExport.UniqueContentIds ?? videoCategories.Count,
                ["reviews"] = reviewExport.Rows,
                ["reviewsWithImageUrl"] = fullExport ? null : reviews.Count(row => HasValue(row, "imageUrl")),
                ["reviewCategories"] = reviewCategoryExport.Rows,
                ["magazines"] = magazineExport.Rows,
                ["magazinesWithImageUrl"] = fullExport ? null : magazines.Count(row => HasValue(row, "imageUrl")),
            },
            ["statusMap"] = new JsonObject
            {
                ["0"] = "draft",
                ["1"] = "publish",
                ["3"] = "publish",
                ["4"] = "skip",
                ["5"] = "draft",
            },
        };

        if (fullExport || postExportSettings.IsWindowed)
        {
            manifest["chunks"] = new JsonObject
            {
                ["posts"] = new JsonObject
                {
                    ["files"] = postFiles,
                    ["rows"] = postCount,
                },
                ["post-categories"] = BuildChunkManifest(postCategoryExport),
                ["tags"] = BuildChunkManifest(tagExport),
                ["post-relations"] = BuildChunkManifest(postRelationExport),
                ["comments"] = BuildChunkManifest(commentExport),
                ["ads"] = BuildChunkManifest(adExport),
                ["videos"] = BuildChunkManifest(videoExport),
                ["video-categories"] = BuildChunkManifest(videoCategoryExport),
                ["reviews"] = BuildChunkManifest(reviewExport),
                ["review-categories"] = BuildChunkManifest(reviewCategoryExport),
                ["magazines"] = BuildChunkManifest(magazineExport),
            };
        }

        return manifest;
    }

    private static JsonObject BuildChunkManifest(CollectionExportResult exportResult)
    {
        var node = new JsonObject
        {
            ["files"] = exportResult.Files,
            ["rows"] = exportResult.Rows,
        };

        if (exportResult.UniqueContentIds is not null)
        {
            node["uniqueContentIds"] = exportResult.UniqueContentIds.Value;
        }

        return node;
    }

    private static bool HasValue(JsonObject row, string key) =>
        !string.IsNullOrWhiteSpace(JsonRowHelper.GetString(row, key));

    private static void WriteProgressFile(string outputDir, JsonObject progress)
    {
        Directory.CreateDirectory(outputDir);
        var path = Path.Combine(outputDir, ".export-progress.json");
        File.WriteAllText(path, progress.ToJsonString(JsonOptions.WriteIndented) + Environment.NewLine);
    }

    private static string AddOrderByBeforeForJson(string baseQuery, string orderBy)
    {
        var trimmed = baseQuery.Trim();
        return System.Text.RegularExpressions.Regex.Replace(
            trimmed,
            @"FOR JSON PATH;\s*$",
            $"ORDER BY {orderBy}{Environment.NewLine}FOR JSON PATH;",
            System.Text.RegularExpressions.RegexOptions.IgnoreCase);
    }
}
