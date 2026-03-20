<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Middleware;

use Closure;
use Fruitcake\Laravel_Debugbar\Laravel_Debugbar;
use Illuminate\Http\Request;
readonly class Debugbar_Enabled
{
    /**
     * Create a new middleware instance.
     *
     */
    public function __construct(protected Laravel_Debugbar $debugbar)
    {
    }
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     */
    public function handle($request, Closure $next): mixed
    {
        if (!$this->debugbar->is_enabled()) {
            abort(404);
        }
        return $next($request);
    }
}