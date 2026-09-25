<?php

declare(strict_types=1);

namespace AmdadulHaq\DatabaseBackup\Dumpers;

use AmdadulHaq\DatabaseBackup\Concerns\MysqlBinary;

/**
 * Dumps MySQL and MariaDB connections with mysqldump.
 */
final class MysqlDumper extends ProcessDumper
{
    use MysqlBinary;

    /**
     * @param  array<string, mixed>  $connection
     */
    public function dump(array $connection, string $target): void
    {
        $this->run($this->command($connection, $target), $this->env($connection));
    }

    /**
     * @param  array<string, mixed>  $connection
     * @return list<string>
     */
    public function command(array $connection, string $target): array
    {
        $command = [
            $this->mysqlBinary($this->config($connection, 'driver', 'mysql'), 'mysqldump', 'mariadb-dump'),
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
            ...$this->optionsFor($this->config($connection, 'driver', 'mysql')),
            $this->config($connection, 'database'),
        ];
    }

    /**
     * @param  array<string, mixed>  $connection
     * @return array<string, string>
     */
    public function env(array $connection): array
    {
        return ['MYSQL_PWD' => $this->config($connection, 'password')];
    }
}
