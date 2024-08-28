<?php

namespace Cockpit\Context;

use Cockpit\Interfaces\ContextInterface;
use Cockpit\Interfaces\RecorderInterface;
use DateTime;
use Error;
use Exception;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Application;
use Illuminate\Queue\CallQueuedClosure;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Jobs\RedisJob;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;

class JobContext implements ContextInterface, RecorderInterface
{
    protected $app;

    protected $job = null;

    protected $maxChainedJobReportingDepth;

    public function __construct(Application $app, int $maxChainedJobReportingDepth = 5)
    {
        $this->app = $app;

        $this->maxChainedJobReportingDepth = $maxChainedJobReportingDepth;
    }

    public function start(): void
    {
        $this->app->get('events')->listen(JobExceptionOccurred::class, [$this, 'setJob']);
    }

    public function setJob(JobExceptionOccurred $event): JobContext
    {
        $this->job = $event->job;

        return $this;
    }

    public function reset(): JobContext
    {
        $this->job = null;

        return $this;
    }

    public function getContext(): array
    {
        if (!$this->job) {
            return [];
        }

        return array_merge($this->getJobProperties(), [
            'name'       => $this->job->resolveName(),
            'connection' => $this->job->getConnectionName(),
            'queue'      => $this->job->getQueue(),
        ]);
    }

    protected function getJobProperties(): array
    {
        $payload = $this->resolveJobPayload();

        $properties = [];

        foreach ($payload as $key => $value) {
            if (!in_array($key, ['job', 'data', 'displayName'])) {
                $properties[$key] = $value;
            }
        }

        if ($pushedAt = DateTime::createFromFormat('U.u', data_get($payload, 'pushedAt', ''))) {
            $properties['pushedAt'] = $pushedAt->format(DATE_ATOM);
        }

        try {
            $properties['data'] = $this->resolveCommandProperties(
                $this->resolveObjectFromCommand($payload['data']['command']),
                $this->maxChainedJobReportingDepth
            );
        } catch (Exception $exception) {
        }

        return $properties;
    }

    protected function resolveJobPayload(): array
    {
        if (!$this->job instanceof RedisJob) {
            return $this->job->payload();
        }

        try {
            return json_decode($this->job->getReservedJob(), true, 512, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            return $this->job->payload();
        }
    }

    protected function resolveCommandProperties(object $command, int $maxChainDepth): array
    {
        $properties = collect((new ReflectionClass($command))->getProperties())
            ->reject(function (ReflectionProperty $property) {
                return in_array($property->name, ['job', 'closure']);
            })
            ->mapWithKeys(function (ReflectionProperty $property) use ($command) {
                try {
                    $property->setAccessible(true);

                    return [$property->name => $property->getValue($command)];
                } catch (Error $error) {
                    return [$property->name => 'uninitialized'];
                }
            });

        if ($properties->has('chained')) {
            $properties['chained'] = $this->resolveJobChain($properties->get('chained'), $maxChainDepth);
        }

        return $properties->all();
    }

    protected function resolveJobChain(array $chainedCommands, int $maxDepth): array
    {
        if ($maxDepth === 0) {
            return ['Cockpit stopped recording jobs after this point since the max chain depth was reached'];
        }

        return array_map(function (string $command) use ($maxDepth) {
            $commandObject = $this->resolveObjectFromCommand($command);

            return [
                'name' => $commandObject instanceof CallQueuedClosure ? $commandObject->displayName() :
                    get_class($commandObject),
                'data' => $this->resolveCommandProperties($commandObject, $maxDepth - 1),
            ];
        }, $chainedCommands);
    }

    // Taken from Illuminate\Queue\CallQueuedHandler
    protected function resolveObjectFromCommand(string $command): object
    {
        if (Str::startsWith($command, 'O:')) {
            return unserialize($command);
        }

        if ($this->app->bound(Encrypter::class)) {
            return unserialize($this->app[Encrypter::class]->decrypt($command));
        }

        throw new RuntimeException('Unable to extract job payload.');
    }
}
