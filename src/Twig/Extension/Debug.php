<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Twig\Extension;

use Debug_Bar\Bridge\Twig\Debug_Twig_Extension;
use Twig\Environment;
/**
 * Access debugbar debug in your Twig templates.
 */
class Debug extends Debug_Twig_Extension
{
    public function debug(Environment $env, $context): void
    {
        if (!$this->messages_collector) {
            $app = app();
            if ($app->bound('debugbar') && $app['debugbar']->has_collector('messages')) {
                $this->messages_collector = $app['debugbar']['messages'];
            }
        }
        parent::debug($env, $context);
    }
}