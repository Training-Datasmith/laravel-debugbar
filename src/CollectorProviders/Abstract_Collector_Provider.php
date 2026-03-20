<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Collector_Providers;

use Debug_Bar\Data_Collector\Data_Collector_Interface;
use Fruitcake\Laravel_Debugbar\Laravel_Debugbar;
/**
 * Base class for all Debugbar collector providers.
 *
 * Collector providers are responsible for instantiating one or more DataCollector
 * instances and registering them with the DebugBar. Each provider encapsulates the
 * boot logic for its feature area (e.g. database, cache, events).
 */
abstract class Abstract_Collector_Provider
{
    /**
     * @param Laravel_Debugbar $debugbar The Debugbar instance to register collectors on.
     */
    public function __construct(protected readonly Laravel_Debugbar $debugbar)
    {
    }

    /**
     * Register a DataCollector with the Debugbar.
     *
     * @param Data_Collector_Interface $collector The collector instance to add.
     */
    protected function add_collector(Data_Collector_Interface $collector): void
    {
        $this->debugbar->add_collector($collector);
    }

    /**
     * Check whether a named collector has already been registered.
     *
     * @param  string $name The collector name (e.g. 'messages', 'queries').
     * @return bool         True if the collector is registered.
     */
    public function has_collector(string $name): bool
    {
        return $this->debugbar->has_collector($name);
    }

    /**
     * Retrieve a registered collector by name.
     *
     * @param  string                    $name The collector name.
     * @return Data_Collector_Interface        The registered collector instance.
     * @throws \DebugBar\DebugBarException    If no collector with that name is registered.
     */
    public function get_collector(string $name): Data_Collector_Interface
    {
        return $this->debugbar->get_collector($name);
    }

    /**
     * Wrap a provider-level exception in a RuntimeException and record it in the Debugbar.
     *
     * @param string     $message   Human-readable context for the failure.
     * @param \Throwable $exception The underlying exception that was caught.
     */
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