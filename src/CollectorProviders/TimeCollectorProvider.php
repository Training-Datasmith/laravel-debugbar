<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Collector_Providers;

use Debug_Bar\Data_Collector\Time_Data_Collector;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Events\Preparing_Response;
use Illuminate\Routing\Events\Response_Prepared;
use Illuminate\Routing\Events\Route_Matched;
use Illuminate\Routing\Events\Routing;
class Time_Collector_Provider extends Abstract_Collector_Provider
{
    public function __invoke(Application $app, Request $request, Dispatcher $events, array $options): void
    {
        if ($this->has_collector('time')) {
            /** @var TimeDataCollector $timeCollector */
            $time_collector = $this['time'];
        } else {
            $time_collector = $this->debugbar->get_time_collector();
            $this->add_collector($time_collector);
        }
        if ($options['memory_usage'] ?? false) {
            $time_collector->show_memory_usage();
        }
        $events->listen(Routing::class, fn() => $time_collector->start_measure('Routing'));
        $events->listen(Route_Matched::class, fn() => $time_collector->stop_measure('Routing'));
        $events->listen(Preparing_Response::class, fn() => $time_collector->start_measure('Preparing Response'));
        $events->listen(Response_Prepared::class, fn() => $time_collector->stop_measure('Preparing Response'));
    }
}