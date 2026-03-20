<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Collector_Providers;

use Fruitcake\Laravel_Debugbar\Data_Collector\Event_Collector;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
class Events_Collector_Collector_Provider extends Abstract_Collector_Provider
{
    public function __invoke(Request $request, Dispatcher $events, array $options): void
    {
        $start_time = $request->server('REQUEST_TIME_FLOAT');
        $collect_data = $options['data'] ?? false;
        $collect_listeners = $options['listeners'] ?? false;
        $excluded_events = $options['excluded'] ?? [];
        $event_collector = new Event_Collector($start_time ? (float) $start_time : null);
        if ($collect_data) {
            $event_collector->set_collect_values($collect_data);
        }
        if ($collect_listeners) {
            $event_collector->set_collect_listeners($collect_listeners);
        }
        if ($excluded_events) {
            $event_collector->set_excluded_events($excluded_events);
        }
        $this->add_collector($event_collector);
        $events->listen('*', function (?string $event, array $data = []) use ($event_collector): void {
            if ($this->debugbar->is_enabled()) {
                $event_collector->on_wildcard_event($event, $data);
            }
        });
    }
}