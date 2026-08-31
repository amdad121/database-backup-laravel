<?php

declare(strict_types=1);

namespace AmdadulHaq\DatabaseBackup\Events;

use Throwable;

final readonly class BackupFailed
{
    public function __construct(
        public string $connection,
        public Throwable $exception,
    ) {}
}
