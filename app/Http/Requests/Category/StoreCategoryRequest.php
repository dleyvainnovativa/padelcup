<?php

namespace App\Http\Requests\Category;

use App\Enums\CategoryFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isManager() || $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'format' => ['required', new Enum(CategoryFormat::class)],
            'group_format' => ['nullable', new Enum(\App\Enums\GroupFormat::class)],
            'mexicano_pairing' => ['nullable', new Enum(\App\Enums\MexicanoPairing::class)],
            'preferred_group_size' => ['required', 'integer', 'in:3,4'],
            'advance_per_group' => ['nullable', 'integer', 'min:1', 'max:5'],
            'extra_qualifiers' => ['nullable', 'integer', 'min:0', 'max:16'],
            'min_pairs' => ['nullable', 'integer', 'min:0'],
            'max_pairs' => ['nullable', 'integer', 'min:1', 'gte:min_pairs'],
            'price_centavos' => ['required', 'integer', 'min:0'],
            'has_third_place' => ['nullable', 'boolean'],
            'whatsapp_group_url' => ['nullable', 'url', 'starts_with:https://chat.whatsapp.com/'],
        ];
    }

    public function messages(): array
    {
        return [
            'preferred_group_size.in' => 'El tamaño de grupo preferido debe ser 3 o 4.',
            'max_pairs.gte' => 'El cupo máximo no puede ser menor al mínimo.',
            'whatsapp_group_url.starts_with' => 'El enlace debe ser un grupo de WhatsApp válido.',
        ];
    }
}
