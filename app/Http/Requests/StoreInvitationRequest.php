<?php

namespace App\Http\Requests;

use App\Models\Invitation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Dipakai untuk membuat kode baru dan mengganti kode: keduanya hanya boleh dilakukan Admin organisasi.
class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Dicek di sini (sebelum validasi) agar peran yang tidak berhak langsung 403, bukan melihat galat validasi.
        return $this->user()->can('manageInvitations', $this->route('organization'));
    }

    public function rules(): array
    {
        return [
            'days' => ['required', 'integer', Rule::in(Invitation::DURATIONS)],
        ];
    }

    public function messages(): array
    {
        return [
            'days.required' => 'Pilih masa berlaku kode.',
            'days.in' => 'Masa berlaku harus 1, 7, atau 30 hari.',
        ];
    }
}
