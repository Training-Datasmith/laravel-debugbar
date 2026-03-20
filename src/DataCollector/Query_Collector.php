<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Data_Collector;

use Debug_Bar\Data_Collector\Asset_Provider;
use Debug_Bar\Data_Collector\Data_Collector;
use Debug_Bar\Data_Collector\Has_Time_Data_Collector;
use Debug_Bar\Data_Collector\Renderable;
use Debug_Bar\Data_Collector\Resettable;
use Debug_Bar\Data_Formatter\Query_Formatter;
use Fruitcake\Laravel_Debugbar\Support\Explain;
use Illuminate\Database\Events\Query_Executed;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Support\Str;
/**
 * Collects data about SQL statements executed with PDO
 */
class Query_Collector extends Data_Collector implements Renderable, Asset_Provider, Resettable
{
    use Has_Time_Data_Collector;
    protected array $queries = [];
    protected int $query_count = 0;
    protected int $transaction_events_count = 0;
    protected int $info_statements = 0;
    protected ?int $soft_limit = null;
    protected ?int $hard_limit = null;
    protected ?int $last_memory_usage = null;
    protected bool|int $find_source = false;
    protected array $middleware = [];
    protected bool $explain_query = false;
    protected bool $show_query_result = false;
    protected array $reflection = [];
    protected array $exclude_paths = [];
    protected array $backtrace_exclude_paths = ['/vendor/laravel/framework/src/Illuminate/Support', '/vendor/laravel/framework/src/Illuminate/Database', '/vendor/laravel/framework/src/Illuminate/Events', '/vendor/laravel/framework/src/Illuminate/Collections', '/vendor/october/rain', '/vendor/barryvdh/laravel-debugbar', '/vendor/fruitcake/laravel-debugbar'];
    protected ?Query_Formatter $query_formatter = null;
    protected bool $render_sql_with_params = false;
    protected bool $duration_background = false;
    protected ?float $slow_threshold = null;
    public function get_query_formatter(): Query_Formatter
    {
        if ($this->query_formatter === null) {
            $this->query_formatter = new Query_Formatter();
        }
        return $this->query_formatter;
    }
    /**
     * @param int|null $softLimit After the soft limit, no parameters/backtrace are captured
     * @param int|null $hardLimit After the hard limit, queries are ignored
     */
    public function set_limits(?int $soft_limit, ?int $hard_limit): void
    {
        $this->soft_limit = $soft_limit;
        $this->hard_limit = $hard_limit;
    }
    /**
     * Renders the SQL of traced statements with params embedded
     */
    public function set_render_sql_with_params(bool $enabled = true): void
    {
        $this->render_sql_with_params = $enabled;
    }
    /**
     * Enable/disable finding the source
     */
    public function set_find_source(bool|int $value, array $middleware): void
    {
        $this->find_source = $value;
        $this->middleware = $middleware;
    }
    public function merge_exclude_paths(array $exclude_paths): void
    {
        $this->exclude_paths = array_merge($this->exclude_paths, $exclude_paths);
    }
    /**
     * Set additional paths to exclude from the backtrace
     */
    public function merge_backtrace_exclude_paths(array $exclude_paths): void
    {
        $this->backtrace_exclude_paths = array_merge($this->backtrace_exclude_paths, $exclude_paths);
    }
    /**
     * Enable/disable the shaded duration background on queries
     */
    public function set_duration_background(bool $enabled): void
    {
        $this->duration_background = $enabled;
    }
    /**
     * Highlights queries that exceed the threshold
     *
     * @param int|float $threshold miliseconds value
     */
    public function set_slow_threshold(int|float $threshold): void
    {
        $this->slow_threshold = $threshold / 1000;
    }
    public function is_sql_rendered_with_params(): bool
    {
        return $this->render_sql_with_params;
    }
    /**
     * Enable/disable the EXPLAIN queries
     *
     * @deprecated use setExplainQuery()
     */
    public function set_explain_source(bool $enabled): void
    {
        $this->set_explain_query($enabled);
    }
    /**
     * Enable/disable the EXPLAIN queries
     */
    public function set_explain_query(bool $enabled): void
    {
        $this->explain_query = $enabled;
    }
    /**
     * Enable/disable the EXPLAIN queries
     */
    public function set_show_query_result(bool $enabled): void
    {
        $this->show_query_result = $enabled;
    }
    public function start_memory_usage(): void
    {
        $this->last_memory_usage = memory_get_usage(false);
    }
    public function add_query(Query_Executed $query): void
    {
        $this->query_count++;
        if ($this->hard_limit && $this->query_count > $this->hard_limit) {
            return;
        }
        $limited = $this->soft_limit && $this->query_count > $this->soft_limit;
        $sql = $query->sql;
        $time = $query->time / 1000;
        $end_time = microtime(true);
        $start_time = $end_time - $time;
        $source = [];
        if (!$limited && $this->find_source) {
            try {
                $source = $this->find_source();
            } catch (\Exception) {
            }
        }
        $bindings = match (true) {
            $limited && filled($query->bindings) => null,
            default => $query->connection->prepare_bindings($query->bindings),
        };
        $this->queries[] = ['query' => $sql, 'type' => 'query', 'bindings' => $bindings, 'start' => $start_time, 'time' => $time, 'memory' => $this->last_memory_usage ? memory_get_usage(false) - $this->last_memory_usage : 0, 'source' => $source, 'connection' => $query->connection, 'driver' => $query->connection->get_config('driver')];
        if ($this->has_time_data_collector()) {
            $this->add_time_measure(Str::limit($sql, 100), $start_time, $end_time, [], 'Database Query');
        }
    }
    /**
     * Use a backtrace to search for the origins of the query.
     */
    protected function find_source(): array
    {
        $stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS | DEBUG_BACKTRACE_PROVIDE_OBJECT, app('config')->get('debugbar.debug_backtrace_limit', 50));
        $sources = [];
        foreach ($stack as $index => $trace) {
            $sources[] = $this->parse_trace($index, $trace);
        }
        return array_slice(array_filter($sources), 0, is_int($this->find_source) ? $this->find_source : 5);
    }
    /**
     * Parse a trace element from the backtrace stack.
     */
    protected function parse_trace(int $index, array $trace): object|bool
    {
        $frame = (object) ['index' => $index, 'namespace' => null, 'name' => null, 'file' => null, 'line' => $trace['line'] ?? '1'];
        if (isset($trace['function']) && $trace['function'] === 'substituteBindings') {
            $frame->name = 'Route binding';
            return $frame;
        }
        if (isset($trace['class']) && isset($trace['file']) && !$this->file_is_in_excluded_path($trace['file'])) {
            $frame->file = $trace['file'];
            if (isset($trace['object']) && is_a($trace['object'], '\Twig\Template')) {
                [$frame->file, $frame->line] = $this->get_twig_info($trace);
            } elseif (str_contains($frame->file, storage_path())) {
                $hash = pathinfo($frame->file, PATHINFO_FILENAME);
                if ($frame->name = $this->find_view_from_hash($hash)) {
                    $frame->file = $frame->name[1];
                    $frame->name = $frame->name[0];
                } else {
                    $frame->name = $hash;
                }
                $frame->namespace = 'view';
                return $frame;
            } elseif (str_contains($frame->file, 'Middleware')) {
                $frame->name = $this->find_middleware_from_file($frame->file);
                if ($frame->name) {
                    $frame->namespace = 'middleware';
                } else {
                    $frame->name = $this->normalize_file_path($frame->file);
                }
                return $frame;
            }
            $frame->name = $this->normalize_file_path($frame->file);
            return $frame;
        }
        return false;
    }
    /**
     * Check if the given file is to be excluded from analysis
     */
    protected function file_is_in_excluded_path(string $file): bool
    {
        $normalized_path = str_replace('\\', '/', $file);
        foreach ($this->backtrace_exclude_paths as $excluded_path) {
            if (str_contains($normalized_path, (string) $excluded_path)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Find the middleware alias from the file.
     */
    protected function find_middleware_from_file(string $file): ?string
    {
        $filename = pathinfo($file, PATHINFO_FILENAME);
        foreach ($this->middleware as $alias => $class) {
            if (is_string($class) && str_contains($class, $filename)) {
                return $alias;
            }
        }
        return null;
    }
    /**
     * Find the template name from the hash.
     */
    protected function find_view_from_hash(string $hash): ?array
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
        foreach ($property->get_value($finder) as $name => $path) {
            if ($xxh128Exists && hash('xxh128', 'v2' . $path) === $hash || sha1('v2' . $path) === $hash) {
                return [$name, $path];
            }
        }
        return null;
    }
    /**
     * Get the filename/line from a Twig template trace
     */
    protected function get_twig_info(array $trace): array
    {
        $file = $trace['object']->get_template_name();
        if (isset($trace['line'])) {
            foreach ($trace['object']->get_debug_info() as $code_line => $template_line) {
                if ($code_line <= $trace['line']) {
                    return [$file, $template_line];
                }
            }
        }
        return [$file, -1];
    }
    /**
     * Adds a custom message to statements.
     */
    public function add_message(string $message): void
    {
        $this->info_statements++;
        $source = [];
        if ($this->find_source) {
            try {
                $source = $this->find_source();
            } catch (\Exception) {
            }
        }
        $this->queries[] = ['sql' => $message, 'type' => 'message', 'start' => microtime(true), ...count($source) ? ['xdebug_link' => $source[0]] : []];
    }
    /**
     * Collect a database transaction event.
     */
    public function collect_transaction_event(string $event, mixed $connection): void
    {
        $this->transaction_events_count++;
        $source = [];
        if ($this->find_source) {
            try {
                $source = $this->find_source();
            } catch (\Exception) {
            }
        }
        $this->queries[] = ['query' => $event, 'type' => 'transaction', 'bindings' => [], 'start' => microtime(true), 'time' => 0, 'memory' => 0, 'source' => $source, 'connection' => $connection, 'driver' => $connection->get_config('driver')];
    }
    /**
     * Reset the queries.
     */
    public function reset(): void
    {
        $this->queries = [];
        $this->query_count = 0;
        $this->info_statements = 0;
        $this->transaction_events_count = 0;
        $this->reflection = [];
    }
    /**
     * {@inheritDoc}
     */
    public function collect(): array
    {
        $total_time = 0;
        $total_memory = 0;
        $queries = $this->queries;
        $statements = [];
        $explain = new Explain();
        foreach ($queries as $query) {
            if ($query['type'] === 'message') {
                if (isset($query['xdebug_link'])) {
                    $source = $query['xdebug_link'];
                    $query['xdebug_link'] = $this->get_xdebug_link($source->file ?: '', $source->line);
                }
                $statements[] = $query;
                continue;
            }
            $source = reset($query['source']);
            $normalized_path = is_object($source) ? $this->normalize_file_path($source->file ?: '') : '';
            if ($query['type'] !== 'transaction' && Str::starts_with($normalized_path, $this->exclude_paths)) {
                continue;
            }
            $total_time += $query['time'];
            $total_memory += $query['memory'];
            $connection_name = $query['connection']->get_database_name();
            if ($connection_name && str_ends_with((string) $connection_name, '.sqlite')) {
                $connection_name = $this->normalize_file_path($connection_name);
            }
            $explain_modes = [];
            $is_readonly = $explain->is_read_only_query($query['query'] ?? '');
            $can_run_query = $this->show_query_result && $is_readonly;
            if ($can_run_query) {
                $explain_modes[] = 'result';
            }
            if ($is_readonly && $this->explain_query && $explain->is_raw_explain_supported($query['driver'], $query['bindings'])) {
                $explain_modes[] = 'explain';
            }
            $statements[] = ['sql' => $this->get_sql_query_to_display($query), 'type' => $query['type'], 'params' => $query['bindings'] ?? [], 'backtrace' => array_values($query['source']), 'start' => $query['start'] ?? null, 'duration' => $query['time'], 'duration_str' => $query['type'] === 'transaction' ? '' : $this->get_data_formatter()->format_duration($query['time']), 'slow' => $this->slow_threshold && $this->slow_threshold <= $query['time'], 'memory' => $query['memory'], 'memory_str' => $query['memory'] ? $this->get_data_formatter()->format_bytes($query['memory']) : null, 'filename' => $source ? $this->get_query_formatter()->format_source($source, true) : null, 'source' => $source, 'xdebug_link' => is_object($source) ? $this->get_xdebug_link($source->file ?: '', $source->line) : null, 'connection' => $connection_name, 'explain' => $explain_modes ? ['url' => route('debugbar.queries.explain'), 'driver' => $query['driver'], 'connection' => $query['connection']->get_name(), 'query' => $query['query'], 'modes' => $explain_modes, 'hash' => $explain->hash($query['connection']->get_name(), $query['query'], $query['bindings'])] : null];
        }
        if ($this->duration_background) {
            if ($total_time > 0) {
                // For showing background measure on Queries tab
                $start_percent = 0;
                foreach ($statements as $i => $statement) {
                    if (!isset($statement['duration'])) {
                        continue;
                    }
                    $width_percent = $statement['duration'] / $total_time * 100;
                    $statements[$i] = array_merge($statement, ['start_percent' => round($start_percent, 3), 'width_percent' => round($width_percent, 3)]);
                    $start_percent += $width_percent;
                }
            }
        }
        if ($this->soft_limit && $this->hard_limit && ($this->query_count > $this->soft_limit && $this->query_count > $this->hard_limit)) {
            array_unshift($statements, ['sql' => '# Query soft and hard limit for Debugbar are reached. Only the first ' . $this->soft_limit . ' queries show details. Queries after the first ' . $this->hard_limit . ' are ignored. Limits can be raised in the config (debugbar.options.db.soft/hard_limit).', 'type' => 'info']);
            $statements[] = ['sql' => '... ' . ($this->query_count - $this->hard_limit) . ' additional queries are executed but now shown because of Debugbar query limits. Limits can be raised in the config (debugbar.options.db.soft/hard_limit)', 'type' => 'info'];
            $this->info_statements += 2;
        } elseif ($this->hard_limit && $this->query_count > $this->hard_limit) {
            array_unshift($statements, ['sql' => '# Query hard limit for Debugbar is reached after ' . $this->hard_limit . ' queries, additional ' . ($this->query_count - $this->hard_limit) . ' queries are not shown.. Limits can be raised in the config (debugbar.options.db.hard_limit)', 'type' => 'info']);
            $statements[] = ['sql' => '... ' . ($this->query_count - $this->hard_limit) . ' additional queries are executed but now shown because of Debugbar query limits. Limits can be raised in the config (debugbar.options.db.hard_limit)', 'type' => 'info'];
            $this->info_statements += 2;
        } elseif ($this->soft_limit && $this->query_count > $this->soft_limit) {
            array_unshift($statements, ['sql' => '# Query soft limit for Debugbar is reached after ' . $this->soft_limit . ' queries, additional ' . ($this->query_count - $this->soft_limit) . ' queries only show the query. Limits can be raised in the config (debugbar.options.db.soft_limit)', 'type' => 'info']);
            $this->info_statements++;
        }
        $visible_statements = count($statements) - $this->info_statements;
        return ['count' => $visible_statements, 'nb_statements' => $this->query_count, 'nb_visible_statements' => $visible_statements, 'nb_excluded_statements' => $this->query_count + $this->transaction_events_count - $visible_statements, 'nb_failed_statements' => 0, 'accumulated_duration' => $total_time, 'accumulated_duration_str' => $this->get_data_formatter()->format_duration($total_time), 'memory_usage' => $total_memory, 'memory_usage_str' => $total_memory ? $this->get_data_formatter()->format_bytes($total_memory) : null, 'statements' => $statements];
    }
    /**
     * {@inheritDoc}
     */
    public function get_name(): string
    {
        return 'queries';
    }
    /**
     * {@inheritDoc}
     */
    public function get_widgets(): array
    {
        return ['queries' => ['icon' => 'database', 'widget' => 'PhpDebugBar.Widgets.LaravelQueriesWidget', 'map' => 'queries', 'default' => '[]'], 'queries:badge' => ['map' => 'queries.nb_statements', 'default' => 0]];
    }
    protected function get_sql_query_to_display(array $query): string
    {
        $sql = $query['query'];
        $grammar = $query['connection']->get_query_grammar();
        if ($query['type'] === 'query' && $grammar instanceof Grammar) {
            try {
                $sql = $grammar->substitute_bindings_into_raw_sql($sql, $query['bindings'] ?? []);
                return $this->get_query_formatter()->format_sql($sql);
            } catch (\Throwable) {
                // Continue using the old substitute
            }
        }
        if ($query['type'] === 'query' && $this->render_sql_with_params) {
            $pdo = null;
            try {
                $pdo = $query['connection']->get_pdo();
            } catch (\Throwable) {
                // ignore error for non-pdo laravel drivers
            }
            $sql = $this->get_query_formatter()->format_sql_with_bindings($sql, $query['bindings'] ?? [], $pdo);
        }
        return $this->get_query_formatter()->format_sql($sql);
    }
    public function get_assets(): array
    {
        return ['js' => ['widgets/sqlqueries/widget.js', __DIR__ . '/../../resources/queries/widget.js'], 'css' => 'widgets/sqlqueries/widget.css'];
    }
}