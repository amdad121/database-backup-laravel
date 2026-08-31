<?php

declare(strict_types=1);

namespace AmdadulHaq\DatabaseBackup\Console;

use AmdadulHaq\DatabaseBackup\DatabaseBackupManager;
use AmdadulHaq\DatabaseBackup\Exceptions\BackupFailedException;
use Illuminate\Console\Command;

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
                number_format($backup['size'] / 1024, 1).' KB',
                date('Y-m-d H:i:s', $backup['modified']),
            ], $backups),
        );

        return self::SUCCESS;
    }
}
