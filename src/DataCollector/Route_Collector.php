<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Data_Collector;

use Closure;
use Debug_Bar\Data_Collector\Data_Collector;
use Debug_Bar\Data_Collector\Renderable;
use Illuminate\Routing\Router;
use Livewire\Mechanisms\Handle_Components\Handle_Components;
/**
 * Based on Illuminate\Foundation\Console\RoutesCommand for Taylor Otwell
 * https://github.com/laravel/framework/blob/master/src/Illuminate/Foundation/Console/RoutesCommand.php
 *
 */
class Route_Collector extends Data_Collector implements Renderable
{
    /**
     * {@inheritDoc}
     */
    public function collect(): array
    {
        $route = app(Router::class)->current();
        return $this->get_route_information($route);
    }
    /**
     * Get the route information for a given route.
     */
    protected function get_route_information(mixed $route): array
    {
        if (!is_a($route, 'Illuminate\Routing\Route')) {
            return [];
        }
        $uri = head($route->methods()) . ' ' . $route->uri();
        $action = $route->get_action();
        $result = ['uri' => $uri];
        $result = array_merge($result, $action);
        $uses = $action['uses'] ?? null;
        $controller = is_string($action['controller'] ?? null) ? $action['controller'] : '';
        if (request()->has_header('X-Livewire') && class_exists(Handle_Components::class)) {
            try {
                $component_data = request('components')[0] ?? null;
                if (isset($component_data['snapshot'], $component_data['updates'])) {
                    $snapshot = json_decode($component_data['snapshot'], true);
                    if (count($component_data['updates']) > 0) {
                        $method = $component_data['updates'][array_key_first($component_data['updates'])] ?? null;
                    } else {
                        $method = null;
                    }
                    [$component] = app(Handle_Components::class)->from_snapshot($snapshot);
                    $result['controller'] = ltrim($component::class, '\\');
                    $reflector = new \ReflectionClass($component);
                    $controller = $component::class . '@' . $method;
                }
            } catch (\Throwable) {
            }
        }
        if (str_contains($controller, '@')) {
            [$controller, $method] = explode('@', $controller);
            if (class_exists($controller) && method_exists($controller, $method)) {
                $reflector = new \ReflectionMethod($controller, $method);
            }
            unset($result['uses']);
        } elseif ($uses instanceof \Closure) {
            $reflector = new \ReflectionFunction($uses);
            $result['uses'] = $this->get_data_formatter()->format_var($uses);
        } elseif (is_string($uses) && str_contains($uses, '@__invoke')) {
            if (class_exists($controller) && method_exists($controller, 'render')) {
                $reflector = new \ReflectionMethod($controller, 'render');
                $result['controller'] = $controller . '@render';
            }
        }
        if (isset($reflector)) {
            $filename = $this->normalize_file_path($reflector->get_file_name());
            $result['file'] = sprintf('%s:%s-%s', $filename, $reflector->get_start_line(), $reflector->get_end_line());
            if ($link = $this->get_xdebug_link($reflector->get_file_name(), $reflector->get_start_line())) {
                $result['file'] = ['value' => $result['file'], 'xdebug_link' => $link];
                if (isset($result['controller'])) {
                    $result['controller'] = ['value' => $result['controller'], 'xdebug_link' => $link];
                }
            }
        }
        if ($middleware = $this->get_middleware($route)) {
            $result['middleware'] = $middleware;
        }
        return array_filter($result);
    }
    /**
     * Get middleware
     */
    protected function get_middleware(mixed $route): string
    {
        return implode(', ', array_map(fn($middleware): mixed => $middleware instanceof Closure ? 'Closure' : $middleware, $route->gather_middleware()));
    }
    /**
     * {@inheritDoc}
     */
    public function get_name(): string
    {
        return 'route';
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
        return ['route' => ['icon' => 'share-3', 'widget' => $widget, 'map' => 'route', 'default' => '{}']];
    }
}