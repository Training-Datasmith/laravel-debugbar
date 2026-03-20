<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Support\Clockwork;

use Debug_Bar\Data_Collector\Data_Collector;
use Debug_Bar\Data_Collector\Data_Collector_Interface;
use Debug_Bar\Data_Collector\Renderable;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
/**
 *
 * Based on \Symfony\Component\HttpKernel\DataCollector\RequestDataCollector by Fabien Potencier <fabien@symfony.com>
 *
 */
class Clockwork_Collector extends Data_Collector implements Data_Collector_Interface, Renderable
{
    protected Request $request;
    protected Response $response;
    public function __construct(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;
    }
    /**
     * {@inheritDoc}
     */
    public function get_name(): string
    {
        return 'clockwork';
    }
    /**
     * {@inheritDoc}
     */
    public function get_widgets(): array
    {
        return [];
    }
    /**
     * {@inheritdoc}
     */
    public function collect(): array
    {
        $request = $this->request;
        $response = $this->response;
        $data = ['getData' => $request->query->all(), 'postData' => $request->request->all(), 'headers' => $request->headers->all(), 'cookies' => $request->cookies->all(), 'uri' => $request->get_request_uri(), 'method' => $request->get_method(), 'responseStatus' => $response->get_status_code()];
        if ($this->request->has_session()) {
            $data['sessionData'] = $this->request->get_session()->all();
        }
        if (isset($data['headers']['authorization'][0])) {
            $data['headers']['authorization'][0] = substr($data['headers']['authorization'][0], 0, 12) . '******';
        }
        return $this->hide_masked_values($data);
    }
}