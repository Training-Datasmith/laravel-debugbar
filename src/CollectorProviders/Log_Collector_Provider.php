<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Collector_Providers;

use Debug_Bar\Data_Collector\Messages_Collector;
use Illuminate\Log\Events\Message_Logged;
use Illuminate\Log\Logger;
class Log_Collector_Provider extends Abstract_Collector_Provider
{
    public function __invoke(Logger $logger, array $options): void
    {
        $log_collector = new Messages_Collector('log');
        if ($this->has_collector('messages')) {
            /** @var MessagesCollector $messagesCollector */
            $messages_collector = $this->get_collector('messages');
            $messages_collector->aggregate($log_collector);
        } else {
            $this->add_collector($log_collector);
        }
        $logger->listen(function (Message_Logged $log) use ($log_collector): void {
            try {
                $log_message = $log->message;
                if (mb_check_encoding($log_message, 'UTF-8')) {
                    $context = $log->context;
                    $log_message .= $context ? ' ' . json_encode($context, JSON_PRETTY_PRINT) : '';
                } else {
                    $log_message = '[INVALID UTF-8 DATA]';
                }
            } catch (\Throwable $e) {
                $log_message = '[Exception: ' . $e->get_message() . ']';
            }
            $log_collector->log($log->level, '[' . date('H:i:s') . '] ' . "LOG.{$log->level}: " . $log_message, $log->context);
        });
    }
}