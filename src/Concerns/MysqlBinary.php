<?php

declare(strict_types=1);

namespace AmdadulHaq\DatabaseBackup\Concerns;

use Symfony\Component\Process\ExecutableFinder;

trait MysqlBinary
{
    /**
     * Resolve the client binary for the driver. MariaDB 11+ deprecates the
     * mysql* names, so for the mariadb driver prefer an explicit mariadb-*
     * path, then a customised mysql* path, then mariadb-* on $PATH.
     *
     * @param  string  $mysql  e.g. "mysqldump"
     * @param  string  $mariadb  e.g. "mariadb-dump"
     */
    protected function mysqlBinary(string $driver, string $mysql, string $mariadb): string
    {
        if ($driver !== 'mariadb') {
            return $this->binaries[$mysql] ?? $mysql;
        }

        if (($this->binaries[$mariadb] ?? '') !== '') {
            return $this->binaries[$mariadb];
        }

        if (($this->binaries[$mysql] ?? $mysql) !== $mysql) {
            return $this->binaries[$mysql];
        }

        return (new ExecutableFinder)->find($mariadb) !== null ? $mariadb : $mysql;
    }
}
