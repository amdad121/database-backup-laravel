<?php

declare(strict_types=1);

namespace AmdadulHaq\DatabaseBackup\Dumpers;

use AmdadulHaq\DatabaseBackup\Concerns\MysqlClient;
use AmdadulHaq\DatabaseBackup\Exceptions\BackupFailedException;

/**
 * Dumps MySQL and MariaDB connections with mysqldump.
 */
final class MysqlDumper extends ProcessDumper
{
    use MysqlClient;

    /**
     * @param  array<string, mixed>  $connection
     */
    public function dump(array $connection, string $target): void
    {
        $this->withDefaultsFile($connection, fn (string $defaults) => $this->run($this->command($connection, $target, $defaults), []));
    }

    /**
     * @param  array<string, mixed>  $connection
     * @param  string|null  $defaults  Option file holding the password.
     * @return list<string>
     */
    public function command(array $connection, string $target, ?string $defaults = null): array
    {
        $driver = $this->config($connection, 'driver', 'mysql');
        $command = [$this->mysqlBinary($driver, 'mysqldump', 'mariadb-dump')];

        if ($defaults !== null) {
            // Must be the first option.
            $command[] = '--defaults-extra-file='.$defaults;
        }

        $command = [
            ...$command,
            '--host='.$this->config($connection, 'host', '127.0.0.1'),
            '--port='.$this->config($connection, 'port', '3306'),
            '--user='.$this->config($connection, 'username', 'root'),
            '--result-file='.$target,
        ];

        if ($this->config($connection, 'unix_socket') !== '') {
            $command[] = '--socket='.$this->config($connection, 'unix_socket');
        }

        return [
            ...$command,
            ...$this->sslFlags($connection),
            ...$this->optionsFor($driver),
            $this->config($connection, 'database'),
        ];
    }

    protected function failure(): string
    {
        return BackupFailedException::class;
    }
}
