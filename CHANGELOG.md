# Changelog

All notable changes to `database-backup-laravel` will be documented in this file.

## v1.2.0

### Added

- `db:backups` command — lists the backups stored on the disk for the connection (file, size, modified), newest first.

### Changed

- Default `pg_dump` options now include `--clean --if-exists`, so a PostgreSQL restore drops existing objects first instead of failing on "already exists".

## v1.1.0

### Added

- `db:restore` command — pulls a backup from the disk, gunzips it if needed, and restores it into the connection.
  - `db:restore --latest` restores the most recent backup for the connection; `db:restore <path>` restores a specific file.
  - Confirms before overwriting; `--force` skips the prompt for scripts/CI.
  - Driver-based restorers (`src/Restorers/`, `Restorer` contract): `PostgresRestorer` (`psql`, single transaction + `ON_ERROR_STOP`), `MysqlRestorer` (`mysql`), `SqliteRestorer` (file copy).
- `binaries.mysql` / `binaries.psql` config (`DB_BACKUP_BIN_MYSQL`, `DB_BACKUP_BIN_PSQL`) for the restore clients.

## v1.0.2

- `temp_directory` now defaults to the system temp dir instead of `storage/app/database-backup`, so nothing is written into the app tree.

## v1.0.1

- Allow `symfony/process` `^8.0`.

## v1.0.0 - Initial release

### Added

- `db:backup` Artisan command that dumps one database connection and stores it on a Laravel filesystem disk (local by default, or any S3-compatible provider — Cloudflare R2, MinIO, DigitalOcean Spaces, Backblaze B2, AWS S3).
- Driver-based dumpers (`src/Dumpers/`, all implementing the `Dumper` contract): `PostgresDumper` (`pg_dump`), `MysqlDumper` (`mysqldump`, handles `mysql` and `mariadb`), `SqliteDumper` (file copy).
- Optional gzip compression (streamed, via PHP's zlib — no `gzip` binary needed).
- Retention pruning after each successful upload: `keep_last` and `keep_days`.
- Backups stored under a root folder that defaults to a slug of `APP_NAME`, so several sites can share one bucket. Files named `{connection}-{database}-{Y-m-d_His}.sql[.gz]`.
- Configurable connection, disk, path, compression, retention, dump timeout, temp directory, `mysqldump`/`pg_dump` paths, and per-driver extra dump options — all overridable via env.
- `BackupCompleted` and `BackupFailed` events.
- Test suite (Pest + Orchestra Testbench) and CI across PHP 8.2-8.5 and Laravel 11/12/13; Pint, Larastan (level 8), Rector.
