<?php

declare(strict_types=1);

namespace AmdadulHaq\DatabaseBackup\Events;

use Throwable;

/**
 * The backup was stored, but its .sha256 checksum could not be written.
 */
final readonly class BackupChecksumFailed
{
    public function __construct(
        public string $connection,
        public string $remotePath,
        public Throwable $exception,
    ) {}
}
