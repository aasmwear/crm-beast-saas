<?php

namespace App\Http\Requests;

class UpdateClientRequest extends StoreClientRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['company_name'][0] = 'sometimes';
        return $rules;
    }
}
