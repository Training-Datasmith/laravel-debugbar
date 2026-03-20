<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Data_Collector;

use Debug_Bar\Data_Collector\Data_Collector;
use Debug_Bar\Data_Collector\Renderable;
use Illuminate\Support\Str;
class Laravel_Collector extends Data_Collector implements Renderable
{
    /**
     * {@inheritDoc}
     */
    public function collect(): array
    {
        $app = app();
        return ['version' => Str::of($app->version())->explode('.')->first() . '.x', 'tooltip' => array_filter(['Laravel Version' => $app->version(), 'PHP Version' => phpversion(), 'Environment' => $app->environment(), 'Debug Mode' => config('app.debug') ? 'Enabled' : 'Disabled', 'URL' => Str::of(config('app.url'))->replace(['http://', 'https://'], ''), 'Timezone' => config('app.timezone'), 'Locale' => config('app.locale'), 'Cached' => implode(', ', array_filter([$app->configuration_is_cached() ? 'Configs' : null, $app->routes_are_cached() ? 'Routes' : null, $app->events_are_cached() ? 'Events' : null]))])];
    }
    /**
     * {@inheritDoc}
     */
    public function get_name(): string
    {
        return 'laravel';
    }
    /**
     * {@inheritDoc}
     */
    public function get_widgets(): array
    {
        return ['version' => ['icon' => 'brand-laravel', 'map' => 'laravel.version', 'default' => ''], 'version:tooltip' => ['map' => 'laravel.tooltip', 'default' => '{}']];
    }
}