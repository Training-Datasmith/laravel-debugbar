<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Collector_Providers;

use Debug_Bar\Data_Collector\Object_Count_Collector;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\Job_Queued;
class Jobs_Collector_Provider extends Abstract_Collector_Provider
{
    public function __invoke(Dispatcher $events, array $options): void
    {
        $jobs = new Object_Count_Collector('jobs', 'briefcase');
        $this->add_collector($jobs);
        $events->listen(Job_Queued::class, function ($event) use ($jobs): void {
            $jobs->count_class($event->job);
        });
    }
}