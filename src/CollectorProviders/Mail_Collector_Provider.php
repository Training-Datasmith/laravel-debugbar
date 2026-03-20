<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Collector_Providers;

use Debug_Bar\Bridge\Symfony\Symfony_Mail_Collector;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Mail\Events\Message_Sending;
use Illuminate\Mail\Events\Message_Sent;
class Mail_Collector_Provider extends Abstract_Collector_Provider
{
    public function __invoke(Dispatcher $events, array $options): void
    {
        $mail_collector = new Symfony_Mail_Collector();
        $this->add_collector($mail_collector);
        $events->listen(function (Message_Sent $event) use ($mail_collector): void {
            $mail_collector->add_symfony_message($event->sent->get_symfony_sent_message());
        });
        if (($options['show_body'] ?? true) || ($options['full_log'] ?? false)) {
            $mail_collector->show_message_body();
        }
        if ($options['timeline'] ?? true) {
            $time_collector = $this->debugbar->get_time_collector();
            $events->listen(Message_Sending::class, fn(Message_Sending $e) => $time_collector->start_measure('Mail: ' . $e->message->get_subject()));
            $events->listen(Message_Sent::class, function (Message_Sent $e) use ($time_collector): void {
                $name = 'Mail: ' . $e->message->get_subject();
                if ($time_collector->has_started_measure($name)) {
                    $time_collector->stop_measure($name);
                } else {
                    $time_collector->add_measure($name);
                }
            });
        }
    }
}