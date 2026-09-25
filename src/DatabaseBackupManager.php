<?php

declare(strict_types=1);

namespace AmdadulHaq\DatabaseBackup;

use AmdadulHaq\DatabaseBackup\Dumpers\Dumper;
use AmdadulHaq\DatabaseBackup\Dumpers\MysqlDumper;
use AmdadulHaq\DatabaseBackup\Dumpers\PostgresDumper;
use AmdadulHaq\DatabaseBackup\Dumpers\SqliteDumper;
use AmdadulHaq\DatabaseBackup\Events\BackupCompleted;
use AmdadulHaq\DatabaseBackup\Events\BackupFailed;
use AmdadulHaq\DatabaseBackup\Events\BackupPruneFailed;
use AmdadulHaq\DatabaseBackup\Events\RestoreCompleted;
use AmdadulHaq\DatabaseBackup\Events\RestoreFailed;
use AmdadulHaq\DatabaseBackup\Exceptions\BackupFailedException;
use AmdadulHaq\DatabaseBackup\Exceptions\RestoreFailedException;
use AmdadulHaq\DatabaseBackup\Restorers\MysqlRestorer;
use AmdadulHaq\DatabaseBackup\Restorers\PostgresRestorer;
use AmdadulHaq\DatabaseBackup\Restorers\Restorer;
use AmdadulHaq\DatabaseBackup\Restorers\SqliteRestorer;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Arr;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DatabaseBackupManager
{
    public function __construct(
        private readonly Config $config,
        private readonly FilesystemFactory $filesystem,
        private readonly Dispatcher $events,
        private readonly DatabaseManager $db,
    ) {}

    /**
     * Dump a connection, upload it to the backup disk, and return the remote path.
     */
    public function backup(?string $connection = null): string
    {
        $connection = $connection ?: (string) $this->config->get('database-backup.connection');

        throw_if($connection === '', BackupFailedException::class, 'No database connection configured to back up.');

        try {
            return $this->privately(fn (): string => $this->run($connection));
        } catch (Throwable $throwable) {
            $this->events->dispatch(new BackupFailed($connection, $throwable));

            throw $throwable instanceof BackupFailedException ? $throwable : new BackupFailedException($throwable->getMessage(), previous: $throwable);
        }
    }

    /**
     * Restore a backup from the disk into the connection. Pass $file as a
     * disk-relative path, or set $latest to pick the newest backup.
     */
    public function restore(?string $connection, string $file = '', bool $latest = false): string
    {
        $connection = $connection ?: (string) $this->config->get('database-backup.connection');

        throw_if($connection === '', RestoreFailedException::class, 'No database connection configured to restore.');

        try {
            $remote = $this->privately(fn (): string => $this->runRestore($connection, $file, $latest));
        } catch (Throwable $throwable) {
            $this->events->dispatch(new RestoreFailed($connection, $throwable));

            throw $throwable instanceof RestoreFailedException ? $throwable : new RestoreFailedException($throwable->getMessage(), previous: $throwable);
        }

        $this->events->dispatch(new RestoreCompleted($connection, $this->diskName(), $remote));

        return $remote;
    }

    /**
     * List the backups on the disk for a connection, newest first.
     *
     * @return array<int, array{path: string, size: int, modified: int}>
     */
    public function backups(?string $connection = null): array
    {
        $connection = $connection ?: (string) $this->config->get('database-backup.connection');

        throw_if($connection === '', BackupFailedException::class, 'No database connection configured.');

        try {
            $disk = $this->disk();

            return array_map(fn (string $file): array => [
                'path' => $file,
                'size' => (int) $disk->size($file),
                'modified' => $disk->lastModified($file),
            ], $this->backupFiles($disk, $connection, $this->connectionConfig($connection, BackupFailedException::class)));
        } catch (Throwable $throwable) {
            throw $throwable instanceof BackupFailedException ? $throwable : new BackupFailedException($throwable->getMessage(), previous: $throwable);
        }
    }

    private function run(string $connection): string
    {
        $settings = $this->settings();
        $connectionConfig = $this->connectionConfig($connection, BackupFailedException::class);
        $dumper = $this->dumper((string) ($connectionConfig['driver'] ?? ''), $settings);

        $extension = $dumper->extension();
        $local = $this->tempDir($settings, BackupFailedException::class).'/db-backup-'.Str::random(16).'.'.$extension;

        try {
            $dumper->dump($connectionConfig, $local);

            if ((bool) ($settings['compress'] ?? true)) {
                $local = $this->gzip($local);
                $extension .= '.gz';
            }

            $disk = $this->disk();
            $remote = $this->remotePath($disk, $connection, $connectionConfig, $extension);

            $this->upload($disk, $local, $remote);

            $this->events->dispatch(new BackupCompleted($connection, $this->diskName(), $remote, filesize($local) ?: 0));

            try {
                $this->prune($disk, $connection, $connectionConfig, (array) ($settings['retention'] ?? []));
            } catch (Throwable $throwable) {
                // The backup itself is safely stored; a pruning error must not report it as failed.
                $this->events->dispatch(new BackupPruneFailed($connection, $throwable));
            }

            return $remote;
        } finally {
            if (is_file($local)) {
                @unlink($local);
            }
        }
    }

    private function runRestore(string $connection, string $file, bool $latest): string
    {
        $settings = $this->settings();
        $connectionConfig = $this->connectionConfig($connection, RestoreFailedException::class);
        $driver = (string) ($connectionConfig['driver'] ?? '');
        $restorer = $this->restorer($driver, $settings);

        $disk = $this->disk();
        $remote = $latest ? ($this->backupFiles($disk, $connection, $connectionConfig)[0] ?? '') : $file;

        throw_if($remote === '', RestoreFailedException::class, 'No backup file found to restore.');
        throw_unless($disk->exists($remote), RestoreFailedException::class, "Backup [{$remote}] not found on the [{$this->diskName()}] disk.");

        $expected = $driver === 'sqlite' ? 'sqlite' : 'sql';
        $basename = basename($remote);
        throw_unless(
            str_ends_with($basename, '.'.$expected) || str_ends_with($basename, '.'.$expected.'.gz'),
            RestoreFailedException::class,
            "Backup [{$remote}] is not a .{$expected} backup and cannot be restored into a [{$driver}] connection.",
        );

        $local = $this->tempDir($settings, RestoreFailedException::class).'/db-restore-'.Str::random(16).'-'.$basename;

        try {
            $this->download($disk, $remote, $local);

            if (str_ends_with($local, '.gz')) {
                $local = $this->gunzip($local);
            }

            // Drop this process's open handle so it reconnects to the restored database.
            $this->db->purge($connection);

            $restorer->restore($connectionConfig, $local);

            return $remote;
        } finally {
            if (is_file($local)) {
                @unlink($local);
            }
        }
    }

    /**
     * Run $callback with a restrictive umask so dumps are never readable by other users.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function privately(callable $callback): mixed
    {
        $umask = umask(0077);

        try {
            return $callback();
        } finally {
            umask($umask);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        return (array) $this->config->get('database-backup');
    }

    private function diskName(): string
    {
        return (string) ($this->config->get('database-backup.disk') ?? 'local');
    }

    private function disk(): Filesystem
    {
        return $this->filesystem->disk($this->diskName());
    }

    /**
     * Resolve a connection's config the way Laravel does: expand `url`, and use
     * the `write` host for read/write splits (first host when several are listed).
     *
     * @param  class-string<Throwable>  $exception
     * @return array<string, mixed>
     */
    private function connectionConfig(string $connection, string $exception): array
    {
        $config = $this->config->get("database.connections.{$connection}");

        throw_if(! is_array($config) || $config === [], $exception, "Database connection [{$connection}] is not configured.");

        /** @var array<string, mixed> $config */
        $config = (new ConfigurationUrlParser)->parseConfiguration($config);

        if (isset($config['write']) && is_array($config['write'])) {
            $config = array_merge($config, $config['write']);
        }

        unset($config['read'], $config['write']);

        if (is_array($config['host'] ?? null)) {
            $config['host'] = Arr::first($config['host']);
        }

        return $config;
    }

    /**
     * Backups live in "{path}/{connection}/". Files that older releases stored
     * directly in "{path}/" are still recognised when their name matches this
     * connection and database exactly.
     */
    private function connectionFolder(string $connection): string
    {
        return ltrim(trim((string) $this->config->get('database-backup.path', ''), '/').'/'.Str::slug($connection), '/');
    }

    /**
     * @param  array<string, mixed>  $connectionConfig
     */
    private function filePrefix(string $connection, array $connectionConfig): string
    {
        $database = basename((string) ($connectionConfig['database'] ?? $connection), '.sqlite');

        return Str::slug($connection).'-'.Str::slug($database).'-';
    }

    /**
     * @param  array<string, mixed>  $connectionConfig
     */
    private function remotePath(Filesystem $disk, string $connection, array $connectionConfig, string $extension): string
    {
        $base = $this->connectionFolder($connection).'/'.$this->filePrefix($connection, $connectionConfig).now()->format('Y-m-d_His');
        $remote = "{$base}.{$extension}";

        for ($i = 2; $disk->exists($remote); $i++) {
            $remote = "{$base}-{$i}.{$extension}";
        }

        return $remote;
    }

    /**
     * The connection's backups on the disk, newest first (ordered by the timestamp in the name).
     *
     * @param  array<string, mixed>  $connectionConfig
     * @return list<string>
     */
    private function backupFiles(Filesystem $disk, string $connection, array $connectionConfig): array
    {
        $suffix = '(\d{4}-\d{2}-\d{2}_\d{6})(?:-(\d+))?\.(?:sql|sqlite)(?:\.gz)?$/';
        $legacy = '/^'.preg_quote($this->filePrefix($connection, $connectionConfig), '/').$suffix;
        $root = trim((string) $this->config->get('database-backup.path', ''), '/');

        // Every backup in the connection's folder belongs to it; legacy files in the
        // shared root only when the name matches this connection and database exactly.
        $candidates = [
            ...array_filter($disk->files($this->connectionFolder($connection)), fn (string $file): bool => preg_match('/-'.$suffix, basename($file)) === 1),
            ...array_filter($disk->files($root ?: null), fn (string $file): bool => preg_match($legacy, basename($file)) === 1),
        ];

        $backups = [];

        foreach (array_unique($candidates) as $file) {
            if (preg_match('/-'.$suffix, basename($file), $m) === 1) {
                $backups[$file] = $m[1].'#'.str_pad($m[2] ?? '1', 6, '0', STR_PAD_LEFT);
            }
        }

        arsort($backups, SORT_STRING);

        return array_map(strval(...), array_keys($backups));
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

    /**
     * @param  array<string, mixed>  $settings
     */
    private function restorer(string $driver, array $settings): Restorer
    {
        $binaries = (array) ($settings['binaries'] ?? []);
        $timeout = (int) ($settings['timeout'] ?? 900);

        return match ($driver) {
            'mysql', 'mariadb' => new MysqlRestorer($binaries, $timeout),
            'pgsql' => new PostgresRestorer($binaries, $timeout),
            'sqlite' => new SqliteRestorer($binaries, $timeout),
            default => throw new RestoreFailedException(
                "Unsupported driver [{$driver}]. Supported: mysql, mariadb, pgsql, sqlite."
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  class-string<Throwable>  $exception
     */
    private function tempDir(array $settings, string $exception): string
    {
        $dir = (string) ($settings['temp_directory'] ?? sys_get_temp_dir());

        throw_if(
            ! is_dir($dir) && ! @mkdir($dir, 0700, true) && ! is_dir($dir),
            $exception,
            "Unable to create temp directory [{$dir}].",
        );

        return $dir;
    }

    private function download(Filesystem $disk, string $remote, string $local): void
    {
        $in = $disk->readStream($remote);
        throw_if($in === null, RestoreFailedException::class, "Unable to read backup [{$remote}].");

        $out = null;

        try {
            $out = fopen($local, 'wb');
            throw_if($out === false, RestoreFailedException::class, "Unable to write [{$local}].");
            throw_if(stream_copy_to_stream($in, $out) === false, RestoreFailedException::class, "Unable to download backup [{$remote}].");
            throw_unless(fclose($out), RestoreFailedException::class, "Unable to write [{$local}].");
            $out = null;
        } finally {
            if (is_resource($out)) {
                fclose($out);
            }

            if (is_resource($in)) {
                fclose($in);
            }
        }
    }

    private function upload(Filesystem $disk, string $local, string $remote): void
    {
        $stream = fopen($local, 'rb');
        throw_if($stream === false, BackupFailedException::class, "Unable to read the dump at [{$local}].");

        try {
            throw_if($disk->writeStream($remote, $stream) === false, BackupFailedException::class, "Failed to write the backup to [{$remote}].");
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function gzip(string $path): string
    {
        $gz = $path.'.gz';

        try {
            $this->copyFile($path, 'compress.zlib://'.$gz, filesize($path));
        } catch (Throwable $throwable) {
            @unlink($gz);

            throw new BackupFailedException("Unable to gzip [{$path}]: {$throwable->getMessage()}", previous: $throwable);
        }

        @unlink($path);

        return $gz;
    }

    private function gunzip(string $path): string
    {
        $out = substr($path, 0, -3);

        try {
            $this->copyFile('compress.zlib://'.$path, $out);
        } catch (Throwable $throwable) {
            @unlink($out);

            throw new RestoreFailedException("Unable to gunzip [{$path}]: {$throwable->getMessage()}", previous: $throwable);
        } finally {
            @unlink($path);
        }

        return $out;
    }

    /**
     * Copy $from to $to, failing on any short or failed write (e.g. a full
     * disk) so a truncated file is never treated as a good backup.
     */
    private function copyFile(string $from, string $to, int|false|null $expected = null): void
    {
        $in = fopen($from, 'rb');
        throw_if($in === false, RuntimeException::class, 'cannot open the source file');

        $out = false;

        try {
            $out = fopen($to, 'wb');
            throw_if($out === false, RuntimeException::class, 'cannot open the target file');

            $copied = stream_copy_to_stream($in, $out);
            throw_if($copied === false || ($expected !== null && $copied !== $expected), RuntimeException::class, 'write failed (disk full?)');

            $closed = fclose($out);
            $out = false;
            throw_unless($closed, RuntimeException::class, 'write failed (disk full?)');
        } finally {
            if ($out !== false) {
                fclose($out);
            }

            fclose($in);
        }
    }

    /**
     * @param  array<string, mixed>  $connectionConfig
     * @param  array<string, mixed>  $retention
     */
    private function prune(Filesystem $disk, string $connection, array $connectionConfig, array $retention): void
    {
        $keepLast = (int) ($retention['keep_last'] ?? 0);
        $keepDays = (int) ($retention['keep_days'] ?? 0);

        if ($keepLast <= 0 && $keepDays <= 0) {
            return;
        }

        $cutoff = $keepDays > 0 ? now()->subDays($keepDays)->getTimestamp() : null;

        foreach ($this->backupFiles($disk, $connection, $connectionConfig) as $index => $file) {
            // Never prune the newest backup (the one just uploaded).
            if ($index === 0) {
                continue;
            }

            if (($keepLast > 0 && $index >= $keepLast) || ($cutoff !== null && $disk->lastModified($file) < $cutoff)) {
                $disk->delete($file);
            }
        }
    }
}
