<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Controllers;

use Laravel\Telescope\Contracts\Entries_Repository;
use Laravel\Telescope\Storage\Entry_Query_Options;
class Telescope_Controller
{
    public function show(Entries_Repository $storage, $uuid)
    {
        $entry = $storage->find($uuid);
        $result = $storage->get('request', (new Entry_Query_Options())->batch_id($entry->batch_id))->first();
        return redirect(config('telescope.domain') . '/' . config('telescope.path') . '/requests/' . $result->id);
    }
}