<?php

namespace App\Http\Requests\Pair;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

class StorePairRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isManager() || $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        // Player 2 is only required for doubles categories. For singles the
        // form omits those fields entirely, so they must be optional (and are
        // ignored by RegistrationService::createManagerPair).
        $isSingles = $this->routeCategory()?->isSingles() ?? false;

        $player2Rules = $isSingles
            ? [
                'player2_id' => ['nullable', 'integer', 'exists:players,id'],
                'player2_name' => ['nullable', 'string', 'max:255'],
                'player2_email' => ['nullable', 'email', 'max:255'],
                'player2_phone' => ['nullable', 'string', 'max:30'],
            ]
            : [
                'player2_id' => ['nullable', 'integer', 'exists:players,id'],
                'player2_name' => ['required_without:player2_id', 'nullable', 'string', 'max:255'],
                'player2_email' => ['nullable', 'email', 'max:255'],
                'player2_phone' => ['nullable', 'string', 'max:30'],
            ];

        return array_merge([
            // Player 1
            'player1_id' => ['nullable', 'integer', 'exists:players,id'],
            'player1_name' => ['required_without:player1_id', 'nullable', 'string', 'max:255'],
            'player1_email' => ['nullable', 'email', 'max:255'],
            'player1_phone' => ['nullable', 'string', 'max:30'],

            'mark_paid' => ['nullable', 'boolean'],
        ], $player2Rules);
    }

    /** The category bound to the current route (for format-aware rules). */
    private function routeCategory(): ?Category
    {
        $cat = $this->route('category');

        return $cat instanceof Category ? $cat : null;
    }

    /** Shape into the player def arrays RegistrationService expects. */
    public function player1Def(): array
    {
        return [
            'player_id' => $this->input('player1_id'),
            'name' => $this->input('player1_name'),
            'email' => $this->input('player1_email'),
            'phone' => $this->input('player1_phone'),
        ];
    }

    public function player2Def(): array
    {
        return [
            'player_id' => $this->input('player2_id'),
            'name' => $this->input('player2_name'),
            'email' => $this->input('player2_email'),
            'phone' => $this->input('player2_phone'),
        ];
    }
}
