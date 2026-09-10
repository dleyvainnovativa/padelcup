<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Http\RedirectResponse;

/**
 * Sends each role to its own home after login:
 *   player  → /mi-perfil  (their dashboard)
 *   manager/admin → /dashboard (the manager panel)
 *
 * This is the single choke point for ALL login paths (password + social +
 * remember-me), so it also covers Google/Apple sign-ins, not just the form.
 */
class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        $user = $request->user();

        $target = ($user && $user->isPlayer())
            ? route('player.dashboard')
            : route('dashboard');
        return redirect()->intended($target);
    }
}
