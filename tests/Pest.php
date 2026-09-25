<?php

declare(strict_types=1);
use AmdadulHaq\DatabaseBackup\Tests\TestCase;
use Illuminate\Support\Facades\Storage;

uses(TestCase::class)->in(__DIR__);

/**
 * Backup files in a folder of the fake disk, without their .sha256 checksums.
 *
 * @return list<string>
 */
function dumps(string $folder): array
{
    return array_values(array_filter(
        Storage::disk('backups_disk')->files($folder),
        fn (string $file): bool => ! str_ends_with($file, '.sha256'),
    ));
}
