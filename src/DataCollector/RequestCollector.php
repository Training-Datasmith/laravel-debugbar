<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Data_Collector;

use Debug_Bar\Bridge\Symfony\Symfony_Request_Collector;
use Debug_Bar\Data_Collector\Data_Collector_Interface;
use Debug_Bar\Data_Collector\Renderable;
use Fruitcake\Laravel_Debugbar\Laravel_Debugbar;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Laravel\Telescope\Incoming_Entry;
use Laravel\Telescope\Telescope;
use Livewire\Mechanisms\Handle_Components\Handle_Components;
use Symfony\Component\Console\Input\Argv_Input;
class Request_Collector extends Symfony_Request_Collector implements Data_Collector_Interface, Renderable
{
    /**
     * {@inheritdoc}
     */
    public function collect(): array
    {
        if ($job = debugbar()->get_processing_job()) {
            return $this->collect_job($job);
        }
        if (app()->running_in_console()) {
            return $this->collect_cli();
        }
        $this->request = request();
        $result = parent::collect();
        if ($this->request->has_session()) {
            $session_attributes = $this->hide_masked_values($this->request->session()->all());
            $session_attributes = $this->get_data_formatter()->format_var($session_attributes);
            $result['data']['session_attributes'] = $session_attributes;
        }
        $result['tooltip'] += ['full_url' => Str::limit($this->request->full_url(), 100)];
        $html_data = [];
        $route = $this->request->route();
        if ($route) {
            // @phpstan-ignore-line despite what phpdocs say, this can return null
            $html_data += $this->get_route_information($this->request->route());
            $result['tooltip'] += ['action_name' => $route->get_name(), 'controller_action' => $route->get_action_name()];
        }
        if (class_exists(Telescope::class) && class_exists(Incoming_Entry::class) && Telescope::is_recording()) {
            $entry = Incoming_Entry::make(['requestId' => app(Laravel_Debugbar::class)->get_current_request_id()])->type('debugbar');
            Telescope::$entries_queue[] = $entry;
            $url = route('debugbar.telescope', [$entry->uuid]);
            $html_data['telescope'] = '<a href="' . $url . '" target="_blank" class="phpdebugbar-widgets-external-link">View in Telescope</a>';
        }
        unset($html_data['as'], $html_data['uses']);
        $result['data'] = $html_data + $result['data'];
        return $result;
    }
    protected function collect_cli(): array
    {
        $argv = new Argv_Input();
        $command = $argv->get_first_argument();
        $commands = Artisan::all();
        $command_class = $commands[$command] ?? null;
        $data = ['method' => 'CLI', 'command' => $command, 'command_class' => $command_class, 'args' => (new Argv_Input())->get_raw_tokens(), 'request_server' => $this->request->server->all()];
        $data = $this->hide_masked_values($data);
        foreach ($data as $key => $var) {
            if (!is_string($var)) {
                $data[$key] = $this->get_data_formatter()->format_var($var);
            }
        }
        if ($command_class) {
            $reflector = new \ReflectionClass($command_class);
            $filename = $this->normalize_file_path($reflector->get_file_name());
            if ($link = $this->get_xdebug_link($reflector->get_file_name(), $reflector->get_start_line())) {
                $data['command_class'] = ['value' => sprintf('%s:%s-%s', $filename, $reflector->get_start_line(), $reflector->get_end_line()), 'xdebug_link' => $link];
            }
        }
        return ['data' => $data];
    }
    protected function collect_job(Job $job): array
    {
        $job_class = $job->resolve_queued_job_class();
        $data = ['method' => 'CLI', 'job' => $job->resolve_name(), 'job_class' => $job_class, 'job_id' => $job->get_job_id(), 'connection' => $job->get_connection_name(), 'queue' => $job->get_queue(), 'payload' => $job->payload()];
        $data = $this->hide_masked_values($data);
        foreach ($data as $key => $var) {
            if (!is_string($var)) {
                $data[$key] = $this->get_data_formatter()->format_var($var);
            }
        }
        if ($job_class) {
            $reflector = new \ReflectionClass($job_class);
            $filename = $this->normalize_file_path($reflector->get_file_name());
            if ($link = $this->get_xdebug_link($reflector->get_file_name(), $reflector->get_start_line())) {
                $data['job_class'] = ['value' => sprintf('%s:%s-%s', $filename, $reflector->get_start_line(), $reflector->get_end_line()), 'xdebug_link' => $link];
            }
        }
        return ['data' => $data];
    }
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
                $component_data = $this->request->request->all()['components'][0] ?? null;
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
                if (isset($result['controller']) && is_string($result['controller'])) {
                    $result['controller'] = ['value' => $result['controller'], 'xdebug_link' => $link];
                }
            }
        }
        if (isset($result['middleware']) && is_array($result['middleware'])) {
            $middleware = implode(', ', $result['middleware']);
            unset($result['middleware']);
            $result['middleware'] = $middleware;
        }
        return array_filter($result);
    }
}