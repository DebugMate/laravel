<?php

namespace Debugmate\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class InstallDebugmateCommand extends Command
{
    protected $signature = 'debugmate:install
        {--C|config : Install the config file}
        {--P|provider : Install service provider}
        {--F|force : Overwrite existing files}';

    protected $description = 'Create the config files for DebugMate.';

    public function handle(): void
    {
        $this->info('Installing DebugMate...');

        $this->publishConfig();
        $this->publishProvider();
        $this->publishEnv();

        $this->info('Installed DebugMate.');
    }

    private function publishConfig(): void
    {
        $configPath = function_exists('config_path')
            ? config_path('debugmate.php')
            : base_path('config/debugmate.php');

        if (!$this->option('config') && $this->fileExists($configPath)) {
            return;
        }

        $this->publish('configuration', $configPath);
    }

    private function publishProvider(): void
    {
        if (!$this->option('provider')) {
            return;
        }

        $providerPath = app_path('Providers/DebugmateServiceProvider.php');

        $this->publish('provider', $providerPath);
        $this->registerDebugmateServiceProvider();
    }

    private function publishEnv(): void
    {
        $env = base_path('.env');

        if (!file_exists($env)) {
            return;
        }

        $envContent = file_get_contents($env);

        if (Str::contains($envContent, 'DEBUGMATE_DOMAIN')) {
            $this->info('Required env vars already exist');

            return;
        }

        $envContent .= PHP_EOL;
        $envContent .= 'DEBUGMATE_DOMAIN=http://localhost' . PHP_EOL;
        $envContent .= 'DEBUGMATE_ENABLED=true' . PHP_EOL;
        $envContent .= 'DEBUGMATE_TOKEN=' . PHP_EOL;

        file_put_contents($env, $envContent);

        $this->info('Env variables has been set on your .env file');
    }

    private function publish(string $fileType, string $path): void
    {
        $lowerFileType = Str::lower($fileType);
        $titleFileType = Str::title($fileType);

        if (!$this->fileExists($path)) {
            $this->publishFile($lowerFileType);

            return;
        }

        if ($this->shouldOverwrite($titleFileType)) {
            $this->publishFile($lowerFileType, true);
        }
    }

    private function fileExists(string $path): bool
    {
        return File::exists($path);
    }

    private function shouldOverwrite(string $fileType): bool
    {
        return $this->option('force')
            || $this->confirm("{$fileType} file already exists. Do you want to overwrite it?", false);
    }

    private function publishFile(string $fileType, bool $forcePublish = false): void
    {
        if ($fileType == 'configuration') {
            $fileType = 'config';
        }

        $params = [
            '--provider' => "Debugmate\DebugmateServiceProvider",
            '--tag'      => "debugmate-{$fileType}",
        ];

        if ($forcePublish === true) {
            $params['--force'] = true;
        }

        $this->call('vendor:publish', $params);
    }

    private function registerDebugmateServiceProvider(): void
    {
        $namespace = Str::replaceLast('\\', '', $this->laravel->getNamespace());

        $serviceProviderPath = app_path('Providers/DebugmateServiceProvider.php');

        file_put_contents(
            $serviceProviderPath,
            str_replace(
                "namespace App\Providers;",
                "namespace {$namespace}\Providers;",
                file_get_contents($serviceProviderPath)
            )
        );

        ServiceProvider::addProviderToBootstrapFile("{$namespace}\Providers\DebugmateServiceProvider");
    }
}
