<?php

declare(strict_types=1);

namespace AmdadulHaq\DatabaseBackup\Restorers;

use AmdadulHaq\DatabaseBackup\Exceptions\RestoreFailedException;

/**
 * Restores a SQLite backup by atomically swapping the database file.
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

        $header = @file_get_contents($source, false, null, 0, 16);
        throw_unless($header === "SQLite format 3\0", RestoreFailedException::class, "Backup [{$source}] is not a SQLite database.");

        // Stage next to the target so the final rename is atomic (same filesystem).
        $staged = $database.'.restore-'.bin2hex(random_bytes(6));

        try {
            throw_unless(@copy($source, $staged), RestoreFailedException::class, "Unable to restore SQLite database to [{$database}].");

            if (is_file($database)) {
                @chmod($staged, fileperms($database) & 0777);
            }

            // Remove the old database's WAL/journal first: left in place, SQLite could
            // replay it onto the restored file and corrupt it.
            foreach (['-wal', '-shm', '-journal'] as $suffix) {
                throw_if(is_file($database.$suffix) && ! @unlink($database.$suffix), RestoreFailedException::class, "Unable to remove [{$database}{$suffix}].");
            }

            throw_unless(@rename($staged, $database), RestoreFailedException::class, "Unable to restore SQLite database to [{$database}].");
        } finally {
            if (is_file($staged)) {
                @unlink($staged);
            }
        }
    }
}
