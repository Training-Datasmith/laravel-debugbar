<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Controllers;

use Debug_Bar\Bridge\Symfony\Symfony_Http_Driver;
use Debug_Bar\Open_Handler;
use Fruitcake\Laravel_Debugbar\Laravel_Debugbar;
use Fruitcake\Laravel_Debugbar\Laravel_Http_Driver;
use Fruitcake\Laravel_Debugbar\Requests\Open_Handler_Request;
use Fruitcake\Laravel_Debugbar\Support\Clockwork\Converter;
use Illuminate\Http\Json_Response;
use Illuminate\Http\Response;
class Open_Handler_Controller
{
    public function handle(Open_Handler_Request $request, Laravel_Debugbar $debugbar, Open_Handler $open_handler): Response|Json_Response
    {
        if ($request->validated('op') !== 'get' && !$debugbar->is_storage_open($request)) {
            return new Json_Response([['datetime' => date('Y-m-d H:i:s'), 'id' => null, 'ip' => $request->get_client_ip(), 'method' => 'ERROR', 'uri' => '!! To enable public access to previous requests, set debugbar.storage.open to true in your config, or enable DEBUGBAR_OPEN_STORAGE if you did not publish the config. !!', 'utime' => microtime(true)]]);
        }
        $response = new Response();
        $driver = $debugbar->get_http_driver();
        if ($driver instanceof Laravel_Http_Driver || $driver instanceof Symfony_Http_Driver) {
            $driver->set_response($response);
        }
        $open_handler->handle($request->input());
        return $response;
    }
    /**
     * Return Clockwork output
     *
     * @throws \DebugBar\DebugBarException
     */
    public function clockwork(Open_Handler $open_handler, $id): \Illuminate\Http\Json_Response
    {
        $request = ['op' => 'get', 'id' => $id];
        $data = $open_handler->handle($request, false, false);
        // Convert to Clockwork
        $converter = new Converter();
        $output = $converter->convert(json_decode($data, true));
        return response()->json($output);
    }
}