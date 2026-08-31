<?php

declare(strict_types=1);

namespace AmdadulHaq\DatabaseBackup\Console;

use AmdadulHaq\DatabaseBackup\DatabaseBackupManager;
use AmdadulHaq\DatabaseBackup\Exceptions\RestoreFailedException;
use Illuminate\Console\Command;

class RestoreDatabaseCommand extends Command
{
    protected $signature = 'db:restore
        {file? : Path of the backup on the disk (omit when using --latest)}
        {--connection= : Connection to restore into; defaults to config("database-backup.connection")}
        {--latest : Restore the most recent backup for the connection}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Restore a database connection from a backup on the backup disk.';

    public function handle(DatabaseBackupManager $manager): int
    {
        $connection = $this->option('connection');
        $connection = is_string($connection) && $connection !== '' ? $connection : (string) config('database-backup.connection');

        $file = $this->argument('file');
        $file = is_string($file) ? $file : '';
        $latest = (bool) $this->option('latest');

        if (! $latest && $file === '') {
            $this->components->error('Pass a backup file path, or use --latest.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("This overwrites the [{$connection}] database. Continue?")) {
            return self::FAILURE;
        }

        try {
            $restored = $manager->restore($connection, $file, $latest);
        } catch (RestoreFailedException $e) {
            $this->components->error("Restore of [{$connection}] failed: ".$e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Restored [{$connection}] from [{$restored}].");

        return self::SUCCESS;
    }
}
