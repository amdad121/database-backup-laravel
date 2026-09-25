<?php

declare(strict_types=1);

namespace AmdadulHaq\DatabaseBackup\Dumpers;

use AmdadulHaq\DatabaseBackup\Exceptions\BackupFailedException;
use PDO;
use Throwable;

/**
 * Backs up SQLite connections with VACUUM INTO, which produces a consistent
 * snapshot that includes committed data still in the WAL file.
 */
final class SqliteDumper extends ProcessDumper
{
    public function extension(): string
    {
        return 'sqlite';
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    public function dump(array $connection, string $target): void
    {
        $database = $this->config($connection, 'database');

        throw_if($database === '' || ! is_file($database), BackupFailedException::class, "SQLite database file not found: [{$database}].");

        try {
            $pdo = new PDO('sqlite:'.$database, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->prepare('VACUUM INTO ?')->execute([$target]);
        } catch (Throwable $throwable) {
            throw new BackupFailedException("Unable to back up SQLite database [{$database}]: {$throwable->getMessage()}", previous: $throwable);
        }
    }
}
