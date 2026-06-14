<?php

use App\Http\Controllers\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webhook Routes
|--------------------------------------------------------------------------
| These must be EXEMPT from CSRF (verified by Stripe signature instead).
| In bootstrap/app.php, add the path to the CSRF "except" list:
|
|   ->withMiddleware(function (Middleware $middleware) {
|       $middleware->validateCsrfTokens(except: ['stripe/webhook']);
|   })
|
| Register this file in bootstrap/app.php:
|   ->withRouting(
|       web: __DIR__.'/../routes/web.php',
|       then: function () {
|           Route::middleware('web')->group(base_path('routes/webhooks.php'));
|       },
|   )
| (or simply require it from web.php). It intentionally has no 'auth'.
*/

Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])
    ->name('stripe.webhook');
