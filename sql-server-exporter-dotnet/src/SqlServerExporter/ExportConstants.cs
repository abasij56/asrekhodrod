namespace SqlServerExporter;

internal static class ExportConstants
{
    public const int KioskCategoryId = 43;
    public const int ChunkSize = 5000;
    public const int MinPostBatchSize = 1;
    public const int ContentFilesIdBatch = 150;
    public const int ContentFilesMinBatch = 10;
    /** Max ContentIds per IN (...) clause for scoped relation/category/tag queries. */
    public const int ContentIdInClauseBatchSize = 500;
    public const int ImageLookupProgressInterval = 100;
    public const int ContentFilesEnrichVersion = 4;
    public const int SqlRetries = 4;
    public const int SqlConnectTimeoutSeconds = 45;

    public const string DatabaseBack = "AsreKhodroBack";
    public const string DatabaseFront = "AsreKhodroFront";
    public const string DatabaseComments = "AsreKhodroComments";
}
