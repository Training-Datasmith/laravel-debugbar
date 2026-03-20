<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Data_Collector;

use Debug_Bar\Data_Collector\Data_Collector;
use Debug_Bar\Data_Collector\Renderable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
/**
 * Collector for Laravel's Auth provider
 */
class Multi_Auth_Collector extends Data_Collector implements Renderable
{
    /** @var bool */
    protected $show_name = false;
    /** @var bool */
    protected $show_guards_data = true;
    public function __construct(protected array $guards = [])
    {
    }
    /**
     * Set to show the users name/email
     */
    public function set_show_name(bool $show_name): void
    {
        $this->show_name = $show_name;
    }
    /**
     * Set to hide the guards tab, and show only name
     */
    public function set_show_guards_data(bool $show_guards_data): void
    {
        $this->show_guards_data = $show_guards_data;
    }
    /**
     * @{inheritDoc}
     */
    public function collect(): array
    {
        $data = ['guards' => []];
        $names = '';
        foreach ($this->guards as $guard_name => $config) {
            try {
                $guard = auth()->guard($guard_name);
                if ($guard->has_user()) {
                    $user = $guard->user();
                    if (!is_null($user)) {
                        $data['guards'][$guard_name] = $this->get_user_information($user);
                        $names .= $guard_name . ': ' . $data['guards'][$guard_name]['name'] . ', ';
                    }
                } else {
                    $data['guards'][$guard_name] = null;
                }
            } catch (\Exception) {
                continue;
            }
        }
        foreach ($data['guards'] as $key => $var) {
            $data['guards'][$key] = $this->get_data_formatter()->format_var($var);
        }
        $data['names'] = rtrim($names, ', ');
        if (!$this->show_guards_data) {
            unset($data['guards']);
        }
        return $data;
    }
    /**
     * Get displayed user information
     */
    protected function get_user_information(mixed $user = null): array
    {
        // Defaults
        if (is_null($user)) {
            return ['name' => 'Guest', 'user' => ['guest' => true]];
        }
        // The default auth identifer is the ID number, which isn't all that
        // useful. Try username, email and name.
        $identifier = $user instanceof Authenticatable ? $user->get_auth_identifier() : $user->get_key();
        if (is_numeric($identifier) || Str::is_uuid($identifier) || Str::is_ulid($identifier)) {
            try {
                if (isset($user->username)) {
                    $identifier = $user->username;
                } elseif (isset($user->email)) {
                    $identifier = $user->email;
                } elseif (isset($user->name)) {
                    $identifier = Str::limit($user->name, 24);
                }
            } catch (\Throwable) {
            }
        }
        return ['name' => $identifier, 'user' => $user instanceof Arrayable ? $user->to_array() : $user];
    }
    /**
     * @{inheritDoc}
     */
    public function get_name(): string
    {
        return 'auth';
    }
    /**
     * @{inheritDoc}
     */
    public function get_widgets(): array
    {
        $widgets = [];
        if ($this->show_guards_data) {
            $widget = match (true) {
                $this->is_json_var_dumper_used() => 'PhpDebugBar.Widgets.JsonVariableListWidget',
                $this->is_html_var_dumper_used() => 'PhpDebugBar.Widgets.HtmlVariableListWidget',
                default => 'PhpDebugBar.Widgets.VariableListWidget',
            };
            $widgets['auth'] = ['icon' => 'lock', 'widget' => $widget, 'map' => 'auth.guards', 'default' => '{}'];
        }
        if ($this->show_name) {
            $widgets['auth.name'] = ['icon' => 'user', 'tooltip' => 'Auth status', 'map' => 'auth.names', 'default' => ''];
        }
        return $widgets;
    }
}