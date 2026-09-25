# Database Backup for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/amdadulhaq/database-backup-laravel.svg?style=flat-square)](https://packagist.org/packages/amdadulhaq/database-backup-laravel)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/amdad121/database-backup-laravel/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/amdad121/database-backup-laravel/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/amdad121/database-backup-laravel/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/amdad121/database-backup-laravel/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/amdadulhaq/database-backup-laravel.svg?style=flat-square)](https://packagist.org/packages/amdadulhaq/database-backup-laravel)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat-square&logo=php)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-12%2F13-FF2D20?style=flat-square&logo=laravel)](https://laravel.com)
[![Sponsor](https://img.shields.io/badge/Sponsor-%E2%9D%A4-pink?style=flat-square&logo=github)](https://github.com/sponsors/amdad121)

Simple database backups for Laravel. Dumps one **PostgreSQL** or **MySQL / MariaDB**
connection (SQLite also supported) and stores it on any Laravel filesystem disk —
the local disk by default, or an S3-compatible API such as **Cloudflare R2**,
**MinIO**, **DigitalOcean Spaces**, **Backblaze B2** or **AWS S3**.

## Requirements

- PHP 8.2, 8.3, 8.4, or 8.5
- Laravel 12 or 13
- `pg_dump` for PostgreSQL, `mysqldump` for MySQL/MariaDB (SQLite needs neither)
- `league/flysystem-aws-s3-v3` when the destination disk is an S3 disk

## Installation

You can install the package via composer:

```bash
composer require amdadulhaq/database-backup-laravel
```

Publish the config file:

```bash
php artisan vendor:publish --tag=database-backup-config
```

The service provider is auto-discovered.

## Destination disk

Any disk from `config/filesystems.php` works. Point `DB_BACKUP_DISK` at it.

### AWS S3

```php
// config/filesystems.php
's3' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
],
```

```dotenv
DB_BACKUP_DISK=s3
```

### Cloudflare R2

```php
'r2' => [
    'driver' => 's3',
    'key' => env('R2_ACCESS_KEY_ID'),
    'secret' => env('R2_SECRET_ACCESS_KEY'),
    'region' => 'auto',
    'bucket' => env('R2_BUCKET'),
    'endpoint' => env('R2_ENDPOINT'), // https://<account-id>.r2.cloudflarestorage.com
    'use_path_style_endpoint' => true,
],
```

```dotenv
DB_BACKUP_DISK=r2
```

### MinIO

```php
'minio' => [
    'driver' => 's3',
    'key' => env('MINIO_ACCESS_KEY'),
    'secret' => env('MINIO_SECRET_KEY'),
    'region' => 'us-east-1',
    'bucket' => env('MINIO_BUCKET'),
    'endpoint' => env('MINIO_ENDPOINT'), // http://minio:9000
    'use_path_style_endpoint' => true,
],
```

### DigitalOcean Spaces

```php
'spaces' => [
    'driver' => 's3',
    'key' => env('DO_SPACES_KEY'),
    'secret' => env('DO_SPACES_SECRET'),
    'region' => env('DO_SPACES_REGION'), // e.g. nyc3
    'bucket' => env('DO_SPACES_BUCKET'),
    'endpoint' => env('DO_SPACES_ENDPOINT'), // https://nyc3.digitaloceanspaces.com
],
```

### Backblaze B2 (S3-compatible)

```php
'b2' => [
    'driver' => 's3',
    'key' => env('B2_KEY_ID'),
    'secret' => env('B2_APP_KEY'),
    'region' => env('B2_REGION'), // e.g. us-west-004
    'bucket' => env('B2_BUCKET'),
    'endpoint' => env('B2_ENDPOINT'), // https://s3.us-west-004.backblazeb2.com
],
```

### Local / any other disk

```dotenv
DB_BACKUP_DISK=local
```

## Usage

```bash
# back up config('database-backup.connection')
php artisan db:backup

# back up a specific connection
php artisan db:backup --connection=pgsql

# list what's on the disk
php artisan db:backups
```

Schedule it in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('db:backup')->dailyAt('02:00');
```

### Restore

```bash
# restore the most recent backup for the connection
php artisan db:restore --latest

# restore a specific file from the disk
php artisan db:restore backups/pgsql/pgsql-app-2026-09-01_020000.sql.gz

# skip the "this overwrites the database" prompt (for scripts)
php artisan db:restore --latest --force
```

The file is pulled from the backup disk, gunzipped if needed, then piped into
`psql` / `mysql` (a SQLite file is swapped in atomically and its stale `-wal` /
`-shm` files are removed). PostgreSQL restores run in a single transaction with
`ON_ERROR_STOP`, and the default `pg_dump` options include `--clean --if-exists`
so a restore drops existing objects first. MySQL restores stop at the first
error; mysqldump emits `DROP TABLE IF EXISTS` for every table in the dump, but
tables that exist in the target and **not** in the backup are left in place.

The backup's type must match the connection: a `.sql[.gz]` file for
`pgsql` / `mysql` / `mariadb`, a `.sqlite[.gz]` file for `sqlite`.

## Configuration (`config/database-backup.php` / env)

| Option | Env | Default | Purpose |
| --- | --- | --- | --- |
| `connection` | `DB_BACKUP_CONNECTION` | `DB_CONNECTION` | Connection to dump |
| `disk` | `DB_BACKUP_DISK` | `local` | Destination filesystem disk |
| `path` | `DB_BACKUP_PATH` | slug of `APP_NAME` | Root folder on the disk (so several sites can share one bucket) |
| `compress` | `DB_BACKUP_COMPRESS` | `true` | gzip the dump before upload |
| `retention.keep_last` | `DB_BACKUP_KEEP_LAST` | `7` | Keep at most N backups for the connection (0 = unlimited) |
| `retention.keep_days` | `DB_BACKUP_KEEP_DAYS` | `30` | Delete backups older than N days (0 = off) |
| `timeout` | `DB_BACKUP_TIMEOUT` | `900` | Max seconds for the dump |
| `temp_directory` | `DB_BACKUP_TEMP_DIR` | system temp dir | Local scratch dir (dump/restore) |
| `binaries.*` | `DB_BACKUP_BIN_MYSQLDUMP` / `DB_BACKUP_BIN_MYSQL` / `DB_BACKUP_BIN_PGDUMP` / `DB_BACKUP_BIN_PSQL` / `DB_BACKUP_BIN_MARIADB_DUMP` / `DB_BACKUP_BIN_MARIADB` | on `$PATH` | Paths to `mysqldump` / `mysql` / `pg_dump` / `psql` |
| `extra_options.*` | — | see config | Raw args appended per driver (`mysql`, `mariadb`, `pgsql`) |

Backups are stored per connection as
`{path}/{connection}/{connection}-{database}-{Y-m-d_His}.sql` (`.sqlite` for SQLite,
`.gz` appended when compression is on; `-2`, `-3`, ... is added if two backups land
in the same second). Backups written by older releases directly into `{path}/` are
still listed, restored and pruned when their name matches the connection and
database exactly.

Connections are resolved like Laravel does: `url` / `DB_URL` is expanded, and for
read/write splits the `write` host is used. PostgreSQL `sslmode`, `sslcert`,
`sslkey` and `sslrootcert` are passed to `pg_dump` / `psql`. Local dump files are
created with mode `0600` in a `0700` temp directory.

## Events

- `AmdadulHaq\DatabaseBackup\Events\BackupCompleted` — `connection`, `disk`, `remotePath`, `bytes`
- `AmdadulHaq\DatabaseBackup\Events\BackupFailed` — `connection`, `exception`
- `AmdadulHaq\DatabaseBackup\Events\BackupPruneFailed` — `connection`, `exception` (the backup itself succeeded)
- `AmdadulHaq\DatabaseBackup\Events\RestoreCompleted` — `connection`, `disk`, `remotePath`
- `AmdadulHaq\DatabaseBackup\Events\RestoreFailed` — `connection`, `exception`

```php
use AmdadulHaq\DatabaseBackup\Events\BackupFailed;
use Illuminate\Support\Facades\Event;

Event::listen(BackupFailed::class, function (BackupFailed $event): void {
    logger()->error("DB backup failed for {$event->connection}", [
        'error' => $event->exception->getMessage(),
    ]);
});
```

## Drivers

Each database type is a small class implementing the `Dumper` contract
(`src/Dumpers/`) and the `Restorer` contract (`src/Restorers/`):

| Driver | Backup | Restore |
| --- | --- | --- |
| `pgsql` | `pg_dump` | `psql` |
| `mysql` | `mysqldump` | `mysql` |
| `mariadb` | `mariadb-dump` (falls back to `mysqldump`) | `mariadb` (falls back to `mysql`) |
| `sqlite` | `VACUUM INTO` snapshot | atomic file swap |

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Credits

- [Amdadul Haq](https://github.com/amdad121)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
