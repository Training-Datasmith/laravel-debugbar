<?php

declare (strict_types=1);
if (!function_exists('debugbar')) {
    /**
     * Get the Debugbar instance
     *
     */
    function debugbar(?string $collector = null): \Fruitcake\Laravel_Debugbar\Laravel_Debugbar|\Debug_Bar\Data_Collector\Data_Collector_Interface|null
    {
        $debugbar = app(\Fruitcake\Laravel_Debugbar\Laravel_Debugbar::class);
        if ($collector) {
            return $debugbar->has_collector($collector) ? $debugbar->get_collector($collector) : null;
        }
        return $debugbar;
    }
}
if (!function_exists('debug')) {
    /**
     * Adds one or more messages to the MessagesCollector
     *
     */
    function debug(mixed ...$value): void
    {
        $debugbar = debugbar();
        foreach ($value as $message) {
            $debugbar->add_message($message, 'debug');
        }
    }
}