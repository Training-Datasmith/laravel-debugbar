<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Collector_Providers;

use Fruitcake\Laravel_Debugbar\Data_Collector\Logs_Collector;
use Illuminate\Log\Logger;
class Logs_Collector_Provider extends Abstract_Collector_Provider
{
    public function __invoke(Logger $logger, array $options): void
    {
        $file = $options['file'] ?? 'laravel.log';
        $this->add_collector(new Logs_Collector($file));
    }
}