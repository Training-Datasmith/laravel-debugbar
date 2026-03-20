<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Collector_Providers;

use Fruitcake\Laravel_Debugbar\Data_Collector\Query_Collector;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\Connection_Established;
use Illuminate\Database\Events\Query_Executed;
use Illuminate\Database\Events\Transaction_Beginning;
use Illuminate\Database\Events\Transaction_Committed;
use Illuminate\Database\Events\Transaction_Rolled_Back;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
class Database_Collector_Provider extends Abstract_Collector_Provider
{
    public function __invoke(Dispatcher $events, Router $router, Request $request, array $options): void
    {
        $query_collector = new Query_Collector();
        if ($options['timeline'] ?? false) {
            $time_collector = $this->debugbar->get_time_collector();
            $query_collector->set_time_data_collector($time_collector);
        }
        $query_collector->set_limits($options['soft_limit'] ?? 100, $options['hard_limit'] ?? 500);
        $query_collector->set_duration_background($options['duration_background'] ?? true);
        $threshold = $options['slow_threshold'] ?? false;
        if ($threshold && !($options['only_slow_queries'] ?? true)) {
            $query_collector->set_slow_threshold($threshold);
        }
        if ($options['with_params'] ?? true) {
            $query_collector->set_render_sql_with_params(true);
        }
        if ($backtrace = $options['backtrace'] ?? true) {
            $query_collector->set_find_source($backtrace, $router->get_middleware());
        }
        if ($exclude_paths = $options['exclude_paths'] ?? []) {
            $query_collector->merge_exclude_paths($exclude_paths);
        }
        if ($exclude_backtrace_paths = $options['backtrace_exclude_paths'] ?? []) {
            $query_collector->merge_backtrace_exclude_paths($exclude_backtrace_paths);
        }
        if (($options['explain']['enabled'] ?? false) && $this->debugbar->is_storage_open($request)) {
            $query_collector->set_explain_query(true);
        }
        if (($options['show_query_result'] ?? false) && $this->debugbar->is_storage_open($request)) {
            $query_collector->set_show_query_result(true);
        }
        $this->add_collector($query_collector);
        try {
            $events->listen(function (Query_Executed $query) use ($query_collector, $options): void {
                // In case Debugbar is disabled after the listener was attached
                if (!$this->debugbar->should_collect('db', true) || !$this->debugbar->is_enabled()) {
                    return;
                }
                $threshold = $options['slow_threshold'] ?? false;
                $only_threshold = $options['only_slow_queries'] ?? true;
                //allow collecting only queries slower than a specified amount of milliseconds
                if (!$only_threshold || !$threshold || $query->time > $threshold) {
                    $query_collector->add_query($query);
                }
            });
        } catch (\Throwable $e) {
            $this->add_collector_exception('Cannot listen to Queries', $e);
        }
        try {
            $events->listen(Transaction_Beginning::class, fn($transaction) => $query_collector->collect_transaction_event('Begin Transaction', $transaction->connection));
            $events->listen(Transaction_Committed::class, fn($transaction) => $query_collector->collect_transaction_event('Commit Transaction', $transaction->connection));
            $events->listen(Transaction_Rolled_Back::class, fn($transaction) => $query_collector->collect_transaction_event('Rollback Transaction', $transaction->connection));
            $events->listen('connection.*.beganTransaction', fn($event, $params) => $query_collector->collect_transaction_event('Begin Transaction', $params[0]));
            $events->listen('connection.*.committed', fn($event, $params) => $query_collector->collect_transaction_event('Commit Transaction', $params[0]));
            $events->listen('connection.*.rollingBack', fn($event, $params) => $query_collector->collect_transaction_event('Rollback Transaction', $params[0]));
            $events->listen(function (Connection_Established $event) use ($query_collector, $options): void {
                $query_collector->collect_transaction_event('Connection Established', $event->connection);
                if ($options['memory_usage'] ?? false) {
                    $event->connection->before_executing(function () use ($query_collector): void {
                        $query_collector->start_memory_usage();
                    });
                }
            });
        } catch (\Throwable $e) {
            $this->add_collector_exception('Cannot listen to Queries', $e);
        }
    }
}