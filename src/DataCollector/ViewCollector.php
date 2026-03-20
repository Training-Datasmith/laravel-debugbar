<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Data_Collector;

use Debug_Bar\Data_Collector\Template_Collector;
use Illuminate\Support\Str;
use Illuminate\View\View;
class View_Collector extends Template_Collector
{
    public function get_name(): string
    {
        return 'views';
    }
    /**
     * Add a View instance to the Collector
     */
    public function add_view(View $view): void
    {
        $name = $view->get_name();
        $type = null;
        $data = $view->get_data();
        $path = $view->get_path();
        // Skip View files from strings
        if (Str::starts_with($name, '__components::')) {
            if ($source = $this->get_render_source($name, $path)) {
                [$name, $type, $data, $path] = $source;
            }
        }
        if (is_object($path)) {
            $type = $view::class;
            $path = null;
        }
        if ($path && $type !== 'livewire') {
            if (!$type) {
                if (str_ends_with($path, '.blade.php')) {
                    $type = 'blade';
                } else {
                    $type = pathinfo($path, PATHINFO_EXTENSION);
                }
            }
            $short_path = $this->normalize_file_path($path);
            foreach ($this->exclude_paths as $exclude_path) {
                if (str_starts_with($short_path, $exclude_path)) {
                    return;
                }
            }
        }
        $this->add_template($name, $data, $type, $path);
    }
    private function get_render_source(string $name, ?string $path): ?array
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 20);
        $component = null;
        $render = null;
        $view = null;
        foreach ($backtrace as $trace) {
            $function = $trace['function'] ?? null;
            //@phpstan-ignore-line
            $class = $trace['class'] ?? null;
            $file = $trace['file'] ?? null;
            $object = $trace['object'] ?? null;
            // Found an invokable class
            if ($function === '__invoke' && $class === 'Livewire\Component' && $object && !$component) {
                /** @var \Livewire\Component $component */
                $component = $trace['object'];
                $name = $component::class;
                $type = 'livewire';
                $path = (new \ReflectionClass($component))->get_file_name();
                $component = [$name, $type, [], $path];
            }
            if (($function === 'render' && $class === 'Illuminate\View\Compilers\BladeCompiler' || $function === '__callStatic' && $class === 'Illuminate\Support\Facades\Facade' && ($trace['args'][0] ?? null) === 'render') && !str_contains((string) $file, '/Illuminate/') && !$render) {
                $render = [$name, 'render', [], $file];
            }
            if (!$view && $class === 'Illuminate\View\View' && $object instanceof View && !str_starts_with($object->get_name(), '__components::')) {
                $view = [$object->get_name(), null, $object->get_data(), $object->get_path()];
            }
        }
        if ($component) {
            return $component;
        }
        if ($render) {
            return $render;
        }
        if ($view) {
            return $view;
        }
        return null;
    }
}