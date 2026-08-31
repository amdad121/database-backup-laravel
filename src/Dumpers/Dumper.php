<?php

declare(strict_types=1);

namespace AmdadulHaq\DatabaseBackup\Dumpers;

interface Dumper
{
    /**
     * Write a dump of the connection to $target (SQL, or the file for SQLite).
     *
     * @param  array<string, mixed>  $connection  A config/database.php connection array.
     */
    public function dump(array $connection, string $target): void;

    /**
     * Extension for the uncompressed dump: "sql" or "sqlite".
     */
    public function extension(): string;
}
