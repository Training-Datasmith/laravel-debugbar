<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Data_Collector;

use Debug_Bar\Data_Collector\Template_Collector;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Livewire\Component;
/**
 * Collector for Models.
 */
class Livewire_Collector extends Template_Collector
{
    public function add_livewire_component(Component $component, ?Request $request = null): void
    {
        $id = $component->get_id();
        $data = $component->all();
        if ((new \ReflectionClass($component))->is_anonymous()) {
            $key = Str::ascii($component->get_name()) . ' #' . $id;
        } else {
            $key = $component::class . ' ' . $component->get_name() . ' #' . $id;
        }
        if ($request && $request->request->get('id') === $id) {
            $data['#oldData'] = $request->request->get('data');
            $data['#actionQueue'] = $request->request->get('actionQueue');
        }
        $data['#name'] = $component->get_name();
        $data['#component'] = $component::class;
        $data['#id'] = $id;
        $path = (new \ReflectionClass($component))->get_file_name();
        $this->add_template($key, $data, 'livewire', $path);
    }
    /**
     * @return array{nb_templates: int, templates: array<string, array{name: string, param_count: int, params: array<string, mixed>, type: string, xdebug_link?: string}>, sentence: string}
     */
    public function collect(): array
    {
        $data = parent::collect();
        $data['sentence'] = 'Livewire component' . ($data['nb_templates'] !== 1 ? 's' : '');
        return $data;
    }
    /**
     * {@inheritDoc}
     */
    public function get_name(): string
    {
        return 'livewire';
    }
    /**
     * @return array<string, array{icon: string, widget: string, map: string, default: string}>
     */
    public function get_widgets(): array
    {
        $widgets = parent::get_widgets();
        $widgets[$this->get_name()]['icon'] = 'brand-livewire';
        return $widgets;
    }
}