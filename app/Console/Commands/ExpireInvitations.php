<?php

namespace App\Console\Commands;

use App\Enums\RegistrationStatus;
use App\Models\Registration;
use App\Services\Registration\ExpiryResolverService;
use Illuminate\Console\Command;

/**
 * Sweeps self-registration holds whose TTL has passed and resolves them per the
 * tournament's expiry policy. Schedule this every few minutes.
 *
 *   php artisan invitations:expire
 *
 * Schedule (routes/console.php or bootstrap/app.php):
 *   Schedule::command('invitations:expire')->everyFiveMinutes();
 */
class ExpireInvitations extends Command
{
    protected $signature = 'invitations:expire';

    protected $description = 'Resolve expired self-registration holds per tournament policy';

    public function handle(ExpiryResolverService $resolver): int
    {
        $expired = Registration::query()
            ->where('status', RegistrationStatus::PendingPayment->value)
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<', now())
            ->with(['category.tournament', 'invitation', 'payments'])
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No hay inscripciones vencidas.');
            return self::SUCCESS;
        }

        foreach ($expired as $registration) {
            $resolver->resolve($registration);
            $this->line("Resuelta inscripción #{$registration->id}");
        }

        $this->info("Procesadas {$expired->count()} inscripciones vencidas.");
        return self::SUCCESS;
    }
}
