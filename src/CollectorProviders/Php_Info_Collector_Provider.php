<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Collector_Providers;

use Debug_Bar\Data_Collector\Php_Info_Collector;
class Php_Info_Collector_Provider extends Abstract_Collector_Provider
{
    public function __invoke(array $options): void
    {
        $this->add_collector(new Php_Info_Collector());
    }
}