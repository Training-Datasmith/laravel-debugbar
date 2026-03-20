<?php

declare(strict_types=1);

/**
 * Example: creating a custom DebugBar collector for laravel-debugbar.
 *
 * Demonstrates the collector API — actual rendering requires a running
 * Laravel application with the debugbar middleware active.
 *
 * Run from the laravel-debugbar project root:
 *   php examples/custom_collector.php
 */

require __DIR__ . '/../vendor/autoload.php';

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

/**
 * A simple collector that tracks custom application events.
 */
final class Custom_Event_Collector extends DataCollector implements Renderable
{
    /** @var list<array{name: string, time: float, data: mixed}> */
    private array $events = [];

    public function record(string $name, mixed $data = null): void
    {
        $this->events[] = [
            'name' => $name,
            'time' => microtime(true),
            'data' => $data,
        ];
    }

    public function collect(): array
    {
        return [
            'count'  => count($this->events),
            'events' => $this->events,
        ];
    }

    public function getName(): string
    {
        return 'custom_events';
    }

    public function getWidgets(): array
    {
        return [
            'custom_events' => [
                'icon'    => 'tasks',
                'widget'  => 'PhpDebugBar.Widgets.VariableListWidget',
                'map'     => 'custom_events.events',
                'default' => '[]',
            ],
            'custom_events:badge' => [
                'map'     => 'custom_events.count',
                'default' => 0,
            ],
        ];
    }
}

// --- Demonstrate the collector ---
$collector = new Custom_Event_Collector();

$collector->record('user.login',  ['user_id' => 42, 'ip' => '127.0.0.1']);
$collector->record('cache.miss',  'user:42:profile');
$collector->record('db.query',    'SELECT * FROM articles WHERE id = 1');

$data = $collector->collect();

echo "Collector name: " . $collector->getName() . "\n";
echo "Event count: "    . $data['count'] . "\n\n";

foreach ($data['events'] as $event) {
    printf("  [%s] %s\n", $event['name'], is_string($event['data']) ? $event['data'] : json_encode($event['data']));
}

echo "\nWidget config keys: " . implode(', ', array_keys($collector->getWidgets())) . "\n";
