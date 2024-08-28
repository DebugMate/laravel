<?php

namespace Cockpit;

use Closure;
use Illuminate\Http\Request;

class Cockpit
{
    public const TYPE_WEB = 'web';
    public const TYPE_CLI = 'cli';
    public const TYPE_JOB = 'job';

    public static $authUsing = null;

    public static function check(Request $request)
    {
        return (static::$authUsing ?: function () {
            return app()->isLocal();
        })($request);
    }

    public static function auth(Closure $callback): void
    {
        static::$authUsing = $callback;
    }
}
