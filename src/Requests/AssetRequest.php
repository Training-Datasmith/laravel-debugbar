<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Requests;

use Illuminate\Foundation\Http\Form_Request;
class Asset_Request extends Form_Request
{
    public function rules(): array
    {
        return ['type' => ['required', 'string', 'in:js,css']];
    }
}