<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Collector_Providers;

use Debug_Bar\Data_Collector\Object_Count_Collector;
use Illuminate\Contracts\Events\Dispatcher;
class Models_Collector_Provider extends Abstract_Collector_Provider
{
    public function __invoke(Dispatcher $events, array $options): void
    {
        $models_collector = new Object_Count_Collector('models');
        $this->add_collector($models_collector);
        $event_list = ['retrieved', 'created', 'updated', 'deleted'];
        $models_collector->set_key_map(array_combine($event_list, array_map(ucfirst(...), $event_list)));
        $models_collector->collect_count_summary(true);
        foreach ($event_list as $event) {
            $events->listen("eloquent.{$event}: *", function (array $event, $models) use ($models_collector): void {
                $event = explode(': ', (string) $event);
                $count = count(array_filter($models));
                $models_collector->count_class($event[1], $count, explode('.', $event[0])[1]);
            });
        }
    }
}