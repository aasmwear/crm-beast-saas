<?php

namespace App\Http\Requests\Tasks;

use Illuminate\Foundation\Http\FormRequest;

class MoveTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Policy gate is enforced in the controller by organization scoping
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'task_id' => ['required', 'integer', 'min:1'],
            'project_id' => ['required', 'integer', 'min:1'],
            'to_index' => ['required', 'integer', 'min:0'],
            'status' => ['nullable', 'string', 'max:50'],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
