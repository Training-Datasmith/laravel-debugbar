<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Collector_Providers;

use Fruitcake\Laravel_Debugbar\Data_Collector\View_Collector;
use Illuminate\Contracts\Events\Dispatcher;
class Views_Collector_Provider extends Abstract_Collector_Provider
{
    public function __invoke(Dispatcher $events, array $options): void
    {
        $collect_data = $options['data'] ?? false;
        $exclude_paths = $options['exclude_paths'] ?? [];
        $group = $options['group'] ?? true;
        $view_collector = new View_Collector($collect_data, $exclude_paths, $group);
        if ($options['timeline'] ?? true) {
            $time_collector = $this->debugbar->get_time_collector();
            $view_collector->set_time_data_collector($time_collector);
        }
        $this->add_collector($view_collector);
        $events->listen('composing:*', function ($event, $params) use ($view_collector): void {
            $view_collector->add_view($params[0]);
        });
    }
}