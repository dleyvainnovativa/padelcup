<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Payment\StripeConnectService;
use Illuminate\Http\Request;

class ConnectController extends Controller
{
    public function __construct(private StripeConnectService $connect) {}

    /** Onboarding status page. */
    public function index(Request $request)
    {
        $manager = $request->user();

        // Refresh status if we have an account but charges aren't enabled yet.
        if ($manager->stripe_account_id && ! $manager->stripe_charges_enabled) {
            $this->connect->syncAccountStatus($manager);
            $manager->refresh();
        }

        return view('dashboard.connect.index', compact('manager'));
    }

    /** Kick off (or resume) Stripe Express onboarding. */
    public function start(Request $request)
    {
        $url = $this->connect->onboardingLink($request->user());

        return redirect()->away($url);
    }

    /** Stripe sends the manager back here after onboarding. */
    public function return(Request $request)
    {
        $this->connect->syncAccountStatus($request->user());

        return redirect()
            ->route('connect.index')
            ->with('status', 'Conexión con Stripe actualizada.');
    }

    /** Stripe sends here if the link expired / needs refreshing. */
    public function refresh(Request $request)
    {
        $url = $this->connect->onboardingLink($request->user());

        return redirect()->away($url);
    }
}
