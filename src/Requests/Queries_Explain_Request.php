<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Requests;

use Illuminate\Foundation\Http\Form_Request;
class Queries_Explain_Request extends Form_Request
{
    public function rules(): array
    {
        return ['connection' => ['required', 'string'], 'query' => ['required', 'string'], 'bindings' => ['nullable', 'array'], 'hash' => ['required', 'string'], 'mode' => ['nullable', 'string', 'in:explain,visual,result'], 'format' => ['nullable', 'string']];
    }
}