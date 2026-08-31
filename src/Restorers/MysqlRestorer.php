<?php

declare(strict_types=1);

namespace AmdadulHaq\DatabaseBackup\Restorers;

/**
 * Restores a MySQL / MariaDB SQL dump with the mysql client.
 */
final class MysqlRestorer extends ProcessRestorer
{
    /**
     * @param  array<string, mixed>  $connection
     */
    public function restore(array $connection, string $source): void
    {
        $this->run($this->command($connection, $source), $this->env($connection));
    }

    /**
     * @param  array<string, mixed>  $connection
     * @return list<string>
     */
    public function command(array $connection, string $source): array
    {
        $command = [
            $this->binary('mysql'),
            '--host='.$this->config($connection, 'host', '127.0.0.1'),
            '--port='.$this->config($connection, 'port', '3306'),
            '--user='.$this->config($connection, 'username', 'root'),
        ];

        if ($this->config($connection, 'unix_socket') !== '') {
            $command[] = '--socket='.$this->config($connection, 'unix_socket');
        }

        return [
            ...$command,
            '--execute=SOURCE '.$source,
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
