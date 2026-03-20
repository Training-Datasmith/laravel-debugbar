<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar;

use Debug_Bar\Data_Formatter\Data_Formatter;
use Debug_Bar\Data_Formatter\Data_Formatter_Interface;
use Debug_Bar\Debug_Bar;
use Fruitcake\Laravel_Debugbar\Console\Clear_Command;
use Fruitcake\Laravel_Debugbar\Support\Octane\Reset_Debugbar;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Cookie\Middleware\Encrypt_Cookies;
use Illuminate\Foundation\Events\Terminating;
use Illuminate\Foundation\Http\Events\Request_Handled;
use Illuminate\Queue\Events\Job_Processed;
use Illuminate\Queue\Events\Job_Processing;
use Illuminate\Support\Collection;
use Laravel\Octane\Events\Request_Received;
class Service_Provider extends \Illuminate\Support\Service_Provider
{
    /**
     * Register the service provider.
     *
     */
    public function register(): void
    {
        $config_path = __DIR__ . '/../config/debugbar.php';
        $this->merge_config_from($config_path, 'debugbar');
        $this->app->alias(Data_Formatter::class, Data_Formatter_Interface::class);
        $this->app->singleton(Laravel_Debugbar::class);
        $this->app->alias(Laravel_Debugbar::class, 'debugbar');
        $this->app->alias(Laravel_Debugbar::class, Debug_Bar::class);
        Collection::macro('debug', function (): \Illuminate\Support\Collection {
            debug($this);
            return $this;
        });
    }
    /**
     * Bootstrap the application events.
     *
     */
    public function boot(Dispatcher $events): void
    {
        if ($this->app->running_in_console()) {
            $config_path = __DIR__ . '/../config/debugbar.php';
            $this->publishes([$config_path => $this->get_config_path()], 'config');
            $this->commands([Clear_Command::class]);
        }
        // Eearly return if debugbar can not enabled
        if (!Laravel_Debugbar::can_be_enabled()) {
            return;
        }
        $this->load_routes_from(__DIR__ . '/debugbar-routes.php');
        // Resolve the LaravelDebugbar instance during boot to force it to be loaded in the Octane sandbox
        try {
            $debugbar = $this->app->make(Laravel_Debugbar::class);
        } catch (\Throwable $e) {
            // Errors can occur when removing LaravelDebugbar with composer scripts, when php-debugbar is not installed
            report($e);
            return;
        }
        // Reset the debugbar instance on each new Octane request
        $events->listen(Request_Received::class, Reset_Debugbar::class);
        // Handle response
        $events->listen(Request_Handled::class, function ($event) use ($debugbar): void {
            $debugbar->handle_response($event->request, $event->response);
        });
        // Store any data collected during termination but not already stored
        $events->listen(Terminating::class, function ($event) use ($debugbar): void {
            $debugbar->terminate();
        });
        if (config('debugbar.collect_jobs')) {
            $events->listen(Job_Processing::class, function (Job_Processing $event) use ($debugbar): void {
                // Sync jobs in non-console jobs are just requests
                if ($event->connection_name === 'sync' && !$this->app->running_in_console()) {
                    return;
                }
                $debugbar->enable();
                $debugbar->set_processing_job($event->job);
            });
            $events->listen(Job_Processed::class, function (Job_Processed $event) use ($debugbar): void {
                if ($debugbar->get_processing_job()) {
                    $debugbar->collect();
                    $debugbar->set_processing_job(null);
                    $debugbar->reset();
                }
            });
        }
        // Exclude debugbar cookies from encryption
        Encrypt_Cookies::except($debugbar->get_stack_data_session_namespace());
        // Attach listeners when debugbar should be enabled
        if ($debugbar->is_enabled() && !$debugbar->request_is_excluded($this->app['request'])) {
            $debugbar->boot();
        }
        // Register boot time, regardless of already being booted
        $this->booted(fn() => $debugbar->booted());
    }
    /**
     * Get the config path
     *
     */
    protected function get_config_path(): string
    {
        return config_path('debugbar.php');
    }
}