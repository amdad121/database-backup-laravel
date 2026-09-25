<?php

declare(strict_types=1);

namespace AmdadulHaq\DatabaseBackup\Concerns;

trait PostgresEnvironment
{
    /**
     * libpq env vars for the password and the connection's SSL settings.
     *
     * @param  array<string, mixed>  $connection
     * @return array<string, string>
     */
    public function env(array $connection): array
    {
        $env = ['PGPASSWORD' => (string) ($connection['password'] ?? '')];

        foreach (['sslmode' => 'PGSSLMODE', 'sslcert' => 'PGSSLCERT', 'sslkey' => 'PGSSLKEY', 'sslrootcert' => 'PGSSLROOTCERT'] as $key => $var) {
            if (($connection[$key] ?? '') !== '' && is_scalar($connection[$key])) {
                $env[$var] = (string) $connection[$key];
            }
        }

        return $env;
    }
}
