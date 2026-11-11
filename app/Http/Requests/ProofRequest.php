<?php

namespace App\Http\Requests;

class ProofRequest extends SubmitPaymentRequest
{
    public function rules(): array
    {
        return [
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'proof.required' => 'Pilih berkas bukti (JPG, PNG, atau PDF) terlebih dahulu.',
        ];
    }
}
