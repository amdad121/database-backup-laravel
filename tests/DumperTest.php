<?php

declare(strict_types=1);

use AmdadulHaq\DatabaseBackup\Concerns\MysqlClient;
use AmdadulHaq\DatabaseBackup\Dumpers\MysqlDumper;
use AmdadulHaq\DatabaseBackup\Dumpers\PostgresDumper;
use AmdadulHaq\DatabaseBackup\Dumpers\SqliteDumper;
use AmdadulHaq\DatabaseBackup\Exceptions\BackupFailedException;

it('builds the mysqldump command', function (): void {
    $dumper = new MysqlDumper(
        extraOptions: ['mysql' => ['--single-transaction', '--quick']],
    );

    $command = $dumper->command([
        'driver' => 'mysql',
        'host' => 'db.internal',
        'port' => 3307,
        'username' => 'backup',
        'password' => 's3cret',
        'database' => 'shop',
    ], '/tmp/out.sql');

    expect($command)->toBe([
        'mysqldump',
        '--host=db.internal',
        '--port=3307',
        '--user=backup',
        '--result-file=/tmp/out.sql',
        '--single-transaction',
        '--quick',
        'shop',
    ]);
});

it('adds --socket when the mysql connection uses a unix socket', function (): void {
    $command = (new MysqlDumper)->command([
        'driver' => 'mysql',
        'database' => 'shop',
        'unix_socket' => '/var/run/mysqld/mysqld.sock',
    ], '/tmp/out.sql');

    expect($command)->toContain('--socket=/var/run/mysqld/mysqld.sock');
});

it('uses the mariadb extra options and a custom binary path', function (): void {
    $dumper = new MysqlDumper(
        binaries: ['mysqldump' => '/usr/local/bin/mariadb-dump'],
        extraOptions: ['mariadb' => ['--skip-lock-tables']],
    );

    $command = $dumper->command(['driver' => 'mariadb', 'database' => 'shop'], '/tmp/out.sql');

    expect($command[0])->toBe('/usr/local/bin/mariadb-dump')
        ->and($command)->toContain('--skip-lock-tables')
        ->and($command)->not->toContain('--single-transaction');
});

it('builds the pg_dump command and password env', function (): void {
    $dumper = new PostgresDumper(
        extraOptions: ['pgsql' => ['--no-owner', '--no-privileges']],
    );

    $command = $dumper->command([
        'driver' => 'pgsql',
        'host' => 'pg.internal',
        'port' => 5433,
        'username' => 'backup',
        'password' => 'p@ss',
        'database' => 'shop',
    ], '/tmp/out.sql');

    expect($command)->toBe([
        'pg_dump',
        '--host=pg.internal',
        '--port=5433',
        '--username=backup',
        '--dbname=shop',
        '--file=/tmp/out.sql',
        '--no-password',
        '--no-owner',
        '--no-privileges',
    ])->and($dumper->env(['password' => 'p@ss']))->toBe(['PGPASSWORD' => 'p@ss']);
});

it('falls back to sensible pg_dump defaults', function (): void {
    $command = (new PostgresDumper)->command(['driver' => 'pgsql', 'database' => 'shop'], '/tmp/out.sql');

    expect($command)->toBe([
        'pg_dump',
        '--host=127.0.0.1',
        '--port=5432',
        '--username=postgres',
        '--dbname=shop',
        '--file=/tmp/out.sql',
        '--no-password',
    ]);
});

it('passes the password through an option file as the first mysqldump option', function (): void {
    $command = (new MysqlDumper)->command(['driver' => 'mysql', 'database' => 'shop'], '/tmp/out.sql', '/tmp/my.cnf');

    expect($command[1])->toBe('--defaults-extra-file=/tmp/my.cnf')
        ->and(implode(' ', $command))->not->toContain('secret');
});

it('maps the PDO ssl options to mysqldump flags', function (): void {
    $command = (new MysqlDumper)->command([
        'driver' => 'mysql',
        'database' => 'shop',
        'options' => [1009 => '/certs/ca.pem', 1008 => '/certs/client.pem', 1007 => '/certs/client.key', 1014 => true],
    ], '/tmp/out.sql');

    expect($command)->toContain('--ssl-ca=/certs/ca.pem', '--ssl-cert=/certs/client.pem', '--ssl-key=/certs/client.key', '--ssl-mode=VERIFY_IDENTITY');
});

it('uses the mariadb flag for server certificate verification', function (): void {
    $command = (new MysqlDumper)->command(['driver' => 'mariadb', 'database' => 'shop', 'options' => [1014 => true]], '/tmp/out.sql');

    expect($command)->toContain('--ssl-verify-server-cert');
});

it('writes a private option file with the escaped password and removes it', function (): void {
    $dumper = new class
    {
        use MysqlClient;

        protected function failure(): string
        {
            return RuntimeException::class;
        }

        public function peek(array $connection): array
        {
            return $this->withDefaultsFile($connection, fn (string $file): array => [$file, file_get_contents($file), fileperms($file) & 0777]);
        }
    };

    [$file, $contents, $mode] = $dumper->peek(['password' => 'p\\a"ss']);

    expect($contents)->toBe("[client]\npassword=\"p\\\\a\"ss\"\n")
        ->and($mode)->toBe(0600)
        ->and(file_exists($file))->toBeFalse();
});

it('wraps a PDOException with a string SQLSTATE code when a sqlite backup fails', function (): void {
    $database = sys_get_temp_dir().'/not-a-db-'.bin2hex(random_bytes(4)).'.sqlite';
    file_put_contents($database, str_repeat('garbage', 1024));

    try {
        (new SqliteDumper)->dump(['database' => $database], $database.'.bak');
    } finally {
        @unlink($database);
    }
})->throws(BackupFailedException::class, 'Unable to back up SQLite database');
