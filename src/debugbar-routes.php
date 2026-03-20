<?php

declare (strict_types=1);
use Fruitcake\Laravel_Debugbar\Controllers\Asset_Controller;
use Fruitcake\Laravel_Debugbar\Controllers\Cache_Controller;
use Fruitcake\Laravel_Debugbar\Controllers\Open_Handler_Controller;
use Fruitcake\Laravel_Debugbar\Controllers\Queries_Controller;
use Fruitcake\Laravel_Debugbar\Controllers\Telescope_Controller;
use Fruitcake\Laravel_Debugbar\Middleware\Debugbar_Enabled;
use Fruitcake\Laravel_Debugbar\Middleware\Stop_Recording_Telescope;
$route_config = ['prefix' => app('config')->get('debugbar.route_prefix'), 'domain' => app('config')->get('debugbar.route_domain'), 'middleware' => array_merge(app('config')->get('debugbar.route_middleware', []), [Debugbar_Enabled::class, Stop_Recording_Telescope::class])];
app('router')->group($route_config, function ($router): void {
    $router->get('open', [Open_Handler_Controller::class, 'handle'])->name('debugbar.openhandler');
    $router->delete('cache/{key}', [Cache_Controller::class, 'delete'])->where('key', '.*')->name('debugbar.cache.delete');
    $router->post('queries/explain', [Queries_Controller::class, 'explain'])->name('debugbar.queries.explain');
    $router->get('clockwork/{id}', [Open_Handler_Controller::class, 'clockwork'])->name('debugbar.clockwork');
    $router->get('assets', [Asset_Controller::class, 'getAssets'])->name('debugbar.assets');
    if (class_exists(\Laravel\Telescope\Telescope::class)) {
        $router->get('telescope/{id}', [Telescope_Controller::class, 'show'])->name('debugbar.telescope');
    }
});