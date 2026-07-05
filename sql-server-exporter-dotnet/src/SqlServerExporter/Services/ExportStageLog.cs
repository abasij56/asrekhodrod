namespace SqlServerExporter.Services;

internal static class ExportStageLog
{
    public static void Boot(string message) => Write(message);

    public static void Phase(string message) => Write($"  · {message}");

    public static void Detail(string message) => Write($"    · {message}");

    private static void Write(string message)
    {
        Console.WriteLine($"[{DateTime.Now:HH:mm:ss}] {message}");
        Console.Out.Flush();
    }
}
