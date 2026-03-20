<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Data_Collector;

use Debug_Bar\Data_Collector\Messages_Collector;
use Debug_Bar\Data_Collector\Resettable;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
/**
 * Collector for Laravel's gate checks
 */
class Gate_Collector extends Messages_Collector implements Resettable
{
    protected array $reflection = [];
    protected int $backtrace_limit = 20;
    public function add_check(mixed $user, string|int $ability, mixed $result, array $arguments = []): void
    {
        $user_key = 'user';
        $user_id = null;
        if ($user) {
            $user_key = Str::snake(class_basename($user));
            $user_id = $user instanceof Authenticatable ? $user->get_auth_identifier() : $user->get_key();
        }
        $label = $result ? 'success' : 'error';
        if ($result instanceof Response) {
            $label = $result->allowed() ? 'success' : 'error';
        }
        $target = null;
        if (isset($arguments[0])) {
            if ($arguments[0] instanceof Model) {
                $model = $arguments[0];
                if ($model->get_key_name() && isset($model[$model->get_key_name()])) {
                    $target = $model::class . '(' . $model->get_key_name() . '=' . $model->get_key() . ')';
                } else {
                    $target = $model::class;
                }
                $arguments[0] = $target;
            } elseif (is_string($arguments[0])) {
                $target = $arguments[0];
            }
        }
        $this->add_message('{ability} {target}', $label, ['ability' => $ability, 'target' => $target, 'result' => $result, $user_key => $user_id, 'arguments' => $arguments]);
    }
    protected function get_stack_trace_item(array $stacktrace): array
    {
        foreach ($stacktrace as $trace) {
            if (!isset($trace['file'])) {
                continue;
            }
            if (str_ends_with($trace['file'], 'Illuminate/Routing/ControllerDispatcher.php')) {
                $trace = $this->find_controller_from_dispatcher($trace);
            } elseif (str_starts_with($trace['file'], storage_path())) {
                $hash = pathinfo($trace['file'], PATHINFO_FILENAME);
                if ($file = $this->find_view_from_hash($hash)) {
                    $trace['file'] = $file;
                }
            }
            if ($this->file_is_in_excluded_path($trace['file'])) {
                continue;
            }
            return $trace;
        }
        return $stacktrace[0];
    }
    /**
     * Find the route action file
     */
    protected function find_controller_from_dispatcher(array $trace): array
    {
        /** @var \Closure|string|array $action */
        $action = app(Router::class)->current()->get_action('uses');
        if (is_string($action)) {
            [$controller, $method] = explode('@', $action);
            $reflection = new \ReflectionMethod($controller, $method);
            $trace['file'] = $reflection->get_file_name();
            $trace['line'] = $reflection->get_start_line();
        } elseif ($action instanceof \Closure) {
            $reflection = new \ReflectionFunction($action);
            $trace['file'] = $reflection->get_file_name();
            $trace['line'] = $reflection->get_start_line();
        }
        return $trace;
    }
    /**
     * Find the template name from the hash.
     */
    protected function find_view_from_hash(string $hash): ?string
    {
        $finder = app('view')->get_finder();
        if (isset($this->reflection['viewfinderViews'])) {
            $property = $this->reflection['viewfinderViews'];
        } else {
            $reflection = new \ReflectionClass($finder);
            $property = $reflection->get_property('views');
            $this->reflection['viewfinderViews'] = $property;
        }
        $xxh128Exists = in_array('xxh128', hash_algos(), true);
        foreach ($property->get_value($finder) as $path) {
            if ($xxh128Exists && hash('xxh128', 'v2' . $path) === $hash || sha1('v2' . $path) === $hash) {
                return $path;
            }
        }
        return null;
    }
    public function reset(): void
    {
        $this->reflection = [];
    }
}