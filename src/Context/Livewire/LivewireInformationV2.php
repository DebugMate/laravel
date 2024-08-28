<?php

namespace Cockpit\Context\Livewire;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

class LivewireInformationV2
{
    public function __construct(public Request $request)
    {
    }

    public function information(): array
    {
        $componentId    = $this->request->input('fingerprint.id');
        $componentAlias = $this->request->input('fingerprint.name');

        if ($componentAlias === null) {
            return [];
        }

        try {
            $componentClass = app(\Livewire\LivewireComponentsFinder::class)->find($componentAlias);
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
            'data'            => $this->resolveData(),
            'updates'         => $this->resolveUpdates(),
        ];
    }

    protected function resolveData(): array
    {
        $data     = $this->request->input('serverMemo.data')     ?? [];
        $dataMeta = $this->request->input('serverMemo.dataMeta') ?? [];

        foreach ($dataMeta['modelCollections'] ?? [] as $key => $value) {
            $data[$key] = array_merge($data[$key] ?? [], $value);
        }

        foreach ($dataMeta['models'] ?? [] as $key => $value) {
            $data[$key] = array_merge($data[$key] ?? [], $value);
        }

        return $data;
    }

    protected function resolveUpdates(): array
    {
        $updates = $this->request->input('updates') ?? [];

        return array_map(function (array $update) {
            $update['payload'] = Arr::except($update['payload'] ?? [], ['id']);

            return $update;
        }, $updates);
    }
}
