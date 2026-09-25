<?php

declare(strict_types=1);

namespace AmdadulHaq\DatabaseBackup\Concerns;

use Symfony\Component\Process\ExecutableFinder;
use Throwable;

trait MysqlClient
{
    /**
     * PDO::MYSQL_ATTR_SSL_* values (Pdo\Mysql::ATTR_SSL_* on PHP 8.4+), mapped to
     * client flags. Literal ints so pdo_mysql need not be loaded.
     *
     * @var array<int, string>
     */
    private static array $sslOptions = [
        1007 => '--ssl-key',
        1008 => '--ssl-cert',
        1009 => '--ssl-ca',
        1010 => '--ssl-capath',
        1011 => '--ssl-cipher',
    ];

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

    /**
     * TLS flags from the connection's PDO options.
     *
     * @param  array<string, mixed>  $connection
     * @return list<string>
     */
    protected function sslFlags(array $connection): array
    {
        $options = is_array($connection['options'] ?? null) ? $connection['options'] : [];
        $flags = [];

        foreach (self::$sslOptions as $attribute => $flag) {
            if (is_string($options[$attribute] ?? null) && $options[$attribute] !== '') {
                $flags[] = $flag.'='.$options[$attribute];
            }
        }

        // PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT
        if (($options[1014] ?? null) === true) {
            $flags[] = ($connection['driver'] ?? 'mysql') === 'mariadb' ? '--ssl-verify-server-cert' : '--ssl-mode=VERIFY_IDENTITY';
        }

        return $flags;
    }

    /**
     * Run $callback with a private option file holding the password, passed as
     * --defaults-extra-file (MYSQL_PWD is deprecated and visible in the environment).
     *
     * @template T
     *
     * @param  array<string, mixed>  $connection
     * @param  callable(string): T  $callback
     * @return T
     */
    protected function withDefaultsFile(array $connection, callable $callback): mixed
    {
        $file = sys_get_temp_dir().DIRECTORY_SEPARATOR.'db-backup-my-'.bin2hex(random_bytes(12)).'.cnf';

        // "x" refuses to follow a pre-existing file or symlink at that path.
        $umask = umask(0077);
        $handle = @fopen($file, 'x');
        umask($umask);
        throw_if($handle === false, $this->failure(), 'Unable to create a MySQL option file.');

        try {
            chmod($file, 0600);
            $password = strtr((string) ($connection['password'] ?? ''), ['\\' => '\\\\', '"' => '\\"', "\n" => '\\n', "\r" => '\\r', "\t" => '\\t']);
            $written = fwrite($handle, "[client]\npassword=\"{$password}\"\n");
            fclose($handle);
            throw_if($written === false, $this->failure(), 'Unable to write the MySQL option file.');

            return $callback($file);
        } finally {
            @unlink($file);
        }
    }

    /**
     * @return class-string<Throwable>
     */
    abstract protected function failure(): string;
}
