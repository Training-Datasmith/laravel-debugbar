<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Collector_Providers;

use Fruitcake\Laravel_Debugbar\Data_Collector\Route_Collector;
class Route_Collector_Provider extends Abstract_Collector_Provider
{
    public function __invoke(array $options): void
    {
        $this->add_collector(new Route_Collector());
    }
}