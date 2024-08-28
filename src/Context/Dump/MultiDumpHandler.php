<?php

namespace Cockpit\Context\Dump;

/**
 * @see https://github.com/spatie/laravel-ignition
 */
class MultiDumpHandler
{
    protected $handlers = [];

    public function dump($value): void
    {
        foreach ($this->handlers as $handler) {
            if ($handler) {
                $handler($value);
            }
        }
    }

    public function addHandler(callable $callable = null): self
    {
        $this->handlers[] = $callable;

        return $this;
    }

    public function getHandlers(): array
    {
        return $this->handlers;
    }
}
