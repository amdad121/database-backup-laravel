<?php

declare(strict_types=1);

namespace AmdadulHaq\DatabaseBackup\Restorers;

use AmdadulHaq\DatabaseBackup\Exceptions\RestoreFailedException;
use Symfony\Component\Process\Process;

abstract class ProcessRestorer implements Restorer
{
    /**
     * @param  array<string, string>  $binaries  Overrides for CLI tool paths.
     */
    public function __construct(
        protected readonly array $binaries = [],
        protected readonly int $timeout = 900,
    ) {}

    protected function binary(string $name): string
    {
        return $this->binaries[$name] ?? $name;
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    protected function config(array $connection, string $key, string $default = ''): string
    {
        return (string) ($connection[$key] ?? $default);
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $env
     * @param  resource|null  $input  Stream piped to the process's stdin.
     */
    protected function run(array $command, array $env, $input = null): void
    {
        $process = new Process($command, null, $env, $input, (float) $this->timeout);
        $process->run();

        throw_unless(
            $process->isSuccessful(),
            RestoreFailedException::class,
            trim($process->getErrorOutput() ?: $process->getOutput() ?: 'Restore process failed.'),
        );
    }
}
