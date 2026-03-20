<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Data_Collector;

use Debug_Bar\Data_Collector\Template_Collector;
use Symfony\Component\Http_Foundation\Response;
/**
 * Collector for Ineratia.
 */
class Inertia_Collector extends Template_Collector
{
    public function add_from_view(\Illuminate\View\View $view): void
    {
        $data = $view->get_data();
        if (isset($data['page']['component'])) {
            $this->add_inertia_template($data['page'], $view->get_name(), $view->get_path());
        }
    }
    public function add_from_response(Response $response): void
    {
        if (!$response->headers->has('X-Inertia') || $response->headers->get('Content-Type') !== 'application/json') {
            return;
        }
        $content = $response->get_content();
        if (is_string($content)) {
            $content = json_decode($content, true);
        }
        if (is_array($content)) {
            $this->add_inertia_template($content);
        }
    }
    private function add_inertia_template(array $page, ?string $name = null, ?string $path = null): void
    {
        if (!isset($page['component'])) {
            return;
        }
        $type = '';
        $component = $page['component'];
        $props = $page['props'] ?? [];
        $page_path = config('debugbar.options.inertia.pages', 'js/Pages');
        if ($files = glob(resource_path($page_path . '/' . $name . '.*'))) {
            $path = $files[0];
            $type = pathinfo($path, PATHINFO_EXTENSION);
            if (in_array($type, ['js', 'jsx'], true)) {
                $type = 'react';
            }
        }
        $this->add_template($component, $props, $type, $path);
    }
    public function collect(): array
    {
        $data = parent::collect();
        $data['sentence'] = 'Inertia page' . ($data['nb_templates'] !== 1 ? 's' : '');
        return $data;
    }
    /**
     * {@inheritDoc}
     */
    public function get_name(): string
    {
        return 'inertia';
    }
    public function get_widgets(): array
    {
        $widgets = parent::get_widgets();
        $widgets[$this->get_name()]['icon'] = 'brand-inertia';
        return $widgets;
    }
}