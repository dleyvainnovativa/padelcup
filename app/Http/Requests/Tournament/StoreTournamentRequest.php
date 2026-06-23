<?php

namespace App\Http\Requests\Tournament;

use App\Enums\ExpiryPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreTournamentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isManager() || $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'rules' => ['nullable', 'string'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'play_start' => ['nullable', 'date_format:H:i'],
            'play_end' => ['nullable', 'date_format:H:i', 'after:play_start'],
            'match_duration_minutes' => ['nullable', 'integer', 'min:30', 'max:240'],
            'cover_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'registration_opens_at' => ['nullable', 'date'],
            'registration_closes_at' => ['nullable', 'date', 'after_or_equal:registration_opens_at'],
            'invitation_ttl_hours' => ['nullable', 'integer', 'min:1', 'max:336'],
            'expiry_policy' => ['nullable', new Enum(ExpiryPolicy::class)],
            'platform_fee_centavos' => ['nullable', 'integer', 'min:0'],
            'is_listed' => ['nullable', 'boolean'],
            'hide_global_ads' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'ends_on.after_or_equal' => 'La fecha de fin no puede ser anterior al inicio.',
            'registration_closes_at.after_or_equal' => 'El cierre de inscripción no puede ser anterior a la apertura.',
        ];
    }
}
