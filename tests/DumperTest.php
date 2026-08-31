<?php

declare(strict_types=1);

use AmdadulHaq\DatabaseBackup\Dumpers\MysqlDumper;
use AmdadulHaq\DatabaseBackup\Dumpers\PostgresDumper;

it('builds the mysqldump command and password env', function (): void {
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
    ])->and($dumper->env(['password' => 's3cret']))->toBe(['MYSQL_PWD' => 's3cret']);
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
