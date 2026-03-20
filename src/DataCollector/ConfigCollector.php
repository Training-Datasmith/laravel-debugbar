<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Data_Collector;

class Config_Collector extends \Debug_Bar\Data_Collector\Config_Collector
{
    public function collect(): array
    {
        // Gather data on collect
        $this->set_data(config()->all());
        return parent::collect();
    }
}