<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Collector_Providers;

use Debug_Bar\Data_Collector\Memory_Collector;
class Memory_Collector_Provider extends Abstract_Collector_Provider
{
    public function __invoke(array $options): void
    {
        $memory_collector = new Memory_Collector();
        $this->add_collector($memory_collector);
        $memory_collector->set_precision($options['precision'] ?? 0);
        if (function_exists('memory_reset_peak_usage') && ($options['reset_peak_usage'] ?? false)) {
            memory_reset_peak_usage();
        }
        if ($options['with_baseline'] ?? false) {
            $memory_collector->reset_memory_baseline();
        }
    }
}