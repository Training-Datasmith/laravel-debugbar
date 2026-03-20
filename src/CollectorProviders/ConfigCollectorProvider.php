<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Collector_Providers;

use Fruitcake\Laravel_Debugbar\Data_Collector\Config_Collector;
class Config_Collector_Provider extends Abstract_Collector_Provider
{
    public function __invoke(array $options): void
    {
        $config_collector = new Config_Collector();
        $masked = ['app.key', 'app.previous_keys', '*.*_key', '*.*apikey', '*.*secret*', '*.*password*', '*.*token*'];
        $config_collector->add_masked_keys(array_merge($masked, $options['masked'] ?? []));
        $this->add_collector($config_collector);
    }
}