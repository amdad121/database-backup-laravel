<?php

declare(strict_types=1);

namespace AmdadulHaq\DatabaseBackup\Events;

final readonly class RestoreCompleted
{
    public function __construct(
        public string $connection,
        public string $disk,
        public string $remotePath,
    ) {}
}
