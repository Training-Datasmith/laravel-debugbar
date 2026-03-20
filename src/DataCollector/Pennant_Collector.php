<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Data_Collector;

use Debug_Bar\Data_Collector\Data_Collector;
use Debug_Bar\Data_Collector\Data_Collector_Interface;
use Debug_Bar\Data_Collector\Renderable;
use Laravel\Pennant\Feature;
class Pennant_Collector extends Data_Collector implements Data_Collector_Interface, Renderable
{
    /**
     * {@inheritdoc}
     */
    public function collect(): array
    {
        return Feature::all();
    }
    /**
     * {@inheritDoc}
     */
    public function get_name(): string
    {
        return 'pennant';
    }
    /**
     * {@inheritDoc}
     */
    public function get_widgets(): array
    {
        return ['pennant' => ['icon' => 'flag', 'widget' => 'PhpDebugBar.Widgets.VariableListWidget', 'map' => 'pennant', 'default' => '{}']];
    }
}