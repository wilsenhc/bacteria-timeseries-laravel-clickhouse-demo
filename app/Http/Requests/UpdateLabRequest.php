<?php

namespace App\Http\Requests;

use App\Models\Lab;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLabRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Lab $lab */
        $lab = $this->route('lab');

        return $this->user()->can('update', $lab);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Lab $lab */
        $lab = $this->route('lab');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('labs', 'name')->where('organization_id', $lab->organization_id)->ignore($lab)],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('labs', 'code')->where('organization_id', $lab->organization_id)->ignore($lab)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array{name: string, code: string|null, notes: string|null}
     */
    public function labData(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'code' => $this->filled('code') ? $this->string('code')->toString() : null,
            'notes' => $this->filled('notes') ? $this->string('notes')->toString() : null,
        ];
    }
}
