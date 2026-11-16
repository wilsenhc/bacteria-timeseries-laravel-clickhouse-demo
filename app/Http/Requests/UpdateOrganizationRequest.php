<?php

namespace App\Http\Requests;

use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Organization $organization */
        $organization = $this->route('organization');

        return $this->user()->can('update', $organization);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Organization $organization */
        $organization = $this->route('organization');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('organizations', 'name')->ignore($organization)],
            'address' => ['required', 'string', 'max:1000'],
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
        ];
    }
}
