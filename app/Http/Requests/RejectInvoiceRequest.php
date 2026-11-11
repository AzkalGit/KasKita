<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageInvoices', $this->route('organization'));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge(['reason' => trim($this->input('reason'))]);
        }
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Isi alasan penolakan agar anggota tahu apa yang perlu diperbaiki.',
            'reason.max' => 'Alasan penolakan maksimal 255 karakter.',
        ];
    }
}
