<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Controllers;

use Debug_Bar\Asset_Handler;
use Debug_Bar\Bridge\Symfony\Symfony_Http_Driver;
use Fruitcake\Laravel_Debugbar\Laravel_Debugbar;
use Fruitcake\Laravel_Debugbar\Laravel_Http_Driver;
use Fruitcake\Laravel_Debugbar\Requests\Asset_Request;
use Illuminate\Http\Response;
class Asset_Controller
{
    public function get_assets(Asset_Request $request, Asset_Handler $asset_handler, Laravel_Debugbar $debugbar): Response
    {
        $type = $request->validated('type');
        $response = new Response();
        $driver = $debugbar->get_http_driver();
        if ($driver instanceof Laravel_Http_Driver || $driver instanceof Symfony_Http_Driver) {
            $driver->set_response($response);
        }
        $asset_handler->handle(['type' => $type]);
        return $response;
    }
}