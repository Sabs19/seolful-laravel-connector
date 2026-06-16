<?php

namespace Seolful\Connector\Concerns;

trait WritesEnvFile
{
    protected function writeEnv(string $key, string $value): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            return;
        }

        $contents = file_get_contents($envPath);

        if (preg_match("/^{$key}=.*/m", $contents)) {
            $contents = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $contents);
        } else {
            $contents .= PHP_EOL . "{$key}={$value}";
        }

        file_put_contents($envPath, $contents);
    }
}
