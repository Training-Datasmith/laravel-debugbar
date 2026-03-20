<?php

declare (strict_types=1);
namespace Fruitcake\Laravel_Debugbar\Requests;

use Illuminate\Foundation\Http\Form_Request;
class Cache_Delete_Request extends Form_Request
{
    public function authorize(): bool
    {
        return $this->has_valid_signature() && debugbar()->is_storage_open($this);
    }
    public function rules(): array
    {
        return ['tags' => ['sometimes', 'array'], 'tags.*' => ['string']];
    }
}