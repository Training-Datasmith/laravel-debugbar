<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar;

use Debug_Bar\Bridge\Symfony\Symfony_Http_Driver;
use Debug_Bar\Data_Collector\Data_Collector;
use Debug_Bar\Data_Collector\Data_Collector_Interface;
use Debug_Bar\Data_Collector\Exceptions_Collector;
use Debug_Bar\Data_Collector\Messages_Collector;
use Debug_Bar\Data_Collector\Time_Data_Collector;
use Debug_Bar\Data_Formatter\Json_Data_Formatter;
use Debug_Bar\Debug_Bar;
use Debug_Bar\Http_Driver_Interface;
use Debug_Bar\Javascript_Renderer;
use Debug_Bar\Request_Id_Generator_Interface;
use Debug_Bar\Storage\File_Storage;
use Debug_Bar\Storage\Pdo_Storage;
use Debug_Bar\Storage\Redis_Storage;
use Debug_Bar\Storage\Sqlite_Storage;
use Exception;
use Fruitcake\Laravel_Debugbar\Collector_Providers\Auth_Collector_Provider;
use Fruitcake\Laravel_Debugbar\Collector_Providers\Cache_Collector_Provider;
use Fruitcake\Laravel_Debugbar\Collector_Providers\Config_Collector_Provider;
use Fruitcake\Laravel_Debugbar\Collector_Providers\Database_Collector_Provider;
use Fruitcake\Laravel_Debugbar\Collector_Providers\Events_Collector_Collector_Provider;
use Fruitcake\Laravel_Debugbar\Collector_Providers\Exceptions_Collector_Provider;
use Fruitcake\Laravel_Debugbar\Collector_Providers\Gate_Collector_Provider;
use Fruitcake\Laravel_Debugbar\Collector_Providers\Http_Client_Collector_Provider;
use Fruitcake\Laravel_Debugbar\Collector_Providers\Inertia_Collector_Provider;
use Fruitcake\Laravel_Debugbar\Collector_Providers\Jobs_Collector_Provider;
use Fruitcake\Laravel_Debugbar\Collector_Providers\Laravel_Collector_Provider;
use Fruitcake\Laravel_Debugbar\Collector_Providers\Livewire_Collector_Provider;
use Fruitcake\Laravel_Debugbar\Collector_Providers\Log_Collector_Provider;
use Fruitcake\Laravel_Debugbar\Collector_Providers\Logs_Collector_Provider;
use Fruitcake\Laravel_Debugbar\Collector_Providers\Mail_Collector_Provider;
use Fruitcake\Laravel_Debugbar\Collector_Providers\Memory_Collector_Provider;
use Fruitcake\Laravel_Debugbar\Collector_Providers\Messages_Collector_Provider;
use Fruitcake\Laravel_Debugbar\Collector_Providers\Models_Collector_Provider;
use Fruitcake\Laravel_Debugbar\Collector_Providers\Pennant_Collector_Provider;
use Fruitcake\Laravel_Debugbar\Collector_Providers\Php_Info_Collector_Provider;
use Fruitcake\Laravel_Debugbar\Collector_Providers\Request_Collector_Provider;
use Fruitcake\Laravel_Debugbar\Collector_Providers\Route_Collector_Provider;
use Fruitcake\Laravel_Debugbar\Collector_Providers\Session_Collector_Provider;
use Fruitcake\Laravel_Debugbar\Collector_Providers\Time_Collector_Provider;
use Fruitcake\Laravel_Debugbar\Collector_Providers\Views_Collector_Provider;
use Fruitcake\Laravel_Debugbar\Data_Collector\Request_Collector;
use Fruitcake\Laravel_Debugbar\Support\Clockwork\Clockwork_Collector;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\Argv_Input;
use Symfony\Component\Http_Foundation\Ip_Utils;
use Symfony\Component\Http_Foundation\Json_Response;
use Symfony\Component\Http_Foundation\Response as SymfonyResponse;
use Symfony\Component\Var_Dumper\Cloner\Stub;
use Throwable;
/**
 * Debug bar subclass which adds all without Request and with LaravelCollector.
 * Rest is added in Service Provider
 *
 * @method void emergency(...$message)
 * @method void alert(...$message)
 * @method void critical(...$message)
 * @method void error(...$message)
 * @method void warning(...$message)
 * @method void notice(...$message)
 * @method void info(...$message)
 * @method void debug(...$message)
 * @method void log(...$message)
 */
class Laravel_Debugbar extends Debug_Bar
{
    protected Application $app;
    protected Request $request;
    protected ?Job $processing_job = null;
    protected bool $booted = false;
    protected ?bool $enabled = null;
    protected ?bool $storage_open = null;
    /**
     * Laravel default error handler
     *
     * @var callable|null
     */
    protected $prev_error_handler;
    protected ?string $editor_template = null;
    protected bool $response_is_modified = false;
    protected Time_Data_Collector $time_collector;
    protected Messages_Collector $messages_collector;
    protected Exceptions_Collector $exceptions_collector;
    public function __construct(Application $app, Request $request)
    {
        $start_time = defined('LARAVEL_START') ? (float) LARAVEL_START : microtime(true);
        $this->app = $app;
        $this->request = $request;
        $this->time_collector = new Time_Data_Collector($start_time);
        $this->messages_collector = new Messages_Collector();
        $this->exceptions_collector = new Exceptions_Collector();
    }
    public function set_application(Application $app): void
    {
        $this->app = $app;
    }
    public function set_request(Request $request): void
    {
        $this->request = $request;
    }
    public function set_processing_job(?Job $job): void
    {
        $this->processing_job = $job;
    }
    public function get_processing_job(): ?Job
    {
        return $this->processing_job;
    }
    public function get_http_driver(): Http_Driver_Interface
    {
        if ($this->http_driver === null) {
            $this->http_driver = new Laravel_Http_Driver($this->request);
        }
        return $this->http_driver;
    }
    public function get_request_id_generator(): Request_Id_Generator_Interface
    {
        if ($this->request_id_generator === null) {
            $this->request_id_generator = new class implements Request_Id_Generator_Interface
            {
                public function generate(): string
                {
                    return (string) Str::ulid();
                }
            };
        }
        return $this->request_id_generator;
    }
    public function get_time_collector(): Time_Data_Collector
    {
        return $this->time_collector;
    }
    public function get_messages_collector(): Messages_Collector
    {
        return $this->messages_collector;
    }
    public function get_exceptions_collector(): Exceptions_Collector
    {
        return $this->exceptions_collector;
    }
    public function is_collecting(): bool
    {
        return $this->enabled && $this->booted;
    }
    /**
     * Enable the Debugbar and boot, if not already booted.
     */
    public function enable(): void
    {
        $this->enabled = true;
        if (!$this->booted) {
            $this->boot();
        }
    }
    /**
     * Boot the debugbar (add collectors, renderer and listener)
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $config = config();
        $this->editor_template = $config->get('debugbar.editor') ?: $config->get('app.editor');
        $this->remote_path_replacements = $this->get_remote_server_replacements();
        // Set custom error handler
        if ($config->get('debugbar.error_handler', false)) {
            // Get the error_level config, default to E_ALL
            $error_level = $config->get('debugbar.error_level', E_ALL);
            // set error handler with configured error reporting level
            $this->prev_error_handler = set_error_handler($this->handle_error(...), $error_level);
        }
        $this->select_storage($this);
        $this->register_data_formatter();
        $this->register_collectors();
        $this->booted = true;
    }
    public function booted(): void
    {
        $start_time = defined('LARAVEL_START') ? (float) LARAVEL_START : null;
        if ($start_time) {
            $this->add_measure('Booting', $start_time, microtime(true));
        }
        $this->start_measure('application', 'Application', 'time');
    }
    protected function register_collectors(): void
    {
        // Register default Collector Provider
        $this->register_collector_providers(['symfony_request' => Request_Collector_Provider::class, 'exceptions' => Exceptions_Collector_Provider::class, 'phpinfo' => Php_Info_Collector_Provider::class, 'messages' => Messages_Collector_Provider::class, 'time' => Time_Collector_Provider::class, 'memory' => Memory_Collector_Provider::class, 'laravel' => Laravel_Collector_Provider::class, 'events' => Events_Collector_Collector_Provider::class, 'views' => Views_Collector_Provider::class, 'route' => Route_Collector_Provider::class, 'log' => Log_Collector_Provider::class, 'logs' => Logs_Collector_Provider::class, 'db' => Database_Collector_Provider::class, 'models' => Models_Collector_Provider::class, 'livewire' => Livewire_Collector_Provider::class, 'inertia' => Inertia_Collector_Provider::class, 'mail' => Mail_Collector_Provider::class, 'auth' => Auth_Collector_Provider::class, 'gate' => Gate_Collector_Provider::class, 'cache' => Cache_Collector_Provider::class, 'jobs' => Jobs_Collector_Provider::class, 'pennant' => Pennant_Collector_Provider::class, 'config' => Config_Collector_Provider::class, 'session' => Session_Collector_Provider::class, 'http_client' => Http_Client_Collector_Provider::class]);
        // Register any Custom Collectors
        $this->register_custom_collector_providers(config('debugbar.custom_collectors', []));
    }
    /**
     * @param array<string, string> $providers
     */
    protected function register_collector_providers(array $providers): void
    {
        /** @var Repository $config */
        $config = $this->app->get(Repository::class);
        foreach ($providers as $name => $provider) {
            if (!$this->should_collect($name)) {
                continue;
            }
            try {
                $options = $config->get('debugbar.options.' . $name, []);
                $this->app->call($provider, ['options' => $options]);
            } catch (Exception $e) {
                $this->add_collector_exception('Error calling ' . class_basename($provider), $e);
            }
        }
    }
    /**
     * @param array<string, bool> $providers
     */
    protected function register_custom_collector_providers(array $providers): void
    {
        foreach ($providers as $provider => $enabled) {
            if (!$enabled) {
                continue;
            }
            try {
                $provider = $this->app->make($provider);
                // Add collectors directly, otherwise invoke the class
                if (is_a($provider, Data_Collector_Interface::class)) {
                    $this->add_collector($provider);
                } else {
                    $this->app->call($provider);
                }
            } catch (Exception $e) {
                $this->add_collector_exception('Error calling ' . class_basename($provider), $e);
            }
        }
    }
    /**
     * Register some Casters to avoid large objects for events etc.
     */
    protected function register_data_formatter(): void
    {
        $formatter = new Json_Data_Formatter();
        $formatter->merge_cloner_options(['casters' => [\Illuminate\View\View::class => static fn(\Illuminate\View\View $view, array $a, Stub $stub): array => ['name' => $view->get_name(), 'data' => $view->get_data(), 'path' => $view->get_path(), 'engine' => $view->get_engine()::class, 'factory' => $view->get_factory()::class], \Illuminate\Database\Connection_Interface::class => static fn(\Illuminate\Database\Connection_Interface $connection, array $a, Stub $stub): array => ['database' => $connection->get_database_name()]]]);
        Data_Collector::set_default_data_formatter($formatter);
    }
    public function get_javascript_renderer(?string $base_url = null, ?string $base_path = null): Javascript_Renderer
    {
        if ($this->js_renderer !== null) {
            return $this->js_renderer;
        }
        $renderer = new Javascript_Renderer($this, $base_url, $base_path);
        $config = config();
        $renderer->set_hide_empty_tabs($config->get('debugbar.hide_empty_tabs', true));
        $renderer->set_include_vendors($config->get('debugbar.include_vendors', true));
        $renderer->set_bind_ajax_handler_to_fetch($config->get('debugbar.capture_ajax', true));
        $renderer->set_bind_ajax_handler_to_xhr($config->get('debugbar.capture_ajax', true));
        $renderer->set_defer_datasets($config->get('debugbar.defer_datasets', false));
        $renderer->set_use_dist_files($config->get('debugbar.use_dist_files', true));
        $renderer->set_ajax_handler_auto_show($config->get('debugbar.ajax_handler_auto_show', true));
        $renderer->set_ajax_handler_enable_tab($config->get('debugbar.ajax_handler_enable_tab', true));
        $renderer->set_theme($config->get('debugbar.theme', 'auto'));
        $renderer->set_asset_handler_url(route('debugbar.assets'));
        $renderer->add_assets(cssFiles: ['laravel-debugbar.css', 'laravel-icons.css'], basePath: __DIR__ . '/../resources');
        if ($this->get_storage()) {
            $renderer->set_open_handler_url(route('debugbar.openhandler'));
        }
        $this->js_renderer = $renderer;
        return $this->js_renderer;
    }
    public function should_collect(string $name, bool $default = true): bool
    {
        return config('debugbar.collectors.' . $name, $default);
    }
    /**
     * Handle silenced errors
     */
    public function handle_error(int $level, string $message, string $file = '', int $line = 0, array $context = []): mixed
    {
        if ($this->has_collector('exceptions')) {
            /** @var ExceptionsCollector $exceptionCollector */
            $exception_collector = $this['exceptions'];
            $exception_collector->add_warning($level, $message, $file, $line);
        }
        if ($this->has_collector('messages')) {
            /** @var MessagesCollector $messagesCollector */
            $messages_collector = $this['messages'];
            $file = $file ? ' on ' . $messages_collector->normalize_file_path($file) . ":{$line}" : '';
            $messages_collector->add_message($message . $file, 'deprecation');
        }
        if (!$this->prev_error_handler) {
            return null;
        }
        return call_user_func($this->prev_error_handler, $level, $message, $file, $line, $context);
    }
    /**
     * Starts a measure
     *
     * @param string      $name  Internal name, used to stop the measure
     * @param string|null $label Public name
     */
    public function start_measure(string $name, ?string $label = null, ?string $collector = null, ?string $group = null): void
    {
        $this->time_collector->start_measure($name, $label, $collector, $group);
    }
    /**
     * Stops a measure
     */
    public function stop_measure(string $name): void
    {
        try {
            $this->time_collector->stop_measure($name);
        } catch (Exception $e) {
            $this->add_throwable($e);
        }
    }
    /**
     * Alias for addThrowable
     *
     */
    public function add_exception(Throwable $e): void
    {
        $this->add_throwable($e);
    }
    /**
     * Adds an exception to be profiled in the debug bar
     */
    public function add_throwable(Throwable $e): void
    {
        $this->exceptions_collector->add_throwable($e);
    }
    /**
     * Register collector exceptions
     *
     */
    protected function add_collector_exception(string $message, Exception $exception): void
    {
        $this->add_throwable(new Exception($message . ' on Laravel Debugbar: ' . $exception->get_message(), (int) $exception->get_code(), $exception));
    }
    /**
     * Modify the response and inject the debugbar (or data in headers)
     */
    public function handle_response(Request $request, Symfony_Response $response): Symfony_Response
    {
        $this->set_request($request);
        if ($this->response_is_modified || !$this->booted || !$this->is_enabled() || $this->is_debugbar_request($request) || $this->request_is_excluded($request)) {
            return $response;
        }
        $config = $this->app->get(Repository::class);
        // Prevent duplicate modification
        $this->response_is_modified = true;
        // These rely on the Response, so we add them directly here
        $http_driver = $this->get_http_driver();
        if ($http_driver instanceof Laravel_Http_Driver) {
            $http_driver->set_request($request);
            $http_driver->set_response($response);
        } elseif ($http_driver instanceof Symfony_Http_Driver) {
            $http_driver->set_response($response);
        }
        // Show the Http Response Exception in the Debugbar, when available
        if ($response instanceof Response && isset($response->exception)) {
            $this->add_throwable($response->exception);
        }
        // Update collectors that use the request/response
        if ($this->has_collector('request')) {
            $collector = $this->get_collector('request');
            if ($collector instanceof Request_Collector) {
                $collector->set_response($response);
            }
        }
        if ($config->get('debugbar.clockwork') && !$this->has_collector('clockwork')) {
            try {
                $clockwork_collector = new Clockwork_Collector($request, $response);
                $this->add_collector($clockwork_collector);
            } catch (Exception $e) {
                $this->add_collector_exception('Cannot add ClockworkCollector', $e);
            }
            $this->add_clockwork_headers($response);
        }
        if ($config->get('debugbar.add_ajax_timing', false)) {
            $this->add_server_timing_headers($response);
        }
        if ($response->is_redirection()) {
            try {
                $this->stack_data();
            } catch (Exception $e) {
                $this->app['log']->error('Debugbar exception: ' . $e->get_message(), ['exception' => $e]);
            }
            return $response;
        }
        try {
            // Collect + store data, only inject the ID in theheaders
            $this->send_data_in_headers(true);
        } catch (Exception $e) {
            $this->app['log']->error('Debugbar exception: ' . $e->get_message(), ['exception' => $e]);
        }
        // Check if it's safe to inject the Debugbar
        if ($config->get('debugbar.inject', true) && str_contains((string) $response->headers->get('Content-Type', 'text/html'), 'html') && !$this->is_json_request($request) && !$this->is_json_response($response) && $response->get_content() !== false && in_array($request->get_request_format(), [null, 'html'], true)) {
            try {
                $this->inject_debugbar($response);
            } catch (Exception $e) {
                $this->app['log']->error('Debugbar exception: ' . $e->get_message(), ['exception' => $e]);
            }
        }
        return $response;
    }
    public static function can_be_enabled(): bool
    {
        $app = app();
        return $app->has_debug_mode_enabled() && !$app->environment('testing', 'production');
    }
    /**
     * Check if the Debugbar is enabled
     */
    public function is_enabled(): bool
    {
        if ($this->enabled === null) {
            if (!static::can_be_enabled()) {
                $this->enabled = false;
            } else {
                $config_enabled = value(config('debugbar.enabled'));
                if ($config_enabled === null) {
                    $config_enabled = config('app.debug');
                }
                $this->enabled = $config_enabled && !$this->app->running_in_console();
            }
        }
        return $this->enabled;
    }
    public function is_storage_open(Request $request): bool
    {
        // Additional safeguards that may never have storage open
        if (!$this->is_enabled() || !config('app.debug') || app()->is_production()) {
            return false;
        }
        if ($this->storage_open === null) {
            $open = config('debugbar.storage.open');
            if (is_callable($open)) {
                $this->storage_open = $open($request);
                return $this->storage_open;
            }
            if (is_string($open) && class_exists($open)) {
                $this->storage_open = method_exists($open, 'resolve') ? $open::resolve($request) : false;
                return $this->storage_open;
            }
            if (is_bool($open)) {
                $this->storage_open = $open;
                return $this->storage_open;
            }
            // Allow localhost request when not explicitly allowed/disallowed
            $this->storage_open = Ip_Utils::is_private_ip($request->get_client_ip());
        }
        return $this->storage_open;
    }
    public function request_is_excluded(Request $request): bool
    {
        $except = config('debugbar.except') ?: [];
        if (!$except) {
            return false;
        }
        $except = array_map(fn($item): string => $item !== '/' ? trim((string) $item, '/') : $item, $except);
        return $request->is($except);
    }
    /**
     * Check if this is a request to the Debugbar OpenHandler
     */
    protected function is_debugbar_request(Request $request): bool
    {
        return $request->is(config('debugbar.route_prefix') . '*');
    }
    protected function is_json_request(Request $request): bool
    {
        // If XmlHttpRequest, Live or HTMX, return true
        if ($request->is_xml_http_request() || $request->headers->has('X-Livewire') || $request->headers->has('Hx-Request') && $request->headers->has('Hx-Target')) {
            return true;
        }
        // Check if the request wants Json
        $acceptable = $request->get_acceptable_content_types();
        if (isset($acceptable[0]) && in_array($acceptable[0], ['application/json', 'application/javascript'], true)) {
            return true;
        }
        return false;
    }
    protected function is_json_response(Symfony_Response $response): bool
    {
        if ($response instanceof Json_Response || $response->headers->get('Content-Type') === 'application/json') {
            return true;
        }
        $content = $response->get_content();
        if (is_string($content)) {
            $content = trim($content);
            if ($content === '') {
                return false;
            }
            // Quick check to see if it looks like JSON
            $first = $content[0];
            $last = $content[strlen($content) - 1];
            if ($first === '{' && $last === '}' || $first === '[' && $last === ']') {
                // Must contain a colon or comma
                return strpbrk($content, ':,') !== false;
            }
        }
        return false;
    }
    /**
     * Collects meta data about the current request
     */
    public function collect_meta_data(): array
    {
        $meta = ['id' => $this->get_current_request_id(), 'datetime' => date('Y-m-d H:i:s'), 'utime' => microtime(true), 'method' => $this->request->get_method(), 'uri' => $this->request->get_request_uri(), 'ip' => $this->request->get_client_ip()];
        if ($this->processing_job) {
            $meta['method'] = 'JOB';
            $meta['uri'] = $this->processing_job->resolve_name() . '@' . $this->processing_job->get_connection_name();
        } elseif ($this->app->running_in_console()) {
            $meta['method'] = 'CLI';
            $meta['uri'] = implode(' ', (new Argv_Input())->get_raw_tokens());
        }
        return $meta;
    }
    public function terminate(): void
    {
        if ($this->is_collecting() && $this->data === null && !$this->is_debugbar_request($this->request)) {
            $this->collect();
        }
    }
    /**
     * Injects the web debug toolbar into the given Response.
     *
     * Based on https://github.com/symfony/WebProfilerBundle/blob/master/EventListener/WebDebugToolbarListener.php
     */
    public function inject_debugbar(Symfony_Response $response): void
    {
        $content = $response->get_content();
        $renderer = $this->get_javascript_renderer();
        $widget = "<!-- Laravel Debugbar Widget -->\n" . $renderer->render_head() . $renderer->render();
        // Try to put the widget at the end, directly before the </body>
        $pos = strripos($content, '</body>');
        if (false !== $pos) {
            $content = substr($content, 0, $pos) . $widget . substr($content, $pos);
        } else {
            $content = $content . $widget;
        }
        $original = null;
        if ($response instanceof Response && $response->get_original_content()) {
            $original = $response->get_original_content();
        }
        // Update the new content and reset the content length
        $response->set_content($content);
        $response->headers->remove('Content-Length');
        // Restore original response (e.g. the View or Ajax data)
        if ($response instanceof Response && $original) {
            $response->original = $original;
        }
    }
    /**
     * Disable the Debugbar
     */
    public function disable(): void
    {
        $this->enabled = false;
    }
    public function reset(): void
    {
        parent::reset();
        $this->time_collector->reset();
        $this->exceptions_collector->reset();
        $this->messages_collector->reset();
        $this->enabled = null;
        $this->storage_open = null;
        $this->response_is_modified = false;
        $this->http_driver = null;
    }
    /**
     * Adds a measure
     */
    public function add_measure(string $label, float $start, ?float $end = null, array $params = [], ?string $collector = null, ?string $group = null): void
    {
        $this->time_collector->add_measure($label, $start, $end, $params, $collector, $group);
    }
    /**
     * Utility function to measure the execution of a Closure
     */
    public function measure(string $label, \Closure $closure, ?string $collector = null, ?string $group = null): mixed
    {
        return $this->time_collector->measure($label, $closure, $collector, $group);
    }
    /**
     * Magic calls for adding messages
     */
    public function __call(string $method, array $args): void
    {
        $message_levels = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug', 'log'];
        if (in_array($method, $message_levels, true)) {
            foreach ($args as $arg) {
                $this->add_message($arg, $method);
            }
        }
    }
    /**
     * Adds a message to the MessagesCollector
     *
     * A message can be anything from an object to a string
     */
    public function add_message(mixed $message, string $label = 'info', array $context = []): void
    {
        $this->messages_collector->add_message($message, $label, $context);
    }
    /**
     * Check the version of Laravel
     */
    public function check_version(string $version, string $operator = '>='): bool
    {
        return version_compare($this->app->version(), $version, $operator);
    }
    protected function select_storage(Debug_Bar $debugbar): void
    {
        /** @var Repository $config */
        $config = config();
        if ($config->get('debugbar.storage.enabled')) {
            $driver = strtolower((string) $config->get('debugbar.storage.driver', 'file'));
            switch ($driver) {
                case 'pdo':
                    $connection = $config->get('debugbar.storage.connection');
                    $table = $this->app['db']->get_table_prefix() . 'phpdebugbar';
                    $pdo = $this->app['db']->connection($connection)->get_pdo();
                    $storage = new Pdo_Storage($pdo, $table);
                    break;
                case 'redis':
                    $connection = $config->get('debugbar.storage.connection');
                    $client = $this->app['redis']->connection($connection);
                    if (is_a($client, 'Illuminate\Redis\Connections\Connection', false)) {
                        $client = $client->client();
                    }
                    $storage = new Redis_Storage($client);
                    break;
                case 'custom':
                    $class = $config->get('debugbar.storage.provider');
                    $storage = $this->app->make($class);
                    break;
                case 'socket':
                    throw new \RuntimeException('Socket storage is not supported anymore.');
                case 'file':
                    $path = $config->get('debugbar.storage.path');
                    $storage = new File_Storage($path);
                    break;
                case 'sqlite':
                    $path = $config->get('debugbar.storage.path');
                    $storage = new Sqlite_Storage($path . '/debugbar.sqlite');
                    break;
                default:
                    throw new \RuntimeException('Invalid storage selected: ' . $driver);
            }
            $debugbar->set_storage($storage);
        }
    }
    protected function add_clockwork_headers(Symfony_Response $response): void
    {
        $prefix = config('debugbar.route_prefix');
        $response->headers->set('X-Clockwork-Id', $this->get_current_request_id(), true);
        $response->headers->set('X-Clockwork-Version', '9', true);
        $response->headers->set('X-Clockwork-Path', $prefix . '/clockwork/', true);
    }
    /**
     * Add Server-Timing headers for the TimeData collector
     *
     * @see https://www.w3.org/TR/server-timing/
     */
    protected function add_server_timing_headers(Symfony_Response $response): void
    {
        if ($this->has_collector('time')) {
            $collector = $this->time_collector;
            $headers = [];
            foreach ($collector->collect()['measures'] as $m) {
                $headers[] = sprintf('app;desc="%s";dur=%F', str_replace(["\n", "\r"], ' ', str_replace('"', "'", $m['label'])), $m['duration'] * 1000);
            }
            $response->headers->set('Server-Timing', $headers, false);
        }
    }
    private function get_remote_server_replacements(): array
    {
        $local_path = config('debugbar.local_sites_path') ?: base_path();
        $remote_paths = array_filter(explode(',', config('debugbar.remote_sites_path') ?: '')) ?: [base_path()];
        return array_fill_keys($remote_paths, $local_path);
    }
}