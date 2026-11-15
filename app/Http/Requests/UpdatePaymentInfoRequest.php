<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageSettings', $this->route('organization'));
    }

    public function rules(): array
    {
        return [
            'payment_info' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['payment_info' => 'info pembayaran'];
    }
}
