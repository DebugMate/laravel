<?php

namespace Cockpit\Context\Livewire;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class LivewireInformationV3
{
    public function __construct(public Request $request)
    {
    }

    public function information(): array
    {
        $json           = json_decode($this->request->getContent(), true);
        $firstComponent = $json['components'][0] ?? [];
        $firstSnapshot  = json_decode($firstComponent['snapshot'] ?? [], true);

        $componentId    = data_get($firstSnapshot, 'memo.id');
        $componentAlias = data_get($firstSnapshot, 'memo.name');

        if ($componentAlias === null) {
            return [];
        }

        try {
            $componentClass = app()->make(\Livewire\Mechanisms\ComponentRegistry::class)->getClass($componentAlias);
        } catch (Throwable $throwable) {
            $componentClass = null;

            $context = [
                'file'    => $throwable->getFile(),
                'code'    => $throwable->getCode(),
                'message' => $throwable->getMessage()
            ];

            Log::info('Cockpit - Couldn\'t get livewire class:', $context);
        }

        return [
            'component_class' => $componentClass,
            'component_alias' => $componentAlias,
            'component_id'    => $componentId,
            'data'            => $firstSnapshot['data']     ?? [],
            'updates'         => $firstComponent['updates'] ?? [],
        ];
    }
}
