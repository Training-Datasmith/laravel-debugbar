<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Collector_Providers;

use Debug_Bar\Data_Collector\Data_Collector_Interface;
use Fruitcake\Laravel_Debugbar\Laravel_Debugbar;
abstract class Abstract_Collector_Provider
{
    public function __construct(protected readonly Laravel_Debugbar $debugbar)
    {
    }
    protected function add_collector(Data_Collector_Interface $collector): void
    {
        $this->debugbar->add_collector($collector);
    }
    public function has_collector(string $name): bool
    {
        return $this->debugbar->has_collector($name);
    }
    public function get_collector(string $name): Data_Collector_Interface
    {
        return $this->debugbar->get_collector($name);
    }
    protected function add_collector_exception(string $message, \Throwable $exception): void
    {
        $this->add_throwable(new \RuntimeException($message . ' on Laravel Debugbar: ' . $exception->get_message(), (int) $exception->get_code(), $exception));
    }
    /**
     * Adds an exception to be profiled in the debug bar
     */
    public function add_throwable(\Throwable $e): void
    {
        if ($this->has_collector('exceptions')) {
            /** @var \DebugBar\DataCollector\ExceptionsCollector $collector */
            $collector = $this->get_collector('exceptions');
            $collector->add_throwable($e);
        }
    }
}