<?php

declare(strict_types=1);

namespace AmdadulHaq\DatabaseBackup;

use AmdadulHaq\DatabaseBackup\Console\BackupDatabaseCommand;
use Illuminate\Support\ServiceProvider;

class DatabaseBackupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/database-backup.php', 'database-backup');

        $this->app->singleton(DatabaseBackupManager::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/database-backup.php' => config_path('database-backup.php'),
            ], 'database-backup-config');

            $this->commands([BackupDatabaseCommand::class]);
        }
    }
}
