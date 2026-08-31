<?php

declare(strict_types=1);

namespace AmdadulHaq\DatabaseBackup\Dumpers;

use AmdadulHaq\DatabaseBackup\Exceptions\BackupFailedException;

/**
 * Backs up SQLite connections by copying the database file.
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
        throw_unless(@copy($database, $target), BackupFailedException::class, "Unable to copy SQLite database [{$database}].");
    }
}
