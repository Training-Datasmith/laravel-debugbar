<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Collector_Providers;

use Fruitcake\Laravel_Debugbar\Data_Collector\Gate_Collector;
use Illuminate\Auth\Access\Events\Gate_Evaluated;
use Illuminate\Contracts\Events\Dispatcher;
class Gate_Collector_Provider extends Abstract_Collector_Provider
{
    public function __invoke(Dispatcher $events, array $options): void
    {
        $gate_collector = new Gate_Collector('gate');
        $this->add_collector($gate_collector);
        if ($options['trace'] ?? false) {
            $gate_collector->collect_file_trace(true);
            $gate_collector->add_backtrace_exclude_paths($options['exclude_paths'] ?? []);
        }
        if ($options['timeline'] ?? false) {
            $gate_collector->set_time_data_collector($this->debugbar->get_time_collector());
        }
        $events->listen(Gate_Evaluated::class, fn(Gate_Evaluated $event) => $gate_collector->add_check($event->user, $event->ability, $event->result, $event->arguments));
    }
}