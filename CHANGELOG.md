# Changelog

All notable changes to `database-backup-laravel` will be documented in this file.

## v1.0.0 - Initial release

### Added

- `db:backup` Artisan command that dumps one database connection and stores it on a Laravel filesystem disk (local by default, or any S3-compatible provider — Cloudflare R2, MinIO, DigitalOcean Spaces, Backblaze B2, AWS S3).
- Driver-based dumpers (`src/Dumpers/`, all implementing the `Dumper` contract): `PostgresDumper` (`pg_dump`), `MysqlDumper` (`mysqldump`, handles `mysql` and `mariadb`), `SqliteDumper` (file copy).
- Optional gzip compression (streamed, via PHP's zlib — no `gzip` binary needed).
- Retention pruning after each successful upload: `keep_last` and `keep_days`.
- Backups stored under a root folder that defaults to a slug of `APP_NAME`, so several sites can share one bucket. Files named `{connection}-{database}-{Y-m-d_His}.sql[.gz]`.
- Configurable connection, disk, path, compression, retention, dump timeout, temp directory, `mysqldump`/`pg_dump` paths, and per-driver extra dump options — all overridable via env.
- `BackupCompleted` and `BackupFailed` events.
- Test suite (Pest + Orchestra Testbench) covering the SQLite backup pipeline end to end and the exact `mysqldump` / `pg_dump` command + env each driver builds; CI across PHP 8.2-8.5 and Laravel 11/12/13; Pint, Larastan (level 8), Rector.
