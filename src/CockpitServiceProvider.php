<?php

namespace Debugmate;

use Debugmate\Console\InstallCockpitCommand;
use Debugmate\Console\TestCockpitCommand;
use Debugmate\Context\DumpContext;
use Debugmate\Context\JobContext;
use Debugmate\Context\RequestContext;
use Debugmate\Exceptions\CockpitErrorHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Monolog\Level;
use Monolog\Logger;

class CockpitServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        if (!defined('DEBUGMATE_PATH')) {
            define('DEBUGMATE_PATH', realpath(__DIR__ . '/../'));
        }

        if (!defined('DEBUGMATE_REPO')) {
            define('DEBUGMATE_REPO', 'https://github.com/DebugMate/laravel');
        }

        $this->registerErrorHandler();
        $this->registerContexts();
    }

    public function boot(): void
    {
        $this->bootPublishables()
            ->bootCommands()
            ->bootMacros()
            ->configureQueue();

        $this->mergeConfigFrom(DEBUGMATE_PATH . '/config/cockpit.php', 'cockpit');
    }

    public function bootMacros(): self
    {
        Str::macro('spaceTitle', function (string $value, array $replace = ['_', '.', '-']) {
            return Str::title(Str::replace($replace, ' ', Str::kebab($value)));
        });

        return $this;
    }

    public function bootCommands(): self
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCockpitCommand::class,
                TestCockpitCommand::class,
            ]);
        }

        return $this;
    }

    private function bootPublishables(): self
    {
        if ($this->app->runningInConsole()) {
            $configPath = function_exists('config_path')
                ? config_path('cockpit.php')
                : base_path('config/cockpit.php');

            $this->publishes([
                DEBUGMATE_PATH . '/config/cockpit.php' => $configPath,
            ], 'cockpit-config');

            $this->publishes([
                DEBUGMATE_PATH . '/stubs/CockpitServiceProvider.stub' => app_path('Providers/CockpitServiceProvider.php'),
            ], 'cockpit-provider');
        }

        return $this;
    }

    protected function registerErrorHandler(): void
    {
        $this->app->singleton('cockpit.logger', function () {
            $handler = new CockpitErrorHandler();

            $handler->setMinimumLogLevel(
                $this->getLogLevel()
            );

            return tap(
                new Logger('Cockpit'),
                function (Logger $logger) use ($handler) {
                    return $logger->pushHandler($handler);
                }
            );
        });

        Log::extend('cockpit', function ($app) {
            return $app['cockpit.logger'];
        });
    }

    protected function registerContexts(): void
    {
        $this->app->singleton(JobContext::class);
        $this->app->singleton(DumpContext::class);

        $this->app->bind(RequestContext::class, function ($app) {
            return new RequestContext($app);
        });

        $this->configureContexts();
    }

    protected function configureContexts(): void
    {
        $this->app->make(JobContext::class)->start();
        $this->app->make(DumpContext::class)->start();
    }

    protected function configureQueue(): void
    {
        if (!$this->app->bound('queue')) {
            return;
        }

        $queue = $this->app->get('queue');
        $queue->before([$this, 'resetContexts']);
        $queue->after([$this, 'resetContexts']);
    }

    public function resetContexts(): void
    {
        $this->app->make(JobContext::class)->reset();
        $this->app->make(DumpContext::class)->reset();
    }

    protected function getLogLevel(): Level
    {
        $logLevel = config('logging.channels.cockpit.level', Level::Error->value);

        $logLevel = Level::tryFrom((int)$logLevel);

        if (!$logLevel) {
            throw new InvalidArgumentException('The given log level is invalid');
        }

        return $logLevel;
    }
}
