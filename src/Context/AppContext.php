<?php

namespace Debugmate\Context;

use Debugmate\Exceptions\ViewException;
use Debugmate\Interfaces\ContextInterface;
use Illuminate\Foundation\Application;
use Throwable;

class AppContext implements ContextInterface
{
    public function __construct(
        protected Application $app,
        protected Throwable $throwable
    ) {
    }

    public function getContext(): array
    {
        if ($this->app->runningInConsole()) {
            return [];
        }

        $route  = $this->app['router']->current();
        $action = $route?->getAction();

        $isViewException = $this->throwable instanceof ViewException;

        return [
            'controller' => $route?->getActionName(),
            'route'      => [
                'name'       => $action['as'] ?? 'generated::' . md5($route?->getActionName()),
                'parameters' => $route?->parameters(),
            ],
            'middlewares' => $route?->computedMiddleware,
            'view'        => [
                'name' => $isViewException ? $this->throwable->getFile() : null,
                'data' => $isViewException ? $this->throwable->getViewData() : null,
            ],
        ];
    }
}
