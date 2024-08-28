<?php

namespace Cockpit\Traits;

use Carbon\Carbon;

trait InteractsWithDates
{
    public function interactWithExceededDates(Carbon $from, Carbon $to, callable $callable)
    {
        if ($from->greaterThan($to)) {
            return $callable($from, $to);
        }
    }

    public function todayInterval(): void
    {
        if (!$this->from->equalTo($this->to)) {
            return;
        }

        $this->from = $this->from->startOfDay();
        $this->to   = $this->to->endOfDay();
    }
}
