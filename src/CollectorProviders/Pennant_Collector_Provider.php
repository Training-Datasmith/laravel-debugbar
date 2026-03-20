<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Collector_Providers;

use Fruitcake\Laravel_Debugbar\Data_Collector\Pennant_Collector;
use Illuminate\Contracts\Foundation\Application;
use Laravel\Pennant\Feature_Manager;
class Pennant_Collector_Provider extends Abstract_Collector_Provider
{
    public function __invoke(Application $app, array $options): void
    {
        if (class_exists(Feature_Manager::class) && $app->bound(Feature_Manager::class)) {
            $this->add_collector(new Pennant_Collector());
        }
    }
}