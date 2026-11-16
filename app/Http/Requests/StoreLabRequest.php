<?php

namespace App\Http\Requests;

use App\Models\Lab;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLabRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $organization = $this->organization();

        return $organization !== null
            && $this->user()->can('create', [Lab::class, $organization]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organization = $this->organization();
        $organizationId = $organization instanceof Organization ? $organization->id : 0;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('labs', 'name')->where('organization_id', $organizationId)],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('labs', 'code')->where('organization_id', $organizationId)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'row_count' => ['required', 'integer', 'min:1', 'max:20'],
            'shelves_per_row' => ['required', 'integer', 'min:1', 'max:20'],
            'levels_per_shelf' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }

    /**
     * @return array{name: string, code: string|null, notes: string|null, row_count: int, shelves_per_row: int, levels_per_shelf: int}
     */
    public function labData(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'code' => $this->filled('code') ? $this->string('code')->toString() : null,
            'notes' => $this->filled('notes') ? $this->string('notes')->toString() : null,
            'row_count' => $this->integer('row_count'),
            'shelves_per_row' => $this->integer('shelves_per_row'),
            'levels_per_shelf' => $this->integer('levels_per_shelf'),
        ];
    }

    private function organization(): ?Organization
    {
        $organization = $this->attributes->get('selectedOrganization');

        return $organization instanceof Organization ? $organization : null;
    }
}
