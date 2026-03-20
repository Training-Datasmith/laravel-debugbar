<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Collector_Providers;

use Fruitcake\Laravel_Debugbar\Data_Collector\Multi_Auth_Collector;
class Auth_Collector_Provider extends Abstract_Collector_Provider
{
    public function __invoke(array $options): void
    {
        $guards = config('auth.guards', []);
        $auth_collector = new Multi_Auth_Collector($guards);
        $this->add_collector($auth_collector);
        $auth_collector->set_show_name($options['show_name'] ?? false);
        $auth_collector->set_show_guards_data($options['show_guards'] ?? true);
    }
}