<?php

namespace App\Console\Commands;

use App\Models\Pair;
use App\Models\Payment;
use App\Models\PairInvitation;
use App\Models\Registration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Safely delete a registration and everything tied to it, so the same pair can
 * register again during testing. Removes (in FK-safe order):
 *   payments → invitations → registration → pair → (optionally) the players.
 *
 * This is a TEST/DEV helper. It force-deletes (bypasses soft deletes) so the
 * slate is truly clean.
 *
 *   php artisan reg:delete 9                 (dry run — shows what it would do)
 *   php artisan reg:delete 9 --force          (delete reg + pair, keep players)
 *   php artisan reg:delete 9 --force --players (also delete the players)
 */
class DeleteRegistration extends Command
{
    protected $signature = 'reg:delete {id : Registration ID}
                            {--force : Actually delete}
                            {--players : Also delete the two players}';

    protected $description = 'Safely delete a registration + payments + pair (test helper)';

    public function handle(): int
    {
        $registration = Registration::withTrashed()
            ->with(['pair.player1', 'pair.player2', 'payments', 'invitation'])
            ->find($this->argument('id'));

        if (! $registration) {
            $this->error("No existe la inscripción #{$this->argument('id')}.");
            return self::FAILURE;
        }

        $pair = $registration->pair;
        $payments = $registration->payments;
        $invitation = $registration->invitation;

        $this->line("Inscripción #{$registration->id}");
        $this->line("  Pareja:      " . ($pair?->name() ?? '—') . " (pair #{$pair?->id})");
        $this->line("  Pagos:       {$payments->count()}");
        $this->line("  Invitación:  " . ($invitation ? "#{$invitation->id}" : 'ninguna'));
        if ($this->option('players')) {
            $this->line("  Jugadores:   " . collect([$pair?->player1?->name, $pair?->player2?->name])->filter()->implode(', '));
        }

        if (! $this->option('force')) {
            $this->newLine();
            $this->comment('Simulación. Ejecuta con --force para eliminar.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($registration, $pair, $payments, $invitation) {
            // 1) Payments (reference registration + player)
            Payment::where('registration_id', $registration->id)->forceDelete();

            // 2) Invitation (references pair + registration)
            if ($invitation) {
                PairInvitation::where('id', $invitation->id)->forceDelete();
            }

            // 3) Registration
            Registration::withTrashed()->where('id', $registration->id)->forceDelete();

            // 4) Players (optional) — only if requested. Do BEFORE pair would
            //    violate FK, so detach by deleting pair first if not keeping.
            $player1 = $pair?->player1;
            $player2 = $pair?->player2;

            // 5) Pair
            if ($pair) {
                Pair::withTrashed()->where('id', $pair->id)->forceDelete();
            }

            // 6) Players last (now nothing references them)
            if ($this->option('players')) {
                foreach ([$player1, $player2] as $player) {
                    // Don't delete players linked to a real login account.
                    if ($player && blank($player->user_id)) {
                        $player->forceDelete();
                    }
                }
            }
        });

        $this->info("Inscripción #{$registration->id} eliminada. Ya puedes registrar la pareja de nuevo.");
        return self::SUCCESS;
    }
}
