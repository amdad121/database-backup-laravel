<?php

declare(strict_types=1);

namespace AmdadulHaq\DatabaseBackup\Restorers;

/**
 * Restores a PostgreSQL SQL dump with psql.
 */
final class PostgresRestorer extends ProcessRestorer
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
        return [
            $this->binary('psql'),
            '--host='.$this->config($connection, 'host', '127.0.0.1'),
            '--port='.$this->config($connection, 'port', '5432'),
            '--username='.$this->config($connection, 'username', 'postgres'),
            '--dbname='.$this->config($connection, 'database'),
            '--no-password',
            '--single-transaction',
            '--set=ON_ERROR_STOP=on',
            '--file='.$source,
        ];
    }

    /**
     * @param  array<string, mixed>  $connection
     * @return array<string, string>
     */
    public function env(array $connection): array
    {
        return ['PGPASSWORD' => $this->config($connection, 'password')];
    }
}
