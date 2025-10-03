<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SafeFilterRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'q'         => ['sometimes','string','max:100'],
            'date_from' => ['sometimes','date'],
            'date_to'   => ['sometimes','date','after_or_equal:date_from'],
            'sort'      => ['sometimes','in:date,created_at,counselor'], // allow-list
            'dir'       => ['sometimes','in:asc,desc'],
            'per_page'  => ['sometimes','integer','min:5','max:50'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'q'        => $this->has('q') ? trim((string)$this->input('q')) : null,
            'dir'      => strtolower((string)$this->input('dir', 'asc')),
            'sort'     => (string)$this->input('sort', 'date'),
            'per_page' => (int) $this->input('per_page', 10),
        ]);
    }
}
