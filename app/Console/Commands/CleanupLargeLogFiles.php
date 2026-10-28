<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

class CleanupLargeLogFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'log:cleanup
                            {--dry-run : Run without making actual changes}
                            {--force : Force cleanup even if auto_cleanup is disabled}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup log files that exceed the configured maximum size limit';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! config('filament-log-viewer.auto_cleanup_enabled', false) && ! $this->option('force')) {
            $this->warn('Automatic log cleanup is disabled. Use --force to run anyway.');

            return self::FAILURE;
        }

        $logPath = storage_path('logs');
        $maxSizeKb = (int) config('filament-log-viewer.max_log_file_size', 2048);
        $maxSizeBytes = $maxSizeKb * 1024;
        $strategy = config('filament-log-viewer.cleanup_strategy', 'truncate_oldest');
        $maxEntriesToKeep = (int) config('filament-log-viewer.max_log_entries_to_keep', 1000);
        $isDryRun = $this->option('dry-run');

        if (! is_dir($logPath)) {
            $this->error("Log directory not found: {$logPath}");

            return self::FAILURE;
        }

        $this->info('Starting log cleanup...');
        $this->newLine();

        $logFiles = $this->getLogFiles($logPath);
        $processedCount = 0;
        $totalFreedSpace = 0;

        foreach ($logFiles as $file) {
            $filePath = $file->getRealPath();
            $fileSize = $file->getSize();
            $relativePath = str_replace($logPath.DIRECTORY_SEPARATOR, '', $filePath);

            if ($fileSize > $maxSizeBytes) {
                $this->line("📄 Processing: <comment>{$relativePath}</comment>");
                $this->line('   Size: <info>'.number_format($fileSize / 1024, 2).' KB</info>');

                if ($isDryRun) {
                    $this->line("   <fg=yellow>⚠ DRY RUN:</> Would clean this file using '{$strategy}' strategy");
                    $processedCount++;

                    continue;
                }

                try {
                    $freedSpace = $this->cleanupFile($filePath, $strategy, $maxEntriesToKeep);
                    $totalFreedSpace += $freedSpace;
                    $processedCount++;

                    $newSize = file_exists($filePath) ? filesize($filePath) : 0;
                    $this->line('   <fg=green>✓ Cleaned:</> '.number_format($freedSpace / 1024, 2).' KB freed');
                    $this->line('   New size: <info>'.number_format($newSize / 1024, 2).' KB</info>');
                } catch (\Exception $e) {
                    $this->error("   ✗ Failed to clean: {$e->getMessage()}");
                }

                $this->newLine();
            }
        }

        if ($processedCount === 0) {
            $this->info('✓ No log files exceed the size limit. Nothing to clean.');
        } else {
            $action = $isDryRun ? 'would be' : 'were';
            $this->info("✓ {$processedCount} file(s) {$action} processed.");

            if (! $isDryRun && $totalFreedSpace > 0) {
                $this->info('✓ Total space freed: '.number_format($totalFreedSpace / 1024 / 1024, 2).' MB');
            }
        }

        return self::SUCCESS;
    }

    /**
     * Get all log files from the logs directory.
     */
    private function getLogFiles(string $logPath): Finder
    {
        return Finder::create()
            ->files()
            ->name('*.log')
            ->in($logPath);
    }

    /**
     * Clean up a log file based on the configured strategy.
     *
     * @return int Bytes freed
     */
    private function cleanupFile(string $filePath, string $strategy, int $maxEntriesToKeep): int
    {
        $originalSize = filesize($filePath);

        match ($strategy) {
            'delete_file' => $this->deleteFile($filePath),
            'truncate_oldest' => $this->truncateOldest($filePath, $maxEntriesToKeep),
            default => throw new \InvalidArgumentException("Unknown cleanup strategy: {$strategy}"),
        };

        clearstatcache(true, $filePath);
        $newSize = file_exists($filePath) ? filesize($filePath) : 0;

        return $originalSize - $newSize;
    }

    /**
     * Delete the entire log file.
     */
    private function deleteFile(string $filePath): void
    {
        if (! File::delete($filePath)) {
            throw new \RuntimeException("Failed to delete file: {$filePath}");
        }
    }

    /**
     * Keep only the most recent log entries, remove the oldest.
     */
    private function truncateOldest(string $filePath, int $maxEntriesToKeep): void
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Failed to open file for reading: {$filePath}");
        }

        $entries = [];
        $currentEntry = [];

        // Parse log entries (Laravel format: [YYYY-MM-DD HH:MM:SS])
        while (($line = fgets($handle)) !== false) {
            // Check if this line starts a new log entry
            if (preg_match('/^\[\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}\]/', $line)) {
                // Save the previous entry if it exists
                if (! empty($currentEntry)) {
                    $entries[] = implode('', $currentEntry);
                }
                // Start a new entry
                $currentEntry = [$line];
            } else {
                // Continue the current entry
                $currentEntry[] = $line;
            }
        }

        // Don't forget the last entry
        if (! empty($currentEntry)) {
            $entries[] = implode('', $currentEntry);
        }

        fclose($handle);

        // Keep only the most recent entries
        $totalEntries = count($entries);
        if ($totalEntries <= $maxEntriesToKeep) {
            // File has fewer entries than the limit, no need to truncate
            return;
        }

        $entriesToKeep = array_slice($entries, -$maxEntriesToKeep);

        // Write back the kept entries
        if (file_put_contents($filePath, implode('', $entriesToKeep)) === false) {
            throw new \RuntimeException("Failed to write truncated content to: {$filePath}");
        }
    }
}
