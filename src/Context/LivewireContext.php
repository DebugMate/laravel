<?php

namespace Cockpit\Context;

use Cockpit\Context\Livewire\LivewireInformationV2;
use Cockpit\Context\Livewire\LivewireInformationV3;
use Cockpit\Interfaces\ContextInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LivewireContext implements ContextInterface
{
    public function __construct(public Request $request)
    {
    }

    public function getContext(): array
    {
        if (app()->runningInConsole() && !app()->runningUnitTests() || !$this->isRunningLivewire()) {
            return [];
        }

        $livewireInformation = match ($this->livewireVersion()) {
            'v2'    => (new LivewireInformationV2($this->request))->information(),
            'v3'    => (new LivewireInformationV3($this->request))->information(),
            default => []
        };

        return $this->getRequestData() + $livewireInformation;
    }

    public function isRunningLivewire(): bool
    {
        return $this->request->hasHeader('x-livewire') && $this->request->hasHeader('referer');
    }

    public function livewireVersion(): string
    {
        if (class_exists(\Livewire\LivewireComponentsFinder::class)) {
            return 'v2';
        }

        if (class_exists(\Livewire\Mechanisms\ComponentRegistry::class)) {
            return 'v3';
        }

        Log::info('Cockpit - Couldn\'t recognize Livewire version');

        return '';
    }

    protected function getRequestData(): array
    {
        $livewireManager = app('\Livewire\LivewireManager');

        return [
            'url'    => $livewireManager->originalUrl(),
            'method' => $livewireManager->originalMethod(),
        ];
    }
}
