<?php

declare(strict_types=1);

namespace AmdadulHaq\DatabaseBackup\Tests;

use AmdadulHaq\DatabaseBackup\DatabaseBackupServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [DatabaseBackupServiceProvider::class];
    }
}
