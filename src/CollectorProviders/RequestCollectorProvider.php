<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Collector_Providers;

use Fruitcake\Laravel_Debugbar\Data_Collector\Request_Collector;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\Events\Response_Prepared;
class Request_Collector_Provider extends Abstract_Collector_Provider
{
    public function __invoke(Dispatcher $events, Request $request, array $options): void
    {
        $session_hiddens = (array) config('debugbar.options.session.hiddens', []);
        $session_masked = (array) config('debugbar.options.session.masked', []);
        // Legacy hidden values, using array path
        $hiddens = array_map(function ($value): mixed {
            if (str_contains($value, '.')) {
                return substr($value, strrpos($value, '.') + 1);
            }
            return $value;
        }, array_merge((array) ($options['hiddens'] ?? []), $session_hiddens));
        $masked = array_merge((array) ($options['masked'] ?? []), $session_masked);
        $request_collector = new Request_Collector($request);
        $request_collector->add_masked_keys($hiddens);
        $request_collector->add_masked_keys($masked);
        $this->add_collector($request_collector);
        $events->listen(Response_Prepared::class, fn(Response_Prepared $e) => $request_collector->set_response($e->response));
    }
}