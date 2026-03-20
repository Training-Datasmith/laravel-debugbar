<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Collector_Providers;

class Messages_Collector_Provider extends Abstract_Collector_Provider
{
    public function __invoke(array $options): void
    {
        $message_collector = $this->debugbar->get_messages_collector();
        $this->add_collector($message_collector);
        if ($options['trace'] ?? true) {
            $message_collector->collect_file_trace(true);
            $exclude_paths = $options['backtrace_exclude_paths'] ?? [];
            if ($exclude_paths) {
                $message_collector->add_backtrace_exclude_paths($exclude_paths);
            }
        }
        if ($options['timeline'] ?? true) {
            $message_collector->set_time_data_collector($this->debugbar->get_time_collector());
        }
        if ($options['capture_dumps'] ?? false) {
            $original_handler = \Symfony\Component\Var_Dumper\Var_Dumper::set_handler(function ($var) use (&$original_handler, $message_collector): void {
                if ($original_handler) {
                    $original_handler($var);
                }
                $message_collector->add_message($var);
            });
        }
    }
}