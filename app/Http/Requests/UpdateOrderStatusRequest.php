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

    private function isCashDelivery(): bool
    {
        return $this->input('status') === OrderStatus::Delivered->value
            && $this->input('payment_method') === PaymentMethod::Cash->value;
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
            // Cash is the only method that records how much the customer handed over.
            'cash_received' => [
                Rule::requiredIf($this->isCashDelivery()),
                'prohibited_unless:payment_method,'.PaymentMethod::Cash->value,
                'nullable',
                'integer',
                'min:0',
                'max:10000000',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cash_received.required' => 'Escribe cuánto efectivo entregó el cliente.',
            'cash_received.integer' => 'Escribe el efectivo como número entero, sin puntos ni comas.',
            'cash_received.min' => 'El efectivo recibido no puede ser negativo.',
            'cash_received.max' => 'El efectivo recibido es demasiado alto.',
            'cash_received.prohibited_unless' => 'El efectivo recibido solo aplica al pago en efectivo.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->has('status')) {
                return;
            }

            $order = $this->route('order');

            if ($order->status->next()?->value !== $this->input('status')) {
                $validator->errors()->add('status', 'Esa orden no puede pasar a ese estado.');

                return;
            }

            if ($this->isCashDelivery() && ! $validator->errors()->has('cash_received')
                && $this->integer('cash_received') < $order->total) {
                $validator->errors()->add('cash_received', 'El efectivo recibido no alcanza para cubrir el total.');
            }
        }];
    }
}
