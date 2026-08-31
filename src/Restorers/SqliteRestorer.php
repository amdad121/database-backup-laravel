<?php

declare(strict_types=1);

namespace AmdadulHaq\DatabaseBackup\Restorers;

use AmdadulHaq\DatabaseBackup\Exceptions\RestoreFailedException;

/**
 * Restores a SQLite backup by copying the file back into place.
 */
final class SqliteRestorer extends ProcessRestorer
{
    /**
     * @param  array<string, mixed>  $connection
     */
    public function restore(array $connection, string $source): void
    {
        $database = $this->config($connection, 'database');

        throw_if($database === '', RestoreFailedException::class, 'SQLite database path is not configured.');
        throw_unless(@copy($source, $database), RestoreFailedException::class, "Unable to restore SQLite database to [{$database}].");
    }
}
