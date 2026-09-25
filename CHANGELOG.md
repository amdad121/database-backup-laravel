# Changelog

All notable changes to `database-backup-laravel` will be documented in this file.

## v2.0.0

### Fixed

- Pruning, `db:backups` and `db:restore --latest` no longer mix up connections whose names share a prefix (e.g. `pgsql` and `pgsql-replica`), which could delete or restore another connection's backups. Backups are now stored in a per-connection folder (`{path}/{connection}/`); legacy files in `{path}/` are still recognised when the name matches exactly.
- SQLite backups use `VACUUM INTO`, so committed data still in the WAL file is included and the snapshot is consistent.
- SQLite restores swap the file in atomically and remove stale `-wal` / `-shm` / `-journal` files that could corrupt the restored database.
- gzip / gunzip / download now fail on short or failed writes (e.g. a full disk) instead of uploading a truncated backup.
- Connections configured with `url` / `DB_URL` or read/write hosts are resolved correctly.
- Local dump files are no longer world-readable (temp dir `0700`, files `0600`).
- `db:restore` reports every failure (e.g. temp dir errors, disk exceptions) as a clean error; `db:backups` does the same for disk errors.
- MySQL / MariaDB restores pipe the dump to stdin instead of `SOURCE <path>`, so temp paths with spaces work.
- Two backups in the same second (even concurrent ones) no longer overwrite each other: names get a short random suffix; "latest" and retention order by the timestamp in the file name.
- A truncated `.gz` backup is rejected (gzip trailer size check) instead of restoring a partial dump.
- Each backup gets a `.sha256` checksum, verified before restore and pruned with the backup; the upload size is verified too.
- The per-connection folder uses the connection name as-is, so `mysql_old` and `mysql-old` no longer share one.
- A restored SQLite database that did not exist before is created `0644` so the web server can read it.
- The default temp directory is per user (`database-backup-{uid}`), so one created by root's cron no longer blocks other users.
- A pruning error no longer marks a successful backup as failed; `BackupPruneFailed` is dispatched instead.
- `db:restore` refuses a backup whose type does not match the connection's driver.

### Added

- `RestoreCompleted`, `RestoreFailed` and `BackupPruneFailed` events.
- PostgreSQL `sslmode` / `sslcert` / `sslkey` / `sslrootcert` are passed to `pg_dump` / `psql`.
- MySQL / MariaDB SSL PDO options are passed as `--ssl-*` flags.
- A clear error when SQLite is older than 3.27 (needed for `VACUUM INTO`).
- MariaDB connections use `mariadb-dump` / `mariadb` when available (`binaries.mariadb-dump` / `binaries.mariadb`).
- `db:backups` shows human-readable sizes (`Number::fileSize()`) and times in the app timezone.

### Changed

- **Dropped Laravel 11 support** (it no longer receives security fixes); Laravel 12 and 13 are supported.
- The MySQL password is passed via a private `--defaults-extra-file` instead of the deprecated `MYSQL_PWD`.
- Requires `ext-intl` and `ext-zlib`.
- gzip level lowered from 9 to 6 (much faster, near-identical size).

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
