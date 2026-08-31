<?php

declare(strict_types=1);

namespace AmdadulHaq\DatabaseBackup\Console;

use AmdadulHaq\DatabaseBackup\DatabaseBackupManager;
use AmdadulHaq\DatabaseBackup\Exceptions\BackupFailedException;
use Illuminate\Console\Command;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'db:backup {--connection= : Connection to back up; defaults to config("database-backup.connection")}';

    protected $description = 'Dump a database connection and store it on the backup disk.';

    public function handle(DatabaseBackupManager $manager): int
    {
        $connection = $this->option('connection');

        try {
            $path = $manager->backup(is_string($connection) ? $connection : null);
        } catch (BackupFailedException $backupFailedException) {
            $this->components->error($backupFailedException->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Backed up to [{$path}] on the [".config('database-backup.disk').'] disk.');

        return self::SUCCESS;
    }
}
