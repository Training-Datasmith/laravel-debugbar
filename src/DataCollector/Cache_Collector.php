<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Data_Collector;

use Debug_Bar\Data_Collector\Asset_Provider;
use Debug_Bar\Data_Collector\Has_Time_Data_Collector;
use Debug_Bar\Data_Collector\Resettable;
use Debug_Bar\Data_Collector\Time_Data_Collector;
use Illuminate\Cache\Events\{Cache_Event, Cache_Failed_Over, Cache_Flush_Failed, Cache_Flushed, Cache_Flushing, Cache_Hit, Cache_Missed, Forgetting_Key, Key_Forget_Failed, Key_Forgotten, Key_Write_Failed, Key_Written, Retrieving_Key, Writing_Key};
use Illuminate\Support\Facades\Route;
use Throwable;
class Cache_Collector extends Time_Data_Collector implements Asset_Provider, Resettable
{
    use Has_Time_Data_Collector;
    protected array $event_starts = [];
    protected array $class_map = [Cache_Hit::class => ['hit', Retrieving_Key::class], Cache_Missed::class => ['missed', Retrieving_Key::class], Cache_Flushed::class => ['flushed', Cache_Flushing::class], Cache_Flush_Failed::class => ['flush_failed', Cache_Flushing::class], Key_Written::class => ['written', Writing_Key::class], Key_Write_Failed::class => ['write_failed', Writing_Key::class], Key_Forgotten::class => ['forgotten', Forgetting_Key::class], Key_Forget_Failed::class => ['forget_failed', Forgetting_Key::class]];
    public function __construct(float $request_start_time, protected bool $collect_values)
    {
        parent::__construct($request_start_time);
        $this->memory_measure = true;
    }
    public function get_cache_events(): array
    {
        return $this->class_map;
    }
    public function on_cache_event(Cache_Event|Cache_Failed_Over|Cache_Flushed|Cache_Flush_Failed|Cache_Flushing $event): void
    {
        $class = $event::class;
        $params = get_object_vars($event);
        $label = $this->class_map[$class][0];
        if (isset($params['value'])) {
            if (!($params['value'] instanceof \Closure || is_resource($params['value']))) {
                try {
                    $params['memoryUsage'] = strlen(serialize($params['value'])) * 8;
                } catch (Throwable) {
                }
            }
            if (!$this->collect_values) {
                unset($params['value']);
            }
        }
        $time = microtime(true);
        $start_hash_key = $this->get_event_hash($this->class_map[$class][1] ?? '', $params);
        $start_time = $this->event_starts[$start_hash_key] ?? $time;
        $this->add_measure($label . "\t" . ($params['key'] ?? ''), $start_time, $time, $params);
        if ($this->has_time_data_collector()) {
            $this->add_time_measure('Cache ' . $label . "\t" . ($params['key'] ?? ''), $start_time, $time);
        }
        if (isset($event->key) && in_array($label, ['hit', 'written'], true) && Route::has('debugbar.cache.delete')) {
            $measure_index = array_key_last($this->measures);
            $this->measures[$measure_index]['delete_url'] = url()->signed_route('debugbar.cache.delete', ['key' => urlencode((string) $event->key), 'tags' => $params['tags'] ?? []]);
        }
    }
    public function on_start_cache_event(mixed $event): void
    {
        $start_hash_key = $this->get_event_hash($event::class, get_object_vars($event));
        $this->event_starts[$start_hash_key] = microtime(true);
    }
    protected function get_event_hash(string $class, array $params): string
    {
        unset($params['value']);
        return $class . ':' . substr(hash('sha256', json_encode($params)), 0, 12);
    }
    public function collect(): array
    {
        $data = parent::collect();
        $data['nb_measures'] = $data['count'] = count($data['measures']);
        return $data;
    }
    public function reset(): void
    {
        parent::reset();
        $this->event_starts = [];
    }
    public function get_name(): string
    {
        return 'cache';
    }
    public function get_widgets(): array
    {
        return ['cache' => ['icon' => 'clipboard-text', 'widget' => 'PhpDebugBar.Widgets.LaravelCacheWidget', 'map' => 'cache', 'default' => '{}'], 'cache:badge' => ['map' => 'cache.nb_measures', 'default' => 'null']];
    }
    public function get_assets(): array
    {
        return ['js' => __DIR__ . '/../../resources/cache/widget.js'];
    }
}