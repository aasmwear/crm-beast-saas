<?php

namespace App\Http\Requests\Tasks;

use Illuminate\Foundation\Http\FormRequest;

class MoveTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>|string>
     */
    public function rules(): array
    {
        return [
            'to_project_id' => ['required', 'integer'],
            'before_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:50'],
        ];
    }
}
