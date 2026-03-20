<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Controllers;

use Fruitcake\Laravel_Debugbar\Requests\Cache_Delete_Request;
use Illuminate\Cache\Cache_Manager;
class Cache_Controller
{
    /**
     * Forget a cache key
     *
     */
    public function delete(Cache_Manager $cache, Cache_Delete_Request $request, string $key): \Illuminate\Http\Json_Response
    {
        if ($tags = $request->validated('tags')) {
            $cache = $cache->tags($tags);
        }
        $success = $cache->forget($key);
        return response()->json(compact('success'));
    }
}