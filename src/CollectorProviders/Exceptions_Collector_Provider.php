<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Collector_Providers;

class Exceptions_Collector_Provider extends Abstract_Collector_Provider
{
    public function __invoke(array $options): void
    {
        $exception_collector = $this->debugbar->get_exceptions_collector();
        $this->add_collector($exception_collector);
        $exception_collector->set_chain_exceptions($options['chain'] ?? true);
    }
}