<?php

declare(strict_types=1);

namespace AmdadulHaq\DatabaseBackup;

use AmdadulHaq\DatabaseBackup\Dumpers\Dumper;
use AmdadulHaq\DatabaseBackup\Dumpers\MysqlDumper;
use AmdadulHaq\DatabaseBackup\Dumpers\PostgresDumper;
use AmdadulHaq\DatabaseBackup\Dumpers\SqliteDumper;
use AmdadulHaq\DatabaseBackup\Events\BackupCompleted;
use AmdadulHaq\DatabaseBackup\Events\BackupFailed;
use AmdadulHaq\DatabaseBackup\Exceptions\BackupFailedException;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Throwable;

class DatabaseBackupManager
{
    public function __construct(
        private readonly Config $config,
        private readonly FilesystemFactory $filesystem,
        private readonly Dispatcher $events,
    ) {}

    /**
     * Dump a connection, upload it to the backup disk, and return the remote path.
     */
    public function backup(?string $connection = null): string
    {
        $connection = $connection ?: (string) $this->config->get('database-backup.connection');

        throw_if($connection === '', BackupFailedException::class, 'No database connection configured to back up.');

        try {
            return $this->run($connection);
        } catch (Throwable $throwable) {
            $this->events->dispatch(new BackupFailed($connection, $throwable));

            throw $throwable instanceof BackupFailedException ? $throwable : new BackupFailedException($throwable->getMessage(), previous: $throwable);
        }
    }

    private function run(string $connection): string
    {
        $settings = (array) $this->config->get('database-backup');
        $connectionConfig = (array) $this->config->get("database.connections.{$connection}");

        throw_if($connectionConfig === [], BackupFailedException::class, "Database connection [{$connection}] is not configured.");

        $dumper = $this->dumper((string) ($connectionConfig['driver'] ?? ''), $settings);

        $tempDir = (string) ($settings['temp_directory'] ?? sys_get_temp_dir());
        throw_if(
            ! is_dir($tempDir) && ! mkdir($tempDir, 0755, true) && ! is_dir($tempDir),
            BackupFailedException::class,
            "Unable to create temp directory [{$tempDir}].",
        );

        $extension = $dumper->extension();
        $local = $tempDir.'/db-backup-'.Str::random(16).'.'.$extension;

        try {
            $dumper->dump($connectionConfig, $local);

            if ((bool) ($settings['compress'] ?? true)) {
                $local = $this->gzip($local);
                $extension .= '.gz';
            }

            $database = basename((string) ($connectionConfig['database'] ?? $connection), '.sqlite');
            $name = sprintf('%s-%s-%s.%s', Str::slug($connection), Str::slug($database), now()->format('Y-m-d_His'), $extension);
            $remote = ltrim(trim((string) ($settings['path'] ?? ''), '/').'/'.$name, '/');

            $disk = $this->filesystem->disk((string) ($settings['disk'] ?? 'local'));
            $this->upload($disk, $local, $remote);
            $this->prune($disk, (string) ($settings['path'] ?? ''), $connection, (array) ($settings['retention'] ?? []));

            $this->events->dispatch(new BackupCompleted($connection, (string) ($settings['disk'] ?? 'local'), $remote, filesize($local) ?: 0));

            return $remote;
        } finally {
            if (is_file($local)) {
                @unlink($local);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function dumper(string $driver, array $settings): Dumper
    {
        $binaries = (array) ($settings['binaries'] ?? []);
        $extraOptions = (array) ($settings['extra_options'] ?? []);
        $timeout = (int) ($settings['timeout'] ?? 900);

        return match ($driver) {
            'mysql', 'mariadb' => new MysqlDumper($binaries, $extraOptions, $timeout),
            'pgsql' => new PostgresDumper($binaries, $extraOptions, $timeout),
            'sqlite' => new SqliteDumper($binaries, $extraOptions, $timeout),
            default => throw new BackupFailedException(
                "Unsupported driver [{$driver}]. Supported: mysql, mariadb, pgsql, sqlite."
            ),
        };
    }

    private function upload(Filesystem $disk, string $local, string $remote): void
    {
        $stream = fopen($local, 'rb');
        throw_if($stream === false, BackupFailedException::class, "Unable to read the dump at [{$local}].");

        try {
            throw_if($disk->writeStream($remote, $stream) === false, BackupFailedException::class, "Failed to write the backup to [{$remote}].");
        } finally {
            fclose($stream);
        }
    }

    private function gzip(string $path): string
    {
        $gz = $path.'.gz';
        $in = fopen($path, 'rb');
        $out = gzopen($gz, 'wb9');

        throw_if($in === false || $out === false, BackupFailedException::class, "Unable to gzip [{$path}].");

        while (! feof($in)) {
            gzwrite($out, (string) fread($in, 262144));
        }

        fclose($in);
        gzclose($out);
        @unlink($path);

        return $gz;
    }

    /**
     * @param  array<string, mixed>  $retention
     */
    private function prune(Filesystem $disk, string $path, string $connection, array $retention): void
    {
        $keepLast = (int) ($retention['keep_last'] ?? 0);
        $keepDays = (int) ($retention['keep_days'] ?? 0);

        if ($keepLast <= 0 && $keepDays <= 0) {
            return;
        }

        $prefix = trim($path, '/');
        $needle = Str::slug($connection).'-';
        $cutoff = $keepDays > 0 ? now()->subDays($keepDays)->getTimestamp() : null;

        collect($disk->files($prefix ?: null))
            ->filter(fn (string $file): bool => str_starts_with(basename($file), $needle))
            ->map(fn (string $file): array => ['path' => $file, 'time' => $disk->lastModified($file)])
            ->sortByDesc('time')
            ->values()
            ->each(function (array $backup, int $index) use ($disk, $keepLast, $cutoff): void {
                if (($keepLast > 0 && $index >= $keepLast) || ($cutoff !== null && $backup['time'] < $cutoff)) {
                    $disk->delete($backup['path']);
                }
            });
    }
}
