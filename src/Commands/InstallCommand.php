<?php

namespace Seolful\Connector\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Seolful\Connector\Concerns\WritesEnvFile;
use Throwable;

class InstallCommand extends Command
{
    use WritesEnvFile;

    protected $signature = 'seolful:install
        {key? : Connection key from your Seolful dashboard}
        {--nextjs-url= : Next.js revalidation endpoint URL (e.g. https://yourapp.com/api/seolful/revalidate)}
        {--nextjs-secret= : Shared secret for the revalidation webhook (auto-generated if blank)}
        {--nextjs-path= : Path to your Next.js app — writes integration files automatically}
        {--nextjs-token= : Token for the page-seo read endpoint (auto-generated if blank)}';

    protected $description = 'Install Seolful: run migrations and connect in one step';

    public function handle(): int
    {
        $this->newLine();
        $this->components->info('Installing Seolful');
        $this->newLine();

        // Step 1 — migrations
        $this->components->task('Running migrations', function () {
            try {
                Artisan::call('migrate', [
                    '--path'  => 'vendor/seolful/laravel-connector/database/migrations',
                    '--force' => true,
                ], $this->output);
                return true;
            } catch (Throwable $e) {
                $this->newLine();
                $this->components->error('Migration failed: ' . $e->getMessage());
                return false;
            }
        });

        $this->newLine();

        // Step 2 — connect (delegates to ConnectCommand)
        $args = [];
        if ($key = trim((string) $this->argument('key'))) {
            $args['key'] = $key;
        }

        $result = $this->call('seolful:connect', $args);

        if ($result !== self::SUCCESS) {
            return $result;
        }

        // Step 3 — optional Next.js setup
        $this->configureNextJs();

        return self::SUCCESS;
    }

    // -------------------------------------------------------------------------

    private function configureNextJs(): void
    {
        $url    = $this->option('nextjs-url');
        $secret = $this->option('nextjs-secret');
        $path   = $this->option('nextjs-path');
        $token  = $this->option('nextjs-token');

        $hasFlags = $url !== null || $path !== null;

        // Non-interactive with no flags — skip silently
        if (! $hasFlags && ! $this->input->isInteractive()) {
            return;
        }

        // Interactive — ask if they use a Next.js frontend
        if (! $hasFlags) {
            $this->newLine();
            if (! $this->confirm('Do you use a Next.js frontend?', false)) {
                return;
            }
            $url  = $this->ask('Next.js revalidation URL');
            $path = $this->ask('Path to your Next.js app (leave blank to skip file setup)') ?: null;
        }

        if (! $url && ! $path) {
            return;
        }

        // Resolve secret
        if ($secret === null && $this->input->isInteractive()) {
            $secret = $this->ask('Revalidation secret (leave blank to auto-generate)') ?: null;
        }
        $secret = $secret ?: Str::random(32);

        // Write webhook config to Laravel .env
        if ($url) {
            $this->newLine();
            $this->components->task('Writing webhook config to .env', function () use ($url, $secret) {
                $this->writeEnv('SEOLFUL_REVALIDATE_URL', $url);
                $this->writeEnv('SEOLFUL_REVALIDATE_SECRET', $secret);
                return true;
            });
        }

        // Write integration files to the Next.js app
        if ($path) {
            $resolvedPath = realpath($path) ?: $path;

            if (! $this->isNextJsProject($resolvedPath)) {
                $this->newLine();
                $this->components->warn("No Next.js project found at {$resolvedPath} — skipping file generation.");
                $this->line('  Run <fg=yellow>npx seolful-next init</> inside your Next.js app instead.');
            } else {
                if ($token === null && $this->input->isInteractive()) {
                    $token = $this->ask('Next.js token (leave blank to auto-generate)') ?: null;
                }
                $token = $token ?: Str::random(40);

                $this->components->task('Writing integration files', function () use ($resolvedPath, $token, $secret) {
                    $this->writeEnv('SEOLFUL_NEXTJS_TOKEN', $token);
                    $this->writeNextJsFiles($resolvedPath, $token, $secret);
                    return true;
                });

                $this->newLine();
                $this->components->twoColumnDetail('<fg=gray>Files written to</>', $resolvedPath);
                $this->components->twoColumnDetail('<fg=gray>Next.js token</>', $token);
            }
        }

        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>Revalidation URL</>', $url ?? '(not set)');
        $this->components->twoColumnDetail('<fg=gray>Secret</>', $secret);
        $this->newLine();

        if (! $path) {
            $connectorUrl = rtrim((string) config('app.url'), '/');
            $this->line('  <fg=yellow>Add these to your Next.js app .env.local:</>');
            $this->line("  SEOLFUL_CONNECTOR_URL={$connectorUrl}");
            $this->line("  SEOLFUL_REVALIDATE_SECRET={$secret}");
            $this->newLine();
            $this->line('  Or run <fg=yellow>npx seolful-next init</> inside your Next.js app.');
            $this->newLine();
        }
    }

    private function isNextJsProject(string $path): bool
    {
        $pkg = $path . '/package.json';
        if (! file_exists($pkg)) {
            return false;
        }
        $json = json_decode((string) file_get_contents($pkg), true);
        return isset($json['dependencies']['next']) || isset($json['devDependencies']['next']);
    }

    private function writeNextJsFiles(string $path, string $token, string $secret): void
    {
        $connectorUrl = rtrim((string) config('app.url'), '/');
        $envLocal     = $path . '/.env.local';

        $this->writeEnvTo($envLocal, 'SEOLFUL_CONNECTOR_URL', $connectorUrl);
        $this->writeEnvTo($envLocal, 'SEOLFUL_NEXTJS_TOKEN', $token);
        $this->writeEnvTo($envLocal, 'SEOLFUL_REVALIDATE_SECRET', $secret);

        $this->putFile($path . '/lib/seolful.ts', $this->seolfulTsContent());
        $this->putFile($path . '/components/SeolfulImage.tsx', $this->seolfulImageContent());
        $this->putFile($path . '/app/api/seolful/revalidate/route.ts', $this->revalidateRouteContent());
    }

    private function putFile(string $filePath, string $content): void
    {
        $dir = dirname($filePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($filePath, $content);
    }

    private function seolfulTsContent(): string
    {
        return <<<'TS'
export interface SeolfulImageAlt {
  src: string
  alt: string
  missing: boolean
}

export interface SeolfulPageSeo {
  found: boolean
  title: string | null
  meta_description: string | null
  structured_data: object[]
  demote_h1: boolean
  image_alts: SeolfulImageAlt[]
}

export async function getSeolfulPageSeo(url: string): Promise<SeolfulPageSeo | null> {
  try {
    const res = await fetch(
      `${process.env.SEOLFUL_CONNECTOR_URL}/api/seolful/v1/page-seo?url=${encodeURIComponent(url)}`,
      {
        headers: {
          'X-Seolful-Nextjs-Token': process.env.SEOLFUL_NEXTJS_TOKEN ?? '',
        },
        next: { tags: ['seolful'] },
      }
    )
    if (!res.ok) return null
    return res.json()
  } catch {
    return null
  }
}
TS;
    }

    private function seolfulImageContent(): string
    {
        return <<<'TSX'
import Image, { ImageProps } from 'next/image'
import { SeolfulImageAlt } from '@/lib/seolful'

type Props = ImageProps & {
  seolfulAlts?: SeolfulImageAlt[]
}

export function SeolfulImage({ src, seolfulAlts, alt, ...props }: Props) {
  const resolved = seolfulAlts?.find((a) => a.src === String(src))?.alt ?? alt
  return <Image src={src} alt={resolved ?? ''} {...props} />
}
TSX;
    }

    private function revalidateRouteContent(): string
    {
        return <<<'TS'
import { revalidatePath } from 'next/cache'
import { NextRequest } from 'next/server'

export async function POST(request: NextRequest) {
  try {
    const { url, secret } = await request.json()
    if (!secret || secret !== process.env.SEOLFUL_REVALIDATE_SECRET) {
      return Response.json({ error: 'Unauthorized' }, { status: 401 })
    }
    const pathname = new URL(url).pathname
    revalidatePath(pathname)
    return Response.json({ revalidated: true, path: pathname })
  } catch {
    return Response.json({ error: 'Invalid request' }, { status: 400 })
  }
}
TS;
    }
}
