<?php

namespace App\Http\Requests;

use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Plates are compared normalized (uppercase, no spaces), exactly as they are stored. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('plate'))) {
            $this->merge(['plate' => Vehicle::normalizePlate($this->input('plate'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $businessId = $this->user()->business_id;

        return [
            'plate' => ['required', 'string', 'max:10', Rule::unique('vehicles', 'plate')
                ->where('business_id', $businessId)
                ->ignore($this->route('vehicle')->id)],
            'vehicle_type_id' => ['required', 'integer', Rule::exists('vehicle_types', 'id')->where('business_id', $businessId)],
            'customer_name' => ['nullable', 'string', 'max:100'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'plate.unique' => 'Ya existe otro vehículo con esa placa.',
            'plate.required' => 'Escribe la placa.',
            'plate.max' => 'La placa es demasiado larga.',
        ];
    }
}
