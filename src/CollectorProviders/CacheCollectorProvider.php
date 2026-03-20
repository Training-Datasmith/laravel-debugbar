<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Collector_Providers;

use Fruitcake\Laravel_Debugbar\Data_Collector\Cache_Collector;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
class Cache_Collector_Provider extends Abstract_Collector_Provider
{
    public function __invoke(Request $request, Dispatcher $events, array $options): void
    {
        $collect_values = $options['values'] ?? false;
        $start_time = (float) $request->server('REQUEST_TIME_FLOAT');
        $cache_collector = new Cache_Collector($start_time, $collect_values);
        $this->add_collector($cache_collector);
        if ($options['timeline'] ?? false) {
            $cache_collector->set_time_data_collector($this->debugbar->get_time_collector());
        }
        $class_map = $cache_collector->get_cache_events();
        foreach (array_keys($class_map) as $event_class) {
            $events->listen($event_class, function (\Illuminate\Cache\Events\Cache_Event|\Illuminate\Cache\Events\Cache_Failed_Over|\Illuminate\Cache\Events\Cache_Flushed|\Illuminate\Cache\Events\Cache_Flush_Failed|\Illuminate\Cache\Events\Cache_Flushing $event) use ($cache_collector): void {
                if ($this->debugbar->is_enabled()) {
                    $cache_collector->on_cache_event($event);
                }
            });
        }
        $start_events = array_unique(array_filter(array_map(fn(array $values) => $values[1] ?? null, array_values($class_map))));
        foreach ($start_events as $event_class) {
            $events->listen($event_class, function ($event) use ($cache_collector): void {
                if ($this->debugbar->is_enabled()) {
                    $cache_collector->on_start_cache_event($event);
                }
            });
        }
    }
}