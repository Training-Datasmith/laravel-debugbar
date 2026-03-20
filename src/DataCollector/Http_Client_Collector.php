<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Data_Collector;

use Debug_Bar\Data_Collector\Http_Collector;
use Illuminate\Http\Client\Events\Connection_Failed;
use Illuminate\Http\Client\Events\Response_Received;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
class Http_Client_Collector extends Http_Collector
{
    public function add_event(Response_Received|Connection_Failed $event): void
    {
        $headers = $this->hide_masked_values($event->request->headers());
        if ($event->request->is_multipart()) {
            $request_data = '[MULTIPART]';
        } else {
            $request_data = $this->hide_masked_values($event->request->data());
        }
        $status = null;
        $duration = null;
        $details = ['request_data' => $request_data, 'request_headers' => $headers];
        if ($event instanceof Response_Received) {
            $status = $event->response->status();
            $duration = $event->response->transfer_stats?->get_transfer_time();
            $details['response'] = $this->parse_response($event->response);
            $details['response_headers'] = $this->hide_masked_values($event->response->headers());
        }
        // @phpstan-ignore-next-line because exception might not be set in Laravel 10
        if ($event instanceof Connection_Failed && isset($event->exception)) {
            $details['exception'] = $event->exception;
        }
        $this->add_request($event->request->method(), $event->request->url(), $status, $duration, $details);
    }
    protected function parse_response(Response $response): string|array
    {
        if ($response->redirect()) {
            return 'Redirect: ' . $response->header('Location');
        }
        // Check if stream
        $stream = $response->to_psr_response()->get_body();
        if (!$stream->is_seekable()) {
            return '[STREAM]';
        }
        $content = $response->body();
        $stream->rewind();
        if ($content === '') {
            return '[EMPTY]';
        }
        $json = json_decode($content, true);
        if ($json) {
            return $json;
        }
        return Str::limit($content, 1024);
    }
}