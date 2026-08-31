<?php

declare(strict_types=1);

namespace AmdadulHaq\DatabaseBackup\Dumpers;

/**
 * Dumps PostgreSQL connections with pg_dump.
 */
final class PostgresDumper extends ProcessDumper
{
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
        return [
            $this->binary('pg_dump'),
            '--host='.$this->config($connection, 'host', '127.0.0.1'),
            '--port='.$this->config($connection, 'port', '5432'),
            '--username='.$this->config($connection, 'username', 'postgres'),
            '--dbname='.$this->config($connection, 'database'),
            '--file='.$target,
            '--no-password',
            ...$this->optionsFor('pgsql'),
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
