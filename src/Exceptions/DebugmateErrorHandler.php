<?php

namespace Debugmate\Exceptions;

use Debugmate\Debugmate;
use Debugmate\Context\AppContext;
use Debugmate\Context\CommandContext;
use Debugmate\Context\DumpContext;
use Debugmate\Context\EnvironmentContext;
use Debugmate\Context\JobContext;
use Debugmate\Context\LivewireContext;
use Debugmate\Context\RequestContext;
use Debugmate\Context\StackTraceContext;
use Debugmate\Context\UserContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

class DebugmateErrorHandler extends AbstractProcessingHandler
{
    protected $minimumLogLevel = Level::Error;

    private $response = null; // @phpstan-ignore-line

    public function handle(LogRecord $record): bool
    {
        $this->write($record);

        return true;
    }

    public function setMinimumLogLevel(Level $level)
    {
        $this->minimumLogLevel = $level;
    }

    protected function write(LogRecord $record): void
    {
        if (!$this->shouldReport($record)) {
            return;
        }

        $this->log(
            $record['context']['exception'],
            Arr::except($record['context'], 'exception')
        );
    }

    protected function shouldReport(LogRecord $report): bool
    {
        return $this->hasException($report) && $this->hasValidLogLevel($report);
    }

    protected function hasException(LogRecord $report): bool
    {
        return isset($report->context['exception']) && $report->context['exception'] instanceof Throwable;
    }

    protected function hasValidLogLevel(LogRecord $report): bool
    {
        return (int)$report->level->value >= (int)$this->minimumLogLevel->value;
    }

    protected function log(Throwable $throwable, array $context = []): void
    {
        if (!config('debugmate.enabled')) {
            Log::info('Debugmate - Not enabled');

            return;
        }

        if (!config('debugmate.domain')) {
            Log::info('Debugmate - You need to fill DEBUGMATE_DOMAIN env with a valid debugmate endpoint');

            return;
        }

        try {
            $traceContext       = app(StackTraceContext::class, ['throwable' => $throwable]);
            $userContext        = app(UserContext::class);
            $appContext         = app(AppContext::class, ['throwable' => $throwable]);
            $commandContext     = app(CommandContext::class);
            $livewireContext    = app(LivewireContext::class);
            $jobContext         = app(JobContext::class);
            $dumpContext        = app(DumpContext::class);
            $requestContext     = app(RequestContext::class);
            $environmentContext = app(EnvironmentContext::class);

            $endpoint = Str::finish(config('debugmate.domain'), '/') . 'webhook';

            $this->response = Http::withHeaders(['X-DEBUGMATE-TOKEN' => config('debugmate.token')])
                ->post($endpoint, [
                    'exception'   => get_class($throwable),
                    'message'     => $throwable->getMessage(),
                    'file'        => $throwable->getFile(),
                    'code'        => $throwable->getCode(),
                    'resolved_at' => null,
                    'type'        => $this->getExceptionType(),
                    'url'         => $this->resolveUrl(),
                    'trace'       => $traceContext->getContext(),
                    'debug'       => $dumpContext->getContext(),
                    'app'         => $appContext->getContext(),
                    'user'        => $userContext->getContext(),
                    'context'     => $context,
                    'request'     => $requestContext->getContext(),
                    'command'     => $commandContext->getContext(),
                    'job'         => $jobContext->getContext(),
                    'livewire'    => $livewireContext->getContext(),
                    'environment' => $environmentContext->getContext(),
                ]);
        } catch (Throwable $throwable) {
            $context = [
                'file'    => $throwable->getFile(),
                'code'    => $throwable->getCode(),
                'message' => $throwable->getMessage()
            ];

            Log::info('Debugmate - Couldn\'t send info to server, error:', $context);
        }
    }

    public function failed(): ?bool
    {
        return $this->response
            ? $this->response->failed()
            : null;
    }

    public function reason(): ?string
    {
        return $this->response
            ? "Reason: {$this->response->status()} {$this->response->reason()}"
            : null;
    }

    protected function resolveUrl(): ?string
    {
        return !app()->runningInConsole()
            ? app('request')->fullUrl()
            : null;
    }

    protected function getExceptionType(): string
    {
        if (!app()->runningInConsole()) {
            return Debugmate::TYPE_WEB;
        }

        return $this->isExceptionFromJob() ? Debugmate::TYPE_JOB : Debugmate::TYPE_CLI;
    }

    protected function isExceptionFromJob(): bool
    {
        return is_array(app(JobContext::class)->getContext());
    }
}
