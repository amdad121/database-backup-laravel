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
    | APP_NAME so several sites can safely share one bucket. Files are named
    | "{connection}-{database}-{Y-m-d_His}.sql[.gz]".
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
    | temp_directory : where the local dump is written before upload.
    | binaries       : absolute paths to CLI tools when not on $PATH.
    | extra_options  : raw args appended to the dump command, per driver.
    |
    */
    'timeout' => (int) env('DB_BACKUP_TIMEOUT', 900),
    'temp_directory' => env('DB_BACKUP_TEMP_DIR', storage_path('app/database-backup')),

    'binaries' => [
        'mysqldump' => env('DB_BACKUP_BIN_MYSQLDUMP', 'mysqldump'),
        'pg_dump' => env('DB_BACKUP_BIN_PGDUMP', 'pg_dump'),
    ],

    'extra_options' => [
        'mysql' => ['--single-transaction', '--quick', '--no-tablespaces', '--routines', '--triggers', '--events'],
        'mariadb' => ['--single-transaction', '--quick', '--no-tablespaces', '--routines', '--triggers', '--events'],
        'pgsql' => ['--no-owner', '--no-privileges'],
    ],
];
