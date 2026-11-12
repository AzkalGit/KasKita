<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageTransactions', $this->route('organization'));
    }

    public function rules(): array
    {
        $organization = $this->route('organization');

        return [
            'type' => ['required', Rule::in(['masuk', 'keluar'])],
            // Kategori harus milik organisasi ini. Kategori Iuran diisi otomatis oleh modul iuran.
            'category_id' => [
                'required', 'integer',
                Rule::exists('categories', 'id')->where(
                    fn ($q) => $q->where('organization_id', $organization->id)->where('is_system', false)
                ),
            ],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'tanggal' => [
                'bail', 'required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01',
                // F2: tanggal baru tidak boleh jatuh di bulan yang sudah ditutup
                function (string $attribute, mixed $value, Closure $fail) use ($organization) {
                    if ($organization->isPeriodClosed($value)) {
                        $fail('Bulan itu sudah ditutup. Minta Admin membuka kembali bulan itu di halaman Laporan.');
                    }
                },
            ],
            'description' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'Pilih kategori.',
            'category_id.exists' => 'Pilih salah satu kategori yang tersedia. Kategori Iuran diisi otomatis dari tagihan.',
            'amount.integer' => 'Isi jumlah dengan angka bulat tanpa titik atau koma, misalnya 25000.',
            'amount.min' => 'Jumlah minimal Rp 1.',
            'tanggal.date_format' => 'Isi tanggal dengan format yang benar.',
        ];
    }
}
