<?php

namespace Cockpit\Context\Dump;

use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper as BaseHtmlDumper;

/**
 * @see https://github.com/spatie/laravel-ignition
 */
class HtmlDumper extends BaseHtmlDumper
{
    protected ?string $dumpHeader = null;

    public function dumpVariable($variable): string
    {
        $clonedData = (new VarCloner())
            ->cloneVar($variable)
            ->withMaxDepth(3);

        return $this->dump($clonedData);
    }

    public function dump(Data $data, $output = null, array $extraDisplayOptions = []): string
    {
        return (string)parent::dump($data, true, [
            'maxDepth'        => 3,
            'maxStringLength' => 160,
        ]);
    }
}
