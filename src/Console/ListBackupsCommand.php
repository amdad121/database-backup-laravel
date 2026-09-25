<?php

declare(strict_types=1);

namespace AmdadulHaq\DatabaseBackup\Console;

use AmdadulHaq\DatabaseBackup\DatabaseBackupManager;
use AmdadulHaq\DatabaseBackup\Exceptions\BackupFailedException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Number;

class ListBackupsCommand extends Command
{
    protected $signature = 'db:backups {--connection= : Connection to list backups for; defaults to config("database-backup.connection")}';

    protected $description = 'List the database backups stored on the backup disk.';

    public function handle(DatabaseBackupManager $manager): int
    {
        $connection = $this->option('connection');
        $connection = is_string($connection) && $connection !== '' ? $connection : null;

        try {
            $backups = $manager->backups($connection);
        } catch (BackupFailedException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($backups === []) {
            $this->components->warn('No backups found on the ['.config('database-backup.disk').'] disk.');

            return self::SUCCESS;
        }

        $this->table(
            ['File', 'Size', 'Modified'],
            array_map(fn (array $backup): array => [
                $backup['path'],
                Number::fileSize($backup['size'], precision: 1),
                Date::createFromTimestamp($backup['modified'], (string) config('app.timezone', 'UTC'))->toDateTimeString(),
            ], $backups),
        );

        return self::SUCCESS;
    }
}
