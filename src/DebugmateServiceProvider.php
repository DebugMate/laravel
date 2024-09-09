<?php

namespace Debugmate;

use Debugmate\Console\InstallDebugmateCommand;
use Debugmate\Console\TestDebugmateCommand;
use Debugmate\Context\DumpContext;
use Debugmate\Context\JobContext;
use Debugmate\Context\RequestContext;
use Debugmate\Exceptions\DebugmateErrorHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Monolog\Level;
use Monolog\Logger;

class DebugmateServiceProvider extends BaseServiceProvider
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
        $this->setLogChannel();
    }

    public function boot(): void
    {
        $this->bootPublishables()
            ->bootCommands()
            ->bootMacros()
            ->configureQueue();

        $this->mergeConfigFrom(DEBUGMATE_PATH . '/config/debugmate.php', 'debugmate');
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
                InstallDebugmateCommand::class,
                TestDebugmateCommand::class,
            ]);
        }

        return $this;
    }

    private function bootPublishables(): self
    {
        if ($this->app->runningInConsole()) {
            $configPath = function_exists('config_path')
                ? config_path('debugmate.php')
                : base_path('config/debugmate.php');

            $this->publishes([
                DEBUGMATE_PATH . '/config/debugmate.php' => $configPath,
            ], 'debugmate-config');

            $this->publishes([
                DEBUGMATE_PATH . '/stubs/DebugmateServiceProvider.stub' => app_path('Providers/DebugmateServiceProvider.php'),
            ], 'debugmate-provider');
        }

        return $this;
    }

    protected function registerErrorHandler(): void
    {
        $this->app->singleton('debugmate.logger', function () {
            $handler = new DebugmateErrorHandler();

            $handler->setMinimumLogLevel(
                $this->getLogLevel()
            );

            return tap(
                new Logger('Debugmate'),
                function (Logger $logger) use ($handler) {
                    return $logger->pushHandler($handler);
                }
            );
        });

        Log::extend('debugmate', function ($app) {
            return $app['debugmate.logger'];
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
        $logLevel = config('logging.channels.debugmate.level', Level::Error->value);

        $logLevel = Level::tryFrom((int)$logLevel);

        if (!$logLevel) {
            throw new InvalidArgumentException('The given log level is invalid');
        }

        return $logLevel;
    }

    protected function setLogChannel(): void
    {
        config(['logging.channels.debugmate.driver' => 'debugmate']);
        config(['logging.channels.stack.channels' => ['stack', 'debugmate']]);
    }
}
