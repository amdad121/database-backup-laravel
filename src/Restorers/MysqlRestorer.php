<?php

declare(strict_types=1);

namespace AmdadulHaq\DatabaseBackup\Restorers;

use AmdadulHaq\DatabaseBackup\Concerns\MysqlBinary;
use AmdadulHaq\DatabaseBackup\Exceptions\RestoreFailedException;

/**
 * Restores a MySQL / MariaDB SQL dump by piping it into the mysql client.
 * In batch mode the client stops at the first error.
 */
final class MysqlRestorer extends ProcessRestorer
{
    use MysqlBinary;

    /**
     * @param  array<string, mixed>  $connection
     */
    public function restore(array $connection, string $source): void
    {
        $input = fopen($source, 'rb');
        throw_if($input === false, RestoreFailedException::class, "Unable to read [{$source}].");

        try {
            $this->run($this->command($connection), $this->env($connection), $input);
        } finally {
            if (is_resource($input)) {
                fclose($input);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $connection
     * @return list<string>
     */
    public function command(array $connection): array
    {
        $command = [
            $this->mysqlBinary($this->config($connection, 'driver', 'mysql'), 'mysql', 'mariadb'),
            '--host='.$this->config($connection, 'host', '127.0.0.1'),
            '--port='.$this->config($connection, 'port', '3306'),
            '--user='.$this->config($connection, 'username', 'root'),
        ];

        if ($this->config($connection, 'unix_socket') !== '') {
            $command[] = '--socket='.$this->config($connection, 'unix_socket');
        }

        return [
            ...$command,
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
