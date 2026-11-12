<?php

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'payment_method' => [
                Rule::requiredIf($this->input('status') === OrderStatus::Delivered->value),
                'nullable',
                Rule::enum(PaymentMethod::class),
            ],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->has('status')) {
                return;
            }

            $next = $this->route('order')->status->next();

            if ($next?->value !== $this->input('status')) {
                $validator->errors()->add('status', 'Esa orden no puede pasar a ese estado.');
            }
        }];
    }
}
