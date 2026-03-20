<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Collector_Providers;

use Fruitcake\Laravel_Debugbar\Data_Collector\Livewire_Collector;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Livewire\Component;
use Livewire\Livewire;
class Livewire_Collector_Provider extends Abstract_Collector_Provider
{
    public function __invoke(Application $app, Request $request): void
    {
        if ($app->bound('livewire')) {
            $livewire_collector = new Livewire_Collector(true, [], false);
            $this->add_collector($livewire_collector);
            Livewire::listen('render', fn(Component $component) => $livewire_collector->add_livewire_component($component, $request));
        }
    }
}