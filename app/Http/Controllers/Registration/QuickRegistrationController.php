<?php

namespace App\Http\Controllers\Registration;

use App\Http\Controllers\Controller;
use App\Models\PairInvitation;
use App\Services\Registration\SelfRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Quick-register link: a partner lands here via token, enters name + email,
 * and pays — no account required. This is flow #3 (and the shareable side of
 * flow #2). Public route, guarded only by the unguessable token.
 */
class QuickRegistrationController extends Controller
{
    public function __construct(private SelfRegistrationService $service) {}

    /** Landing page for the invitation token. */
    public function show(PairInvitation $invitation)
    {
        if (! $invitation->isPending() || $invitation->isExpired()) {
            return view('registration.invitation-invalid', compact('invitation'));
        }

        $invitation->load(['pair.player1', 'registration.category.tournament']);

        return view('registration.quick-register', compact('invitation'));
    }

    /** Partner submits their details; creates charge and goes to payment. */
    public function store(Request $request, PairInvitation $invitation)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => 'Debes aceptar los términos y el aviso de privacidad.',
        ]);

        try {
            $result = $this->service->acceptInvitation($invitation, [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
            ]);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        // Reuse the same payment page; pass the single partner charge.
        return redirect()->route('registration.pay', $result['registration']);
    }
}
