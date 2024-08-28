<?php

namespace Cockpit\Context;

use Cockpit\Context\Dump\Dump;
use Cockpit\Context\Dump\DumpHandler;
use Cockpit\Context\Dump\HtmlDumper;
use Cockpit\Context\Dump\MultiDumpHandler;
use Cockpit\Interfaces\ContextInterface;
use Cockpit\Interfaces\RecorderInterface;
use Illuminate\Foundation\Application;
use Illuminate\Support\Arr;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\VarDumper;

/**
 * @see https://github.com/spatie/laravel-ignition
 */
class DumpContext implements ContextInterface, RecorderInterface
{
    protected $dumps = [];

    protected $app;

    protected static $registeredHandler = false;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function start(): DumpContext
    {
        $multiDumpHandler = new MultiDumpHandler();

        $this->app->singleton(MultiDumpHandler::class, function ($multiDumpHandler) {
            return $multiDumpHandler;
        });

        if (!self::$registeredHandler) {
            static::$registeredHandler = true;

            $this->ensureOriginalHandlerExists();

            $originalHandler = VarDumper::setHandler(function ($dumpedVariable) use ($multiDumpHandler) {
                return $multiDumpHandler->dump($dumpedVariable);
            });

            $dumpContext = $this;
            $multiDumpHandler->addHandler($originalHandler);
            $multiDumpHandler->addHandler(function ($var) use ($dumpContext) {
                return (new DumpHandler($dumpContext))->dump($var);
            });
        }

        return $this;
    }

    public function record(Data $data)
    {
        $backtrace   = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 11);
        $sourceFrame = $this->findSourceFrame($backtrace);

        $file       = (string)Arr::get($sourceFrame, 'file');
        $lineNumber = (int)Arr::get($sourceFrame, 'line');

        $htmlDump = (new HtmlDumper())->dump($data);

        $this->dumps[] = new Dump($htmlDump, $file, $lineNumber);
    }

    public function reset()
    {
        $this->dumps = [];
    }

    public function getContext(): ?array
    {
        $dumps = [];

        foreach ($this->dumps as $dump) {
            $dumps[] = $dump->toArray();
        }

        return $dumps;
    }

    protected function ensureOriginalHandlerExists(): void
    {
        $reflectionProperty = new ReflectionProperty(VarDumper::class, 'handler');
        $reflectionProperty->setAccessible(true);

        $handler = $reflectionProperty->getValue();

        if (!$handler) {
            $reflectionMethod = new ReflectionMethod(VarDumper::class, 'register');
            $reflectionMethod->setAccessible(true);
            $reflectionMethod->invoke(null);
        }
    }

    protected function findSourceFrame(array $stackTrace): ?array
    {
        $seenVarDumper = false;

        foreach ($stackTrace as $frame) {
            if (Arr::get($frame, 'class') === VarDumper::class && Arr::get($frame, 'function') === 'dump') {
                $seenVarDumper = true;

                continue;
            }

            if (!$seenVarDumper) {
                continue;
            }

            return $frame;
        }

        return null;
    }
}
