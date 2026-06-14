<?php

namespace App\Http\Controllers\Registration;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Registration;
use App\Services\Registration\SelfRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SelfRegistrationController extends Controller
{
    public function __construct(private SelfRegistrationService $service) {}

    /** Registration form for a category (must be logged in as a player). */
    public function create(Category $category)
    {
        $category->load('tournament');

        return view('registration.create', compact('category'));
    }

    /** Begin self-registration; redirects to the payment page. */
    public function store(Request $request, Category $category)
    {
        $data = $request->validate([
            'flow' => ['required', 'in:pay_both,invite'],
            'player1_name' => ['required', 'string', 'max:255'],
            'player1_phone' => ['nullable', 'string', 'max:30'],
            // Pay-both requires partner details; invite requires at most an email.
            'player2_name' => ['required_if:flow,pay_both', 'nullable', 'string', 'max:255'],
            'player2_email' => ['nullable', 'email', 'max:255'],
            'player2_phone' => ['nullable', 'string', 'max:30'],
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => 'Debes aceptar los términos y el aviso de privacidad.',
        ]);

        try {
            $result = $this->service->begin(
                category: $category,
                registrant: $request->user(),
                player1: [
                    'name' => $data['player1_name'],
                    'phone' => $data['player1_phone'] ?? null,
                ],
                player2: $data['flow'] === 'pay_both' ? [
                    'name' => $data['player2_name'],
                    'email' => $data['player2_email'] ?? null,
                    'phone' => $data['player2_phone'] ?? null,
                ] : ['email' => $data['player2_email'] ?? null],
                flow: $data['flow'],
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        // Collect the registrant's charge(s) on the payment page.
        return redirect()->route('registration.pay', $result['registration']);
    }

    /** Redirect to the pending charge's Stripe Checkout page. */
    public function pay(Registration $registration)
    {
        abort_unless($registration->source->value === 'self', 404);

        $registration->load(['payments', 'category.tournament']);

        // The single pending charge (pay-both = one combined session;
        // invite = the registrant's one charge). Distinct session url.
        $next = $registration->payments
            ->where('status', \App\Enums\PaymentStatus::Pending)
            ->sortBy('id')
            ->first();

        if (! $next || empty($next->meta['checkout_url'])) {
            return redirect()->route('registration.confirmation', $registration);
        }

        return redirect()->away($next->meta['checkout_url']);
    }

    /** Confirmation page after payment is submitted (status set by webhook). */
    public function confirmation(Registration $registration)
    {
        $registration->load(['category.tournament', 'pair', 'invitation']);

        return view('registration.confirmation', compact('registration'));
    }
}
