<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Support;

use Debug_Bar\Data_Collector\Data_Collector;
use Exception;
use Illuminate\Database\Connection_Interface;
use Illuminate\Database\Query_Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
class Explain
{
    public function is_read_only_query(string $query): bool
    {
        return (bool) preg_match('/^(SELECT|WITH)\b/i', ltrim($query));
    }
    public function is_raw_explain_supported(string $driver, ?array $bindings): bool
    {
        return in_array($driver, ['mariadb', 'mysql', 'pgsql'], true) && $bindings !== null;
    }
    public function is_visual_explain_supported(string $connection): bool
    {
        $driver = DB::connection($connection)->get_driver_name();
        if ($driver === 'pgsql') {
            return true;
        }
        if ($driver === 'mysql') {
            // Laravel 11 added a new MariaDB database driver but older Laravel versions handle MySQL and MariaDB with
            // the same driver - and even with new versions you can use the MySQL driver while connection to a MariaDB
            // database. This query uses a feature implemented only in MariaDB to differentiate them.
            try {
                DB::connection($connection)->select('SELECT * FROM seq_1_to_1');
                return false;
            } catch (Query_Exception) {
                // This exception is expected when using MySQL as sequence tables are only available with MariaDB. So
                // the exception gets silenced as the check for MySQL has succeeded.
                return true;
            }
        }
        return false;
    }
    public function confirm_visual_explain(string $connection): ?string
    {
        return match (DB::connection($connection)->get_driver_name()) {
            'mysql' => 'The query and EXPLAIN output is sent to mysqlexplain.com. Do you want to continue?',
            'pgsql' => 'The query and EXPLAIN output is sent to explain.dalibo.com. Do you want to continue?',
            default => null,
        };
    }
    public function hash(string $connection, string $sql, ?array $bindings): string
    {
        $bindings = json_encode($bindings);
        return hash_hmac('sha256', "{$connection}::{$sql}::{$bindings}", config('app.key'));
    }
    private function verify(string $connection, string $sql, array $bindings, string $hash): void
    {
        $computed_hash = $this->hash($connection, $sql, $bindings);
        if (!hash_equals($computed_hash, $hash)) {
            throw new Exception('Query to execute could not be verified.');
        }
    }
    public function generate_select_result(string $connection, string $sql, array $bindings, string $hash, ?string $format): array
    {
        $this->verify($connection, $sql, $bindings, $hash);
        $this->validate_read_only_query($sql);
        $result = DB::connection($connection)->select($sql, $bindings);
        if ($format === 'dump') {
            $result = Data_Collector::get_default_data_formatter()->format_var($result);
        }
        return ['result' => $result];
    }
    public function generate_raw_explain(string $connection, string $sql, array $bindings, string $hash): array
    {
        $this->verify($connection, $sql, $bindings, $hash);
        $this->validate_read_only_query($sql);
        $connection = DB::connection($connection);
        return match ($driver = $connection->get_driver_name()) {
            'mariadb', 'mysql' => $connection->select("EXPLAIN {$sql}", $bindings),
            'pgsql' => array_column($connection->select("EXPLAIN {$sql}", $bindings), 'QUERY PLAN'),
            default => throw new Exception("Visual explain not available for driver '{$driver}'."),
        };
    }
    public function generate_visual_explain(string $connection, string $sql, array $bindings, string $hash): string
    {
        $this->verify($connection, $sql, $bindings, $hash);
        $this->validate_read_only_query($sql);
        if (!$this->is_visual_explain_supported($connection)) {
            throw new Exception('Visual explain not available for this connection.');
        }
        $connection = DB::connection($connection);
        return match ($connection->get_driver_name()) {
            'mysql' => $this->generate_visual_explain_mysql($connection, $sql, $bindings),
            'pgsql' => $this->generate_visual_explain_pgsql($connection, $sql, $bindings),
            default => throw new Exception("Visual explain not available for driver '{$connection->get_driver_name()}'."),
        };
    }
    private function validate_read_only_query(string $sql): void
    {
        $normalized = ltrim($sql);
        if (!$this->is_read_only_query($normalized)) {
            throw new Exception('Only SELECT queries can be explained or executed.');
        }
    }
    private static function redact_bindings(array $bindings): array
    {
        return array_map(fn(): string => '?', $bindings);
    }
    private function generate_visual_explain_mysql(Connection_Interface $connection, string $query, array $bindings): string
    {
        return Http::with_headers(['User-Agent' => 'fruitcake/laravel-debugbar'])->post('https://api.mysqlexplain.com/v2/explains', ['query' => $query, 'bindings' => self::redact_bindings($bindings), 'version' => $connection->select_one('SELECT VERSION()')->{'VERSION()'}, 'explain_json' => $connection->select_one("EXPLAIN FORMAT=JSON {$query}", $bindings)->EXPLAIN, 'explain_tree' => rescue(fn() => $connection->select_one("EXPLAIN FORMAT=TREE {$query}", $bindings)->EXPLAIN, report: false)])->throw()->json('url');
    }
    private function generate_visual_explain_pgsql(Connection_Interface $connection, string $query, array $bindings): string
    {
        return (string) Http::as_form()->post('https://explain.dalibo.com/new', ['query' => $query, 'plan' => $connection->select_one("EXPLAIN (FORMAT JSON) {$query}", $bindings)->{'QUERY PLAN'}, 'title' => ''])->effective_uri();
    }
}