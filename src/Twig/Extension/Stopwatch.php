<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Twig\Extension;

use Debug_Bar\Bridge\Twig\Measure_Twig_Extension;
use Debug_Bar\Bridge\Twig\Measure_Twig_Token_Parser;
use Illuminate\Foundation\Application;
/**
 * Access debugbar time measures in your Twig templates.
 * Based on Symfony\Bridge\Twig\Extension\StopwatchExtension
 */
class Stopwatch extends Measure_Twig_Extension
{
    /**
     * @var \Fruitcake\LaravelDebugbar\LaravelDebugbar
     */
    protected $debugbar;
    /**
     * Create a new time measure extension.
     *
     */
    public function __construct(Application $app)
    {
        if ($app->bound('debugbar')) {
            $this->debugbar = $app['debugbar'];
        }
        parent::__construct(null, 'stopwatch');
    }
    public function get_debugbar()
    {
        return $this->debugbar;
    }
    public function get_token_parsers()
    {
        return [
            /*
             * {% measure foo %}
             * Some stuff which will be recorded on the timeline
             * {% endmeasure %}
             */
            new Measure_Twig_Token_Parser(!is_null($this->debugbar), $this->tag_name, $this->get_name()),
        ];
    }
    public function start_measure(...$arg): void
    {
        if (!$this->debugbar || !$this->debugbar->has_collector('time')) {
            return;
        }
        $this->debugbar->get_collector('time')->start_measure(...$arg);
    }
    public function stop_measure(...$arg): void
    {
        if (!$this->debugbar || !$this->debugbar->has_collector('time')) {
            return;
        }
        $this->debugbar->get_collector('time')->stop_measure(...$arg);
    }
}