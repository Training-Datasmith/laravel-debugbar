<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Facades;

use Debug_Bar\Data_Collector\Data_Collector_Interface;
use Fruitcake\Laravel_Debugbar\Laravel_Debugbar;
/**
 * @method static LaravelDebugbar addCollector(DataCollectorInterface $collector)
 * @method static void            addMessage(mixed $message, string $label = 'info')
 * @method static void            alert(mixed $message)
 * @method static void            critical(mixed $message)
 * @method static void            debug(mixed $message)
 * @method static void            emergency(mixed $message)
 * @method static void            error(mixed $message)
 * @method static LaravelDebugbar getCollector(string $name)
 * @method static bool            hasCollector(string $name)
 * @method static void            info(mixed $message)
 * @method static void            log(mixed $message)
 * @method static void            notice(mixed $message)
 * @method static void            warning(mixed $message)
 *
 * @see \Fruitcake\LaravelDebugbar\LaravelDebugbar
 */
class Debugbar extends \Illuminate\Support\Facades\Facade
{
    /**
     * {@inheritDoc}
     */
    protected static function get_facade_accessor(): string
    {
        return \Fruitcake\Laravel_Debugbar\Laravel_Debugbar::class;
    }
}