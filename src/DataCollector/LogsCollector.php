<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Data_Collector;

use Debug_Bar\Data_Collector\Messages_Collector;
use Psr\Log\Log_Level;
use ReflectionClass;
class Logs_Collector extends Messages_Collector
{
    protected $lines = 124;
    protected array $paths = [];
    public function __construct(string|array|null $path = null, string $name = 'logs')
    {
        parent::__construct($name);
        if (is_array($path) && count($path) > 0) {
            $this->paths = $path;
        } elseif (is_string($path)) {
            $this->paths = [$path];
        } else {
            $this->paths = [storage_path('logs/laravel.log'), storage_path('logs/laravel-' . date('Y-m-d') . '.log')];
        }
    }
    public function collect(): array
    {
        foreach ($this->paths as $log_path) {
            $this->get_storage_logs($log_path);
        }
        return parent::collect();
    }
    /**
     * get logs apache in app/storage/logs
     * only 24 last of current day
     */
    public function get_storage_logs(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        //Load the latest lines, guessing about 15x the number of log entries (for stack traces etc)
        $file = implode('', $this->tail_file($path, $this->lines));
        $basename = basename($path);
        foreach ($this->get_logs($file) as $log) {
            $this->messages[] = ['message' => trim($log['header'] . $log['stack']), 'label' => $log['level'], 'time' => substr((string) $log['header'], 1, 19), 'collector' => $basename, 'is_string' => false];
        }
    }
    /**
     * By Ain Tohvri (ain)
     * http://tekkie.flashbit.net/php/tail-functionality-in-php
     */
    protected function tail_file(string $file, int $lines): array
    {
        $handle = fopen($file, 'r');
        $linecounter = $lines;
        $pos = -2;
        $beginning = false;
        $text = [];
        try {
            while ($linecounter > 0) {
                $t = ' ';
                while ($t !== "\n") {
                    if (fseek($handle, $pos, SEEK_END) === -1) {
                        $beginning = true;
                        break;
                    }
                    $t = fgetc($handle);
                    $pos--;
                }
                $linecounter--;
                if ($beginning) {
                    rewind($handle);
                }
                $text[$lines - $linecounter - 1] = fgets($handle);
                if ($beginning) {
                    break;
                }
            }
        } finally {
            fclose($handle);
        }
        return array_reverse($text);
    }
    /**
     * Search a string for log entries
     * Based on https://github.com/mikemand/logviewer/blob/master/src/Kmd/Logviewer/Logviewer.php by mikemand
     */
    public function get_logs(string $file): array
    {
        $pattern = "/\\[\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}\\](?:(?!\\[\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}\\])[\\s\\S])*/";
        $log_levels = $this->get_levels();
        // There has GOT to be a better way of doing this...
        preg_match_all($pattern, $file, $headings);
        $log_data = preg_split($pattern, $file) ?: [];
        $log = [];
        foreach ($headings as $h) {
            for ($i = 0, $j = count($h); $i < $j; $i++) {
                foreach ($log_levels as $ll) {
                    if (str_contains(strtolower($h[$i]), strtolower('.' . $ll))) {
                        $log[] = ['level' => $ll, 'header' => $h[$i], 'stack' => $log_data[$i] ?? ''];
                    }
                }
            }
        }
        return $log;
    }
    public function get_messages(): array
    {
        return array_reverse(parent::get_messages());
    }
    /**
     * Get the log levels from psr/log.
     * Based on https://github.com/mikemand/logviewer/blob/master/src/Kmd/Logviewer/Logviewer.php by mikemand
     */
    public function get_levels(): array
    {
        $class = new ReflectionClass(new Log_Level());
        return $class->get_constants();
    }
}