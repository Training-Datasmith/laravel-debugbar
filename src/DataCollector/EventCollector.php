<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Data_Collector;

use Debug_Bar\Data_Collector\Time_Data_Collector;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
class Event_Collector extends Time_Data_Collector
{
    protected array $excluded_events = [];
    protected bool $collect_values = false;
    protected bool $collect_listeners = false;
    public function set_collect_values(bool $collect_values = true): void
    {
        $this->collect_values = $collect_values;
    }
    public function set_collect_listeners(bool $collect_listeners = true): void
    {
        $this->collect_listeners = $collect_listeners;
    }
    public function set_excluded_events(array $excluded_events): void
    {
        $this->excluded_events = $excluded_events;
    }
    public function on_wildcard_event(?string $name = null, array $data = []): void
    {
        $current_time = microtime(true);
        $event_class = explode(':', (string) $name)[0];
        foreach ($this->excluded_events as $excluded_event) {
            if (Str::is($excluded_event, $event_class)) {
                return;
            }
        }
        if (!$this->collect_values) {
            $this->add_measure($name, $current_time, $current_time, [], null, $event_class);
            return;
        }
        $params = $data;
        if ($this->collect_listeners) {
            $params['listeners'] = Event::get_listeners($name);
        }
        $this->add_measure($name, $current_time, $current_time, $params, null, $event_class);
    }
    public function collect(): array
    {
        $data = parent::collect();
        $data['nb_measures'] = $data['count'] = count($data['measures']);
        return $data;
    }
    public function get_name(): string
    {
        return 'event';
    }
    public function get_widgets(): array
    {
        return ['events' => ['icon' => 'subtask', 'widget' => 'PhpDebugBar.Widgets.TimelineWidget', 'map' => 'event', 'default' => '{}'], 'events:badge' => ['map' => 'event.nb_measures', 'default' => 0]];
    }
}