<?php

namespace Cockpit\Exceptions;

use Cockpit\Context\Dump\HtmlDumper;
use Cockpit\Interfaces\ContextInterface;
use ErrorException;

/**
 * @see https://github.com/spatie/laravel-ignition
 */
class ViewException extends ErrorException implements ContextInterface
{
    /** @var array<string, mixed> */
    protected $viewData = [];

    protected $view = '';

    /**
     * @param array<string, mixed> $data
     *
     * @return void
     */
    public function setViewData(array $data): void
    {
        $this->viewData = $data;
    }

    /** @return array<string, mixed> */
    public function getViewData(): array
    {
        return $this->viewData;
    }

    public function setView(string $path): void
    {
        $this->view = $path;
    }

    protected function dumpViewData(mixed $variable): string
    {
        return (new HtmlDumper())->dumpVariable($variable);
    }

    public function getContext(): ?array
    {
        $context = [
            'view' => [
                'view' => $this->view,
            ],
        ];

        $context['view']['data'] = array_map([$this, 'dumpViewData'], $this->viewData);

        return $context;
    }
}
