<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageSettings', $this->route('organization'));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->name)) {
            $this->merge(['name' => trim($this->name)]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:100',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $duplicate = Category::where('organization_id', $this->route('organization')->id)
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $value)])
                        ->when($this->route('category'), fn ($q, $category) => $q->where('id', '!=', $category->id))
                        ->exists();

                    if ($duplicate) {
                        $fail('Kategori dengan nama ini sudah ada.');
                    }
                },
            ],
        ];
    }
}
