<?php

namespace Cockpit\Context;

use Cockpit\Interfaces\ContextInterface;
use Illuminate\Foundation\Application;
use Spatie\Backtrace\Backtrace;
use Spatie\Backtrace\CodeSnippet;
use Spatie\Backtrace\Frame;
use Throwable;

class StackTraceContext implements ContextInterface
{
    protected $app;

    protected $throwable;

    public function __construct(Application $app, Throwable $throwable)
    {
        $this->app       = $app;
        $this->throwable = $throwable;
    }

    public function getContext(): array
    {
        $trace = [];

        $backTrace = Backtrace::createForThrowable($this->throwable)
            ->applicationPath($this->app->basePath());

        foreach ($backTrace->frames() as $frame) {
            $trace[] = [
                'file'              => $frame->file,
                'line'              => $frame->lineNumber,
                'function'          => $frame->method,
                'class'             => $frame->class,
                'application_frame' => $frame->applicationFrame,
                'preview'           => $this->resolveFilePreview($frame),
            ];
        }

        return $trace;
    }

    protected function resolveFilePreview(Frame $frame): array
    {
        return (new CodeSnippet())
            ->surroundingLine($frame->lineNumber)
            ->snippetLineCount(20)
            ->get($frame->file);
    }
}
