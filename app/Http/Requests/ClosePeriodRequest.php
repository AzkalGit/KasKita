<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClosePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('closePeriods', $this->route('organization'));
    }

    public function rules(): array
    {
        return [
            'period' => ['required', 'regex:/^20\d{2}-(0[1-9]|1[0-2])$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'period.required' => 'Pilih bulan yang akan ditutup.',
            'period.regex' => 'Pilih bulan dengan format yang benar.',
        ];
    }
}
