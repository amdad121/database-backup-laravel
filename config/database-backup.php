<?php

declare(strict_types=1);

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Connection to back up
    |--------------------------------------------------------------------------
    |
    | The database connection (from config/database.php) to dump when the
    | command is run without --connection. Supported drivers: pgsql, mysql,
    | mariadb, sqlite.
    |
    */
    'connection' => env('DB_BACKUP_CONNECTION', env('DB_CONNECTION', 'sqlite')),

    /*
    |--------------------------------------------------------------------------
    | Destination disk
    |--------------------------------------------------------------------------
    |
    | Any disk from config/filesystems.php. Defaults to the local disk; point
    | this at an S3 disk configured for your provider (AWS S3, Cloudflare R2,
    | MinIO, DigitalOcean Spaces, ...) to store backups off-server.
    |
    */
    'disk' => env('DB_BACKUP_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Root folder
    |--------------------------------------------------------------------------
    |
    | Folder on the disk to store backups in. Defaults to a slug of your
    | APP_NAME so several sites can safely share one bucket. Each connection
    | gets its own sub-folder: "{path}/{connection}/{connection}-{database}-{Y-m-d_His}.sql[.gz]".
    |
    */
    'path' => env('DB_BACKUP_PATH', Str::slug((string) env('APP_NAME', 'laravel'))),

    /*
    |--------------------------------------------------------------------------
    | Compression
    |--------------------------------------------------------------------------
    |
    | gzip the dump before upload. Adds a .gz suffix to the filename.
    |
    */
    'compress' => (bool) env('DB_BACKUP_COMPRESS', true),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | After a successful upload, prune older backups for the same connection.
    | keep_last: keep at most N most-recent files (0 = unlimited).
    | keep_days: delete files older than N days (0 = disabled).
    |
    */
    'retention' => [
        'keep_last' => (int) env('DB_BACKUP_KEEP_LAST', 7),
        'keep_days' => (int) env('DB_BACKUP_KEEP_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dump behaviour
    |--------------------------------------------------------------------------
    |
    | timeout        : max seconds a single dump may run.
    | temp_directory : where the local dump is written before upload; defaults
    |                  to the system temp dir so nothing lands in the app tree.
    |                  Created with 0700 and dumps are written 0600.
    | binaries       : absolute paths to CLI tools when not on $PATH.
    | extra_options  : raw args appended to the dump command, per driver.
    |
    */
    'timeout' => (int) env('DB_BACKUP_TIMEOUT', 900),
    // null = "{system temp}/database-backup-{uid}", resolved per user at runtime.
    'temp_directory' => env('DB_BACKUP_TEMP_DIR'),

    'binaries' => [
        'mysqldump' => env('DB_BACKUP_BIN_MYSQLDUMP', 'mysqldump'),
        'mysql' => env('DB_BACKUP_BIN_MYSQL', 'mysql'),
        'pg_dump' => env('DB_BACKUP_BIN_PGDUMP', 'pg_dump'),
        'psql' => env('DB_BACKUP_BIN_PSQL', 'psql'),
        // MariaDB driver only; when unset, mariadb-dump / mariadb are used if on
        // $PATH, falling back to mysqldump / mysql.
        'mariadb-dump' => env('DB_BACKUP_BIN_MARIADB_DUMP'),
        'mariadb' => env('DB_BACKUP_BIN_MARIADB'),
    ],

    'extra_options' => [
        'mysql' => ['--single-transaction', '--quick', '--no-tablespaces', '--routines', '--triggers', '--events'],
        'mariadb' => ['--single-transaction', '--quick', '--no-tablespaces', '--routines', '--triggers', '--events'],
        'pgsql' => ['--no-owner', '--no-privileges', '--clean', '--if-exists'],
    ],
];
