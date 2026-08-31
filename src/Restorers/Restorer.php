<?php

declare(strict_types=1);

namespace AmdadulHaq\DatabaseBackup\Restorers;

interface Restorer
{
    /**
     * Restore an uncompressed backup ($source) into the connection.
     *
     * For SQL drivers $source is a .sql file; for SQLite it is the .sqlite file.
     *
     * @param  array<string, mixed>  $connection  A config/database.php connection array.
     */
    public function restore(array $connection, string $source): void;
}
