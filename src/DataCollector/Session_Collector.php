<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Data_Collector;

use Debug_Bar\Data_Collector\Data_Collector;
use Debug_Bar\Data_Collector\Data_Collector_Interface;
use Debug_Bar\Data_Collector\Renderable;
class Session_Collector extends Data_Collector implements Data_Collector_Interface, Renderable
{
    /**
     * {@inheritdoc}
     */
    public function collect(): array
    {
        $data = $this->hide_masked_values(session()->all());
        foreach ($data as $key => $value) {
            $data[$key] = is_string($value) ? $value : $this->get_data_formatter()->format_var($value);
        }
        return $data;
    }
    /**
     * {@inheritDoc}
     */
    public function get_name(): string
    {
        return 'session';
    }
    /**
     * {@inheritDoc}
     */
    public function get_widgets(): array
    {
        $widget = match (true) {
            $this->is_json_var_dumper_used() => 'PhpDebugBar.Widgets.JsonVariableListWidget',
            $this->is_html_var_dumper_used() => 'PhpDebugBar.Widgets.HtmlVariableListWidget',
            default => 'PhpDebugBar.Widgets.VariableListWidget',
        };
        return ['session' => ['icon' => 'archive', 'widget' => $widget, 'map' => 'session', 'default' => '{}']];
    }
}