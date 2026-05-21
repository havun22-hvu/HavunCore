<?php

namespace App\Console\Commands\Concerns;

trait NormalizesPath
{
    private function normalizePath(string $path): string
    {
        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}
