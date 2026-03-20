<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Support\Octane;

use Fruitcake\Laravel_Debugbar\Laravel_Debugbar;
use Laravel\Octane\Events\Request_Received;
class Reset_Debugbar
{
    /**
     * Handle the event.
     *
     */
    public function handle(Request_Received $event): void
    {
        if (!$event->sandbox->resolved(Laravel_Debugbar::class)) {
            return;
        }
        with($event->sandbox->make(Laravel_Debugbar::class), function (Laravel_Debugbar $debugbar) use ($event): void {
            $debugbar->set_application($event->sandbox);
            $debugbar->set_request($event->request);
            $debugbar->reset();
            if ($debugbar->is_enabled() && !$debugbar->request_is_excluded($event->request)) {
                $debugbar->boot();
            }
            if ($request_start_time = $event->request->server->get('REQUEST_TIME_FLOAT')) {
                $debugbar->get_time_collector()->set_request_start_time((float) $request_start_time);
            }
            $debugbar->start_measure('application', 'Application', 'time');
        });
    }
}