<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar;

use Debug_Bar\Http_Driver_Interface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\Http_Foundation\Response;
class Laravel_Http_Driver implements Http_Driver_Interface
{
    public function __construct(protected Request $request, protected ?Response $response = null)
    {
    }
    public function set_request(Request $request): void
    {
        $this->request = $request;
    }
    public function set_response(?Response $response): void
    {
        $this->response = $response;
    }
    public function set_headers(array $headers): void
    {
        if (!is_null($this->response)) {
            $this->response->headers->add($headers);
        }
    }
    public function output(string $content): void
    {
        if (!is_null($this->response)) {
            $existing_content = $this->response->get_content();
            $content = $existing_content ? $existing_content . $content : $content;
            $this->response->set_content($content);
        }
    }
    public function is_session_started(): bool
    {
        return true;
    }
    public function set_session_value(string $name, mixed $value): void
    {
        if ($value !== null) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $cookie = Cookie::make($name, $value, 0);
        if ($this->response) {
            $this->response->headers->set_cookie($cookie);
        } else {
            Cookie::queue($cookie);
        }
    }
    /**
     * {@inheritDoc}
     */
    public function has_session_value(string $name): bool
    {
        return $this->request->has_cookie($name);
    }
    /**
     * {@inheritDoc}
     */
    public function get_session_value(string $name): mixed
    {
        $value = $this->request->cookie($name);
        if ($value !== null) {
            return json_decode($value, true);
        }
        return $value;
    }
    /**
     * {@inheritDoc}
     */
    public function delete_session_value(string $name): void
    {
        $this->set_session_value($name, null);
    }
}