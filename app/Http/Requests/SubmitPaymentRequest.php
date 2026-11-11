<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tagihan organisasi lain = 404 (findOrFail)
        $invoice = Invoice::inOrganization($this->route('organization'))
            ->with('member')
            ->findOrFail($this->route('invoice'));

        // Hanya pemilik tagihan yang mengajukan atau mengubah bukti
        $owner = $invoice->member->user_id;

        return $owner !== null && (int) $owner === (int) $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'method' => ['required', Rule::in([Invoice::METHOD_TRANSFER, Invoice::METHOD_TUNAI])],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'method.required' => 'Pilih cara membayar: transfer atau tunai.',
            'method.in' => 'Pilih cara membayar: transfer atau tunai.',
            'proof.file' => 'Bukti harus berupa berkas.',
            'proof.mimes' => 'Bukti harus berformat JPG, PNG, atau PDF.',
            'proof.max' => 'Ukuran bukti maksimal 2 MB. Perkecil berkasnya lalu coba lagi.',
            'proof.uploaded' => 'Bukti gagal diunggah. Pastikan ukurannya maksimal 2 MB, lalu coba lagi.',
        ];
    }
}
