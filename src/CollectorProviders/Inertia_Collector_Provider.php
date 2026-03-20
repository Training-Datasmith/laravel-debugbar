<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Collector_Providers;

use Fruitcake\Laravel_Debugbar\Data_Collector\Inertia_Collector;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Events\Response_Prepared;
class Inertia_Collector_Provider extends Abstract_Collector_Provider
{
    public function __invoke(Application $app, Dispatcher $events, array $options): void
    {
        if ($app->bound('inertia.view-finder')) {
            $inertia_collector = new Inertia_Collector(true, [], false);
            $this->add_collector($inertia_collector);
            $events->listen(Response_Prepared::class, fn(Response_Prepared $e) => $inertia_collector->add_from_response($e->response));
            $events->listen('composing:*', fn($event, $params) => $inertia_collector->add_from_view($params[0]));
        }
    }
}