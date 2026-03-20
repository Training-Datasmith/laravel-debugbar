<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Console;

use Fruitcake\Laravel_Debugbar\Laravel_Debugbar;
use Illuminate\Console\Command;
class Clear_Command extends Command
{
    protected $name = 'debugbar:clear';
    protected $description = 'Clear the Debugbar Storage';
    public function handle(Laravel_Debugbar $debugbar): void
    {
        $debugbar->boot();
        if ($storage = $debugbar->get_storage()) {
            try {
                $storage->clear();
            } catch (\InvalidArgumentException $e) {
                // hide InvalidArgumentException if storage location does not exist
                if (!str_contains($e->get_message(), 'does not exist')) {
                    throw $e;
                }
            }
            $this->info('Debugbar Storage cleared!');
        } else {
            $this->error('No Debugbar Storage found..');
        }
    }
}