<?php

declare(strict_types=1);

namespace AmdadulHaq\DatabaseBackup\Dumpers;

use AmdadulHaq\DatabaseBackup\Exceptions\BackupFailedException;
use Symfony\Component\Process\Process;

abstract class ProcessDumper implements Dumper
{
    /**
     * @param  array<string, string>  $binaries  Overrides for CLI tool paths.
     * @param  array<string, list<string>>  $extraOptions  Extra CLI args, keyed by driver.
     */
    public function __construct(
        protected readonly array $binaries = [],
        protected readonly array $extraOptions = [],
        protected readonly int $timeout = 900,
    ) {}

    public function extension(): string
    {
        return 'sql';
    }

    protected function binary(string $name): string
    {
        return $this->binaries[$name] ?? $name;
    }

    /**
     * @return list<string>
     */
    protected function optionsFor(string $driver): array
    {
        return $this->extraOptions[$driver] ?? [];
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $env
     */
    protected function run(array $command, array $env): void
    {
        $process = new Process($command, null, $env, null, (float) $this->timeout);
        $process->run();

        throw_unless(
            $process->isSuccessful(),
            BackupFailedException::class,
            trim($process->getErrorOutput() ?: $process->getOutput() ?: 'Dump process failed.'),
        );
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    protected function config(array $connection, string $key, string $default = ''): string
    {
        return (string) ($connection[$key] ?? $default);
    }
}
