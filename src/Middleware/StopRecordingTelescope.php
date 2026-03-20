<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Telescope\Telescope;
readonly class Stop_Recording_Telescope
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     */
    public function handle($request, Closure $next): mixed
    {
        if (class_exists(Telescope::class)) {
            Telescope::stop_recording();
        }
        return $next($request);
    }
}