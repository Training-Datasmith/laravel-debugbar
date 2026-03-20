<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Collector_Providers;

use Fruitcake\Laravel_Debugbar\Data_Collector\Session_Collector;
use Illuminate\Http\Request;
class Session_Collector_Provider extends Abstract_Collector_Provider
{
    public function __invoke(Request $request, array $options): void
    {
        // Legacy hidden values, using array path
        $hiddens = array_map(function ($value): mixed {
            if (str_contains($value, '.')) {
                return substr($value, strrpos($value, '.') + 1);
            }
            return $value;
        }, (array) ($options['hiddens'] ?? []));
        $session_collector = new Session_Collector();
        $session_collector->add_masked_keys($hiddens);
        $session_collector->add_masked_keys((array) ($options['masked'] ?? []));
        $this->add_collector($session_collector);
    }
}