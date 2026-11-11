<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageInvoices', $this->route('organization'));
    }

    public function rules(): array
    {
        return [
            'period' => ['required', 'regex:/^20\d{2}-(0[1-9]|1[0-2])$/'],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'period.required' => 'Pilih bulan tagihan.',
            'period.regex' => 'Pilih bulan tagihan dengan format yang benar.',
            'amount.required' => 'Isi jumlah iuran.',
            'amount.integer' => 'Isi jumlah dengan angka bulat tanpa titik atau koma, misalnya 25000.',
            'amount.min' => 'Jumlah minimal Rp 1.',
        ];
    }
}
