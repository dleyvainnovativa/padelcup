<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class OAuthController extends Controller
{
    /** Providers we allow. */
    private const PROVIDERS = ['google', 'apple'];

    /** Redirect the user to the provider's OAuth screen. */
    public function redirect(string $provider)
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle the provider callback.
     *
     * Identity stays in OUR users table — the provider only verifies the
     * credential. We match on provider+provider_id first, then fall back to
     * email so a user who signed up with a password can also use Google later.
     */
    public function callback(string $provider)
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        try {
            $oauthUser = Socialite::driver($provider)->user();
        } catch (\Throwable $e) {
            return redirect()->route('login')
                ->withErrors(['email' => 'No se pudo iniciar sesión con ' . ucfirst($provider) . '. Intenta de nuevo.']);
        }

        // 1) Existing link by provider id
        $user = User::where('provider', $provider)
            ->where('provider_id', $oauthUser->getId())
            ->first();

        // 2) Otherwise match by email (link the social account to the account)
        if (! $user && $oauthUser->getEmail()) {
            $user = User::where('email', $oauthUser->getEmail())->first();
        }

        if ($user) {
            // Attach provider details if missing
            if (! $user->provider) {
                $user->forceFill([
                    'provider' => $provider,
                    'provider_id' => $oauthUser->getId(),
                ])->save();
            }
        } else {
            // 3) Brand new account. Social signups implicitly accept terms here.
            $user = User::create([
                'name' => $oauthUser->getName() ?: ($oauthUser->getNickname() ?: 'Usuario'),
                'email' => $oauthUser->getEmail(),
                'provider' => $provider,
                'provider_id' => $oauthUser->getId(),
                'password' => null, // no password for social-only accounts
                'role' => 'player',
                'email_verified_at' => now(), // provider already verified the email
                'terms_accepted_at' => now(),
                'terms_version' => config('app.terms_version', '1.0'),
            ]);
        }

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }
}
