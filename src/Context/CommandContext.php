<?php

namespace Cockpit\Context;

use Cockpit\Interfaces\ContextInterface;
use Illuminate\Foundation\Application;

/**
 * @SuppressWarnings(PHPMD.Superglobals)
 */
class CommandContext implements ContextInterface
{
    protected $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function getContext(): array
    {
        if (!$this->app->runningInConsole()) {
            return [];
        }

        $arguments = $_SERVER['argv'] ?? [];

        if (empty($arguments)) {
            return [];
        }

        unset($arguments[0]);

        $command = array_shift($arguments);

        return [
            'command'   => 'php artisan ' . $command,
            'arguments' => $arguments,
        ];
    }
}
