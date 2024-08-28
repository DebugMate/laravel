<?php

if (!function_exists('error_percentage')) {
    function error_percentage($chunk, $total)
    {
        if ($chunk === 0 || $total === 0) {
            return 0;
        }

        return ($chunk / $total) * 100;
    }
}

if (!function_exists('is_log_object')) {
    function is_log_object($log): bool
    {
        $log = json_decode(json_encode($log));

        if (gettype($log) == 'object') {
            return true;
        }

        if (
            count(array_filter($log, function ($item) {
                return gettype($item) == 'object';
            })) > 0
        ) {
            return true;
        }

        return false;
    }
}
