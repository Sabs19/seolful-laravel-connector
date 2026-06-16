<?php

namespace Seolful\Connector\Concerns;

trait WritesEnvFile
{
    protected function writeEnv(string $key, string $value): void
    {
        $this->writeEnvTo(base_path('.env'), $key, $value);
    }

    protected function writeEnvTo(string $envPath, string $key, string $value): void
    {
        if (! file_exists($envPath)) {
            file_put_contents($envPath, '');
        }

        $contents = (string) file_get_contents($envPath);

        if (preg_match("/^{$key}=.*/m", $contents)) {
            $contents = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $contents);
        } else {
            $contents .= PHP_EOL . "{$key}={$value}";
        }

        file_put_contents($envPath, $contents);
    }
}
