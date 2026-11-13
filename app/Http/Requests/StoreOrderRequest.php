<?php

namespace App\Http\Requests;

use App\Enums\ServiceKind;
use App\Models\Service;
use App\Models\ServicePrice;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $businessId = $this->user()->business_id;

        return [
            'plate' => ['required', 'string', 'max:10'],
            'vehicle_type_id' => ['required', 'integer', Rule::exists('vehicle_types', 'id')->where('business_id', $businessId)],
            'customer_name' => ['nullable', 'string', 'max:100'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')
                ->where('business_id', $businessId)
                ->where('kind', ServiceKind::Main->value)
                ->where('active', true)],
            'extra_ids' => ['nullable', 'array'],
            'extra_ids.*' => ['integer', Rule::exists('services', 'id')
                ->where('business_id', $businessId)
                ->where('kind', ServiceKind::Extra->value)
                ->where('active', true)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $priced = ServicePrice::where('vehicle_type_id', $this->integer('vehicle_type_id'))
                ->pluck('service_id')
                ->all();

            if (! in_array($this->integer('service_id'), $priced, true)) {
                $validator->errors()->add('service_id', 'Este servicio no tiene precio para ese tipo de vehículo.');
            }

            $missing = collect($this->input('extra_ids', []))
                ->reject(fn ($id) => in_array((int) $id, $priced, true))
                ->map(fn ($id) => Service::find($id)?->name)
                ->filter();

            if ($missing->isNotEmpty()) {
                $validator->errors()->add('extra_ids', 'Sin precio para ese tipo de vehículo: '.$missing->implode(', ').'.');
            }
        }];
    }
}
