<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Notifications\HealthAlert;
use Illuminate\Console\Command;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup';

    protected $description = 'Dump the database to a gzipped file on the backup disk and delete old backups';

    public function handle(): int
    {
        try {
            $path = $this->backup();
        } catch (Throwable $e) {
            report($e);
            Notification::send(Admin::all(), new HealthAlert('Followly: the database backup failed', [
                'The daily database backup failed: '.Str::limit($e->getMessage(), 500),
                'Run `php artisan db:backup` on the server to retry and see the full error.',
            ]));
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Backed up to {$path}; deleted {$this->prune()} old backups.");

        return self::SUCCESS;
    }

    /**
     * @return string the backup's path on the disk
     */
    protected function backup(): string
    {
        $db = config('database.connections.'.config('database.default'));

        if (! in_array($db['driver'], ['mysql', 'mariadb'], true)) {
            throw new RuntimeException("db:backup supports MySQL and MariaDB, not {$db['driver']}.");
        }

        $sql = tempnam(sys_get_temp_dir(), 'backup');
        $gz = "{$sql}.gz";

        try {
            // The password goes in the environment so it does not show up in the process list.
            Process::timeout(1800)->env(['MYSQL_PWD' => (string) $db['password']])->run([
                config('operations.backup.mysqldump'),
                ...(($db['unix_socket'] ?? null) ? ["--socket={$db['unix_socket']}"] : ["--host={$db['host']}", "--port={$db['port']}"]),
                "--user={$db['username']}",
                // A consistent snapshot without locking tables; cPanel users lack the PROCESS privilege tablespaces need.
                '--single-transaction', '--quick', '--no-tablespaces',
                "--result-file={$sql}",
                $db['database'],
            ])->throw();

            // Compress in chunks so a large dump never sits in memory.
            [$in, $out] = [fopen($sql, 'rb'), gzopen($gz, 'wb6')];
            while (! feof($in)) {
                gzwrite($out, fread($in, 1 << 20));
            }
            fclose($in);
            gzclose($out);

            $name = Str::slug(config('app.name')).'-'.now()->format('Y-m-d-His').'.sql.gz';

            return Storage::disk(config('operations.backup.disk'))->putFileAs(config('operations.backup.path'), new File($gz), $name)
                ?: throw new RuntimeException('Could not write the backup to the '.config('operations.backup.disk').' disk.');
        } finally {
            @unlink($sql);
            @unlink($gz);
        }
    }

    protected function prune(): int
    {
        $disk = Storage::disk(config('operations.backup.disk'));
        $cutoff = now()->subDays(config('operations.backup.keep_days'))->getTimestamp();

        return collect($disk->files(config('operations.backup.path')))
            ->filter(fn (string $file) => str_ends_with($file, '.sql.gz') && $disk->lastModified($file) < $cutoff)
            ->each(fn (string $file) => $disk->delete($file))
            ->count();
    }
}
