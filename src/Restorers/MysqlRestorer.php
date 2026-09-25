<?php

declare(strict_types=1);

namespace AmdadulHaq\DatabaseBackup\Restorers;

use AmdadulHaq\DatabaseBackup\Concerns\MysqlClient;
use AmdadulHaq\DatabaseBackup\Exceptions\RestoreFailedException;

/**
 * Restores a MySQL / MariaDB SQL dump by piping it into the mysql client.
 * In batch mode the client stops at the first error. DDL is not
 * transactional in MySQL, so a failed restore can leave a partial database.
 */
final class MysqlRestorer extends ProcessRestorer
{
    use MysqlClient;

    /**
     * @param  array<string, mixed>  $connection
     */
    public function restore(array $connection, string $source): void
    {
        $input = fopen($source, 'rb');
        throw_if($input === false, RestoreFailedException::class, "Unable to read [{$source}].");

        try {
            $this->withDefaultsFile($connection, fn (string $defaults) => $this->run($this->command($connection, $defaults), [], $input));
        } finally {
            if (is_resource($input)) {
                fclose($input);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $connection
     * @param  string|null  $defaults  Option file holding the password.
     * @return list<string>
     */
    public function command(array $connection, ?string $defaults = null): array
    {
        $command = [$this->mysqlBinary($this->config($connection, 'driver', 'mysql'), 'mysql', 'mariadb')];

        if ($defaults !== null) {
            // Must be the first option.
            $command[] = '--defaults-extra-file='.$defaults;
        }

        $command = [
            ...$command,
            '--host='.$this->config($connection, 'host', '127.0.0.1'),
            '--port='.$this->config($connection, 'port', '3306'),
            '--user='.$this->config($connection, 'username', 'root'),
        ];

        if ($this->config($connection, 'unix_socket') !== '') {
            $command[] = '--socket='.$this->config($connection, 'unix_socket');
        }

        return [
            ...$command,
            ...$this->sslFlags($connection),
            $this->config($connection, 'database'),
        ];
    }

    protected function failure(): string
    {
        return RestoreFailedException::class;
    }
}
