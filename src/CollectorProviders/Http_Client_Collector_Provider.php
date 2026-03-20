<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Collector_Providers;

use Debug_Bar\Data_Collector\Time_Data_Collector;
use Fruitcake\Laravel_Debugbar\Data_Collector\Http_Client_Collector;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Events\Connection_Failed;
use Illuminate\Http\Client\Events\Response_Received;
class Http_Client_Collector_Provider extends Abstract_Collector_Provider
{
    protected ?Http_Client_Collector $http_client_collector = null;
    public function __invoke(Dispatcher $events, array $options): void
    {
        $http_client_collector = new Http_Client_Collector('http_client');
        if ($this->has_collector('time') && ($options['timeline'] ?? true)) {
            /** @var TimeDataCollector   $timeCollector */
            $time_collector = $this->get_collector('time');
            $http_client_collector->set_time_data_collector($time_collector);
        }
        $this->http_client_collector = $http_client_collector;
        $masked = $options['masked'] ?? [];
        $http_client_collector->add_masked_keys($masked);
        $this->add_collector($http_client_collector);
        $events->listen(Response_Received::class, fn(Response_Received $e) => $this->add_event($e));
        $events->listen(Connection_Failed::class, fn(Connection_Failed $e) => $this->add_event($e));
    }
    protected function add_event(Response_Received|Connection_Failed $event): void
    {
        try {
            $this->http_client_collector->add_event($event);
        } catch (\Throwable $e) {
            $this->add_throwable($e);
        }
    }
}