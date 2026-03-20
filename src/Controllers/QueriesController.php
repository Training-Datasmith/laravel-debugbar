<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Controllers;

use Exception;
use Fruitcake\Laravel_Debugbar\Laravel_Debugbar;
use Fruitcake\Laravel_Debugbar\Requests\Queries_Explain_Request;
use Fruitcake\Laravel_Debugbar\Support\Explain;
class Queries_Controller
{
    /**
     * Generate explain data for query.
     */
    public function explain(Queries_Explain_Request $request, Laravel_Debugbar $debugbar, Explain $explain): \Illuminate\Http\Json_Response
    {
        $validated = $request->validated();
        if (($validated['mode'] ?? null) === 'result') {
            if (!config('debugbar.options.db.show_query_result', false) || !$debugbar->is_storage_open($request)) {
                return response()->json(['success' => false, 'message' => 'Query result is currently disabled in the Debugbar.'], 400);
            }
            return response()->json(['success' => true, 'data' => $explain->generate_select_result($validated['connection'], $validated['query'], $validated['bindings'] ?? null, $validated['hash'], $validated['format'] ?? null)]);
        }
        if (!config('debugbar.options.db.explain.enabled', false) || !$debugbar->is_storage_open($request)) {
            return response()->json(['success' => false, 'message' => 'EXPLAIN is currently disabled in the Debugbar.'], 400);
        }
        try {
            if (($validated['mode'] ?? null) === 'visual') {
                return response()->json(['success' => true, 'data' => $explain->generate_visual_explain($validated['connection'], $validated['query'], $validated['bindings'] ?? null, $validated['hash'])]);
            }
            return response()->json(['success' => true, 'data' => $explain->generate_raw_explain($validated['connection'], $validated['query'], $validated['bindings'] ?? null, $validated['hash']), 'visual' => $explain->is_visual_explain_supported($validated['connection']) ? ['confirm' => $explain->confirm_visual_explain($validated['connection'])] : null]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->get_message()], 400);
        }
    }
}