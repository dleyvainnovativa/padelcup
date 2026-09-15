<?php

namespace App\Services\Tournament;

use App\Models\Category;
use App\Models\Court;
use App\Models\CourtAvailability;
use App\Models\GameMatch;
use App\Models\Group;
use App\Models\Pair;
use App\Models\Payment;
use App\Models\PhaseWindow;
use App\Models\Player;
use App\Models\PlayerAvailability;
use App\Models\Registration;
use App\Models\Sponsor;
use App\Models\Ad;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Venue;
use App\Enums\TournamentPhase;
use Illuminate\Support\Facades\DB;

/**
 * Full-fidelity tournament export/import as portable JSON.
 *
 * Cross-server safe: every entity carries a temporary "ref" and all foreign
 * keys point at those refs (never DB ids). Import rebuilds the graph in
 * dependency order, remapping refs → new ids.
 *
 * Deliberately NOT carried across servers (see stripping below):
 *   - Stripe ids / connected account (meaningless on another Stripe account)
 *   - image files (only the path strings travel; re-upload on the target)
 *   - user accounts (referenced by email; relinked if present, else null)
 */
class TournamentTransferService
{
    public const FORMAT = 'voleo.tournament';
    public const VERSION = 1;

    /* ======================================================================
       EXPORT
       ====================================================================== */

    public function export(Tournament $t): array
    {
        $t->load([
            'venues.courts.availabilities',
            'phaseWindows',
            'categories.groups.pairs',
            'categories.pairs.player1',
            'categories.pairs.player2',
            'categories.registrations.payments',
            'categories.matches',
            'sponsors',
            'ads',
        ]);

        // Collect every player referenced by any pair, plus the users they and
        // payments/matches reference (exported as email-only for relinking).
        $players = collect();
        $userEmails = collect();

        foreach ($t->categories as $cat) {
            foreach ($cat->pairs as $pair) {
                if ($pair->player1) $players->put($pair->player1->id, $pair->player1);
                if ($pair->player2) $players->put($pair->player2->id, $pair->player2);
            }
        }

        $playerRef = fn($id) => $id ? 'player_' . $id : null;
        $userRef = function ($userId) use (&$userEmails) {
            if (! $userId) return null;
            $u = User::find($userId);
            if (! $u || ! $u->email) return null;
            $userEmails->put($u->email, $u->email);
            return 'user_' . md5($u->email);
        };

        // Players block (attach a user ref when linked).
        $playersOut = $players->values()->map(function (Player $p) use ($userRef) {
            return [
                'ref' => 'player_' . $p->id,
                'name' => $p->name,
                'email' => $p->email,
                'phone' => $p->phone,
                'normalized_name' => $p->normalized_name,
                'user' => $userRef($p->user_id),
            ];
        })->all();

        // Venues → courts → availabilities.
        $venuesOut = $t->venues->map(function (Venue $v) {
            return [
                'ref' => 'venue_' . $v->id,
                'name' => $v->name,
                'address' => $v->address,
                'courts' => $v->courts->map(function (Court $c) {
                    return [
                        'ref' => 'court_' . $c->id,
                        'name' => $c->name,
                        'sort_order' => $c->sort_order,
                        'is_active' => $c->is_active,
                        'availabilities' => $c->availabilities->map(fn(CourtAvailability $a) => [
                            'starts_at' => $this->dt($a->starts_at),
                            'ends_at' => $this->dt($a->ends_at),
                        ])->all(),
                    ];
                })->all(),
            ];
        })->all();

        // Categories with all nested competition data.
        $categoriesOut = $t->categories->map(function (Category $cat) use ($playerRef, $userRef) {
            return [
                'ref' => 'cat_' . $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'format' => $this->enum($cat->format),
                'play_format' => $this->enum($cat->play_format),
                'group_format' => $this->enum($cat->group_format),
                'mexicano_pairing' => $this->enum($cat->mexicano_pairing),
                'preferred_group_size' => $cat->preferred_group_size,
                'advance_per_group' => $cat->advance_per_group,
                'extra_qualifiers' => $cat->extra_qualifiers,
                'min_pairs' => $cat->min_pairs,
                'max_pairs' => $cat->max_pairs,
                'price_centavos' => $cat->price_centavos,
                'registration_opens_at' => $this->dt($cat->registration_opens_at),
                'registration_closes_at' => $this->dt($cat->registration_closes_at),
                'tint' => $cat->tint,
                'has_third_place' => $cat->has_third_place,
                'whatsapp_group_url' => $cat->whatsapp_group_url,
                'category_key' => $cat->category_key,

                'groups' => $cat->groups->map(fn(Group $g) => [
                    'ref' => 'grp_' . $g->id,
                    'name' => $g->name,
                    'position' => $g->position,
                    // pairs are linked via the group_pair pivot, not a column
                    'pairs' => $g->pairs->pluck('id')->map(fn($id) => 'pair_' . $id)->all(),
                ])->all(),

                'pairs' => $cat->pairs->map(fn(Pair $p) => [
                    'ref' => 'pair_' . $p->id,
                    'is_singles' => $p->is_singles,
                    'player1' => $playerRef($p->player1_id),
                    'player2' => $playerRef($p->player2_id),
                    'display_name' => $p->display_name,
                    'seed' => $p->seed,
                    'schedule_preferences' => $p->schedule_preferences,
                ])->all(),

                'registrations' => $cat->registrations->map(function (Registration $r) use ($playerRef, $userRef) {
                    return [
                        'ref' => 'reg_' . $r->id,
                        'pair' => $r->pair_id ? 'pair_' . $r->pair_id : null,
                        'source' => $this->enum($r->source),
                        'status' => $this->enum($r->status),
                        'payment_status' => $this->enum($r->payment_status),
                        'terms_accepted_at' => $this->dt($r->terms_accepted_at),
                        'terms_version' => $r->terms_version,
                        // Payments: financial history only, Stripe ids stripped.
                        'payments' => $r->payments->map(fn(Payment $pay) => [
                            'player' => $playerRef($pay->player_id),
                            'payer_user' => $userRef($pay->payer_user_id),
                            'amount_centavos' => $pay->amount_centavos,
                            'platform_fee_centavos' => $pay->platform_fee_centavos,
                            'status' => $this->enum($pay->status),
                            'refunded_centavos' => $pay->refunded_centavos,
                            'paid_at' => $this->dt($pay->paid_at),
                            'refunded_at' => $this->dt($pay->refunded_at),
                            'meta' => $pay->meta,
                            'imported' => true,
                        ])->all(),
                    ];
                })->all(),

                'matches' => $cat->matches->map(function (GameMatch $m) use ($userRef) {
                    return [
                        'ref' => 'match_' . $m->id,
                        'group' => $m->group_id ? 'grp_' . $m->group_id : null,
                        'pair_a' => $m->pair_a_id ? 'pair_' . $m->pair_a_id : null,
                        'pair_b' => $m->pair_b_id ? 'pair_' . $m->pair_b_id : null,
                        'seed_label_a' => $m->seed_label_a,
                        'seed_label_b' => $m->seed_label_b,
                        'round' => $m->round,
                        'slot' => $m->slot,
                        'feeder_a' => $m->feeder_a_id ? 'match_' . $m->feeder_a_id : null,
                        'feeder_b' => $m->feeder_b_id ? 'match_' . $m->feeder_b_id : null,
                        'feeder_a_source' => $m->feeder_a_source,
                        'feeder_b_source' => $m->feeder_b_source,
                        'is_third_place' => $m->is_third_place,
                        'court' => $m->court_id ? 'court_' . $m->court_id : null,
                        'starts_at' => $this->dt($m->starts_at),
                        'duration_minutes' => $m->duration_minutes,
                        'state' => $this->enum($m->state),
                        'result_type' => $this->enum($m->result_type),
                        'sets' => $m->sets,
                        'winner' => $m->winner_pair_id ? 'pair_' . $m->winner_pair_id : null,
                        'incident_note' => $m->incident_note,
                        'confirmed_at' => $this->dt($m->confirmed_at),
                    ];
                })->all(),
            ];
        })->all();

        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exported_at' => now()->toIso8601String(),
            'source' => ['app' => 'voleo', 'tournament' => $t->slug ?: (string) $t->id],

            'tournament' => [
                'name' => $t->name,
                'description' => $t->description,
                'rules' => $t->rules,
                'logo_path' => $t->logo_path,
                'cover_image_path' => $t->cover_image_path,
                'starts_on' => $this->d($t->starts_on),
                'ends_on' => $this->d($t->ends_on),
                'play_start' => $t->play_start,
                'play_end' => $t->play_end,
                'match_duration_minutes' => $t->match_duration_minutes,
                'min_rest_minutes' => $t->min_rest_minutes,
                'registration_opens_at' => $this->dt($t->registration_opens_at),
                'registration_closes_at' => $this->dt($t->registration_closes_at),
                'invitation_ttl_hours' => $t->invitation_ttl_hours,
                'expiry_policy' => $this->enum($t->expiry_policy),
                'platform_fee_centavos' => $t->platform_fee_centavos,
                'iva_enabled' => $t->iva_enabled,
                'hide_global_ads' => $t->hide_global_ads,
                'is_listed' => $t->is_listed,
                'day_durations' => $t->day_durations,
                'day_hours' => $t->day_hours,
                'tiebreak_order' => $t->tiebreak_order,
                // phase/locked_at intentionally omitted — import comes in as Setup.
            ],

            'users' => $userEmails->values()->map(fn($email) => [
                'ref' => 'user_' . md5($email),
                'email' => $email,
            ])->all(),

            'players' => $playersOut,
            'venues' => $venuesOut,

            'phase_windows' => $t->phaseWindows->map(fn(PhaseWindow $w) => [
                'phase' => $this->enum($w->phase),
                'starts_at' => $this->dt($w->starts_at),
                'ends_at' => $this->dt($w->ends_at),
            ])->all(),

            'player_availability' => PlayerAvailability::where('tournament_id', $t->id)->get()
                ->map(fn(PlayerAvailability $a) => [
                    'normalized_name' => $a->normalized_name,
                    'day' => $this->d($a->day),
                    'unavailable' => $a->unavailable,
                    'earliest_time' => $a->earliest_time,
                    'latest_time' => $a->latest_time,
                ])->all(),

            'categories' => $categoriesOut,

            'sponsors' => $t->sponsors->map(fn(Sponsor $s) => [
                'name' => $s->name,
                'image_path' => $s->image_path,
                'link_url' => $s->link_url,
                'sort_order' => $s->sort_order,
                'is_active' => $s->is_active,
                'is_admin' => $s->is_admin,
                'scope' => $s->scope,
            ])->all(),

            'ads' => $t->ads->map(fn(Ad $a) => [
                'title' => $a->title,
                'image_path' => $a->image_path,
                'link_url' => $a->link_url,
                'scope' => $a->scope,
                'is_active' => $a->is_active,
                'sort_order' => $a->sort_order,
                // clicks reset to 0 on import
            ])->all(),
        ];
    }

    /* ======================================================================
       IMPORT
       ====================================================================== */

    /**
     * Import a decoded payload as a brand-new tournament owned by $managerId.
     * Runs in a transaction; throws on invalid shape.
     *
     * @return Tournament the newly created tournament
     */
    public function import(array $data, int $managerId): Tournament
    {
        $this->assertValid($data);

        return DB::transaction(function () use ($data, $managerId) {
            // Ref → new id maps, filled as we insert in dependency order.
            $userMap = [];   // user ref  → existing user id (by email) or null
            $playerMap = []; // player ref→ new player id
            $courtMap = [];  // court ref → new court id
            $groupMap = [];  // group ref → new group id
            $pairMap = [];   // pair ref  → new pair id
            $matchMap = [];  // match ref → new match id

            // 1) Users — match by email, never create.
            foreach (($data['users'] ?? []) as $u) {
                $existing = User::where('email', $u['email'])->value('id');
                $userMap[$u['ref']] = $existing; // may be null
            }

            // 2) Players — create (or reuse by email if present).
            foreach (($data['players'] ?? []) as $p) {
                $userId = $p['user'] ? ($userMap[$p['user']] ?? null) : null;
                $player = Player::create([
                    'name' => $p['name'],
                    'email' => $p['email'] ?? null,
                    'phone' => $p['phone'] ?? null,
                    'normalized_name' => $p['normalized_name'] ?? null,
                    'user_id' => $userId,
                    'created_by' => $managerId,
                ]);
                $playerMap[$p['ref']] = $player->id;
            }

            // 3) Tournament — fresh slug, current manager, Setup phase.
            $tt = $data['tournament'];
            $tournament = Tournament::create([
                'manager_id' => $managerId,
                'name' => $tt['name'],
                // slug intentionally omitted: the model's creating hook generates a
                // unique one (soft-delete aware), keeping that logic in one place.
                'description' => $tt['description'] ?? null,
                'rules' => $tt['rules'] ?? null,
                'logo_path' => $tt['logo_path'] ?? null,
                'cover_image_path' => $tt['cover_image_path'] ?? null,
                'starts_on' => $tt['starts_on'] ?? null,
                'ends_on' => $tt['ends_on'] ?? null,
                'play_start' => $tt['play_start'] ?? null,
                'play_end' => $tt['play_end'] ?? null,
                'match_duration_minutes' => $tt['match_duration_minutes'] ?? null,
                'min_rest_minutes' => $tt['min_rest_minutes'] ?? null,
                'registration_opens_at' => $tt['registration_opens_at'] ?? null,
                'registration_closes_at' => $tt['registration_closes_at'] ?? null,
                'invitation_ttl_hours' => $tt['invitation_ttl_hours'] ?? null,
                'expiry_policy' => $tt['expiry_policy'] ?? null,
                'platform_fee_centavos' => $tt['platform_fee_centavos'] ?? 0,
                'iva_enabled' => $tt['iva_enabled'] ?? false,
                'hide_global_ads' => $tt['hide_global_ads'] ?? false,
                'is_listed' => false, // imported tournaments start unlisted
                'day_durations' => $tt['day_durations'] ?? null,
                'day_hours' => $tt['day_hours'] ?? null,
                'tiebreak_order' => $tt['tiebreak_order'] ?? null,
                'phase' => TournamentPhase::Setup,
                'locked_at' => null,
            ]);

            // 4) Venues → courts → availabilities.
            foreach (($data['venues'] ?? []) as $v) {
                $venue = Venue::create([
                    'tournament_id' => $tournament->id,
                    'name' => $v['name'],
                    'address' => $v['address'] ?? null,
                ]);
                foreach (($v['courts'] ?? []) as $c) {
                    $court = Court::create([
                        'venue_id' => $venue->id,
                        'name' => $c['name'],
                        'sort_order' => $c['sort_order'] ?? 0,
                        'is_active' => $c['is_active'] ?? true,
                    ]);
                    $courtMap[$c['ref']] = $court->id;
                    foreach (($c['availabilities'] ?? []) as $a) {
                        CourtAvailability::create([
                            'court_id' => $court->id,
                            'starts_at' => $a['starts_at'],
                            'ends_at' => $a['ends_at'],
                        ]);
                    }
                }
            }

            // 5) Phase windows.
            foreach (($data['phase_windows'] ?? []) as $w) {
                PhaseWindow::create([
                    'tournament_id' => $tournament->id,
                    'phase' => $w['phase'],
                    'starts_at' => $w['starts_at'],
                    'ends_at' => $w['ends_at'],
                ]);
            }

            // 6) Player availability (linked by normalized_name string).
            foreach (($data['player_availability'] ?? []) as $a) {
                PlayerAvailability::create([
                    'tournament_id' => $tournament->id,
                    'normalized_name' => $a['normalized_name'],
                    'day' => $a['day'] ?? null,
                    'unavailable' => $a['unavailable'] ?? false,
                    'earliest_time' => $a['earliest_time'] ?? null,
                    'latest_time' => $a['latest_time'] ?? null,
                ]);
            }

            // 7) Categories → groups → pairs → registrations/payments → matches.
            $deferredMatchLinks = []; // [new_match_id => ['feeder_a'=>ref, ...]]

            foreach (($data['categories'] ?? []) as $cat) {
                $category = Category::create([
                    'tournament_id' => $tournament->id,
                    'name' => $cat['name'],
                    // slug omitted: model's creating hook makes it per-tournament unique.
                    'format' => $cat['format'] ?? null,
                    'play_format' => $cat['play_format'] ?? null,
                    'group_format' => $cat['group_format'] ?? null,
                    'mexicano_pairing' => $cat['mexicano_pairing'] ?? null,
                    'preferred_group_size' => $cat['preferred_group_size'] ?? null,
                    'advance_per_group' => $cat['advance_per_group'] ?? null,
                    'extra_qualifiers' => $cat['extra_qualifiers'] ?? 0,
                    'min_pairs' => $cat['min_pairs'] ?? null,
                    'max_pairs' => $cat['max_pairs'] ?? null,
                    'price_centavos' => $cat['price_centavos'] ?? 0,
                    'registration_opens_at' => $cat['registration_opens_at'] ?? null,
                    'registration_closes_at' => $cat['registration_closes_at'] ?? null,
                    'tint' => $cat['tint'] ?? null,
                    'has_third_place' => $cat['has_third_place'] ?? false,
                    'whatsapp_group_url' => $cat['whatsapp_group_url'] ?? null,
                    'category_key' => $cat['category_key'] ?? null,
                ]);

                $groupRefsThisCat = [];
                foreach (($cat['groups'] ?? []) as $g) {
                    $group = Group::create([
                        'category_id' => $category->id,
                        'name' => $g['name'],
                        'position' => $g['position'] ?? 0,
                    ]);
                    $groupMap[$g['ref']] = $group->id;
                    $groupRefsThisCat[$g['ref']] = $g['pairs'] ?? [];
                }

                foreach (($cat['pairs'] ?? []) as $p) {
                    $pair = Pair::create([
                        'category_id' => $category->id,
                        'is_singles' => $p['is_singles'] ?? false,
                        'player1_id' => $p['player1'] ? ($playerMap[$p['player1']] ?? null) : null,
                        'player2_id' => $p['player2'] ? ($playerMap[$p['player2']] ?? null) : null,
                        'display_name' => $p['display_name'] ?? null,
                        'seed' => $p['seed'] ?? null,
                        'schedule_preferences' => $p['schedule_preferences'] ?? null,
                    ]);
                    $pairMap[$p['ref']] = $pair->id;
                }

                // Attach the group_pair pivot now that both sides exist.
                foreach ($groupRefsThisCat as $grpRef => $pairRefs) {
                    $ids = collect($pairRefs)
                        ->map(fn($ref) => $pairMap[$ref] ?? null)
                        ->filter()
                        ->all();
                    if ($ids) {
                        // Preserve source order as pivot position (0..n).
                        $syncData = [];
                        foreach (array_values($ids) as $pos => $pid) {
                            $syncData[$pid] = ['position' => $pos];
                        }
                        Group::find($groupMap[$grpRef])->pairs()->syncWithoutDetaching($syncData);
                    }
                }

                foreach (($cat['registrations'] ?? []) as $r) {
                    $reg = Registration::create([
                        'category_id' => $category->id,
                        'pair_id' => $r['pair'] ? ($pairMap[$r['pair']] ?? null) : null,
                        'source' => $r['source'] ?? null,
                        'status' => $r['status'] ?? null,
                        'payment_status' => $r['payment_status'] ?? null,
                        'terms_accepted_at' => $r['terms_accepted_at'] ?? null,
                        'terms_version' => $r['terms_version'] ?? null,
                    ]);

                    foreach (($r['payments'] ?? []) as $pay) {
                        Payment::create([
                            'registration_id' => $reg->id,
                            'player_id' => $pay['player'] ? ($playerMap[$pay['player']] ?? null) : null,
                            'payer_user_id' => $pay['payer_user'] ? ($userMap[$pay['payer_user']] ?? null) : null,
                            'connected_account_id' => null,        // stripped
                            'amount_centavos' => $pay['amount_centavos'] ?? 0,
                            'platform_fee_centavos' => $pay['platform_fee_centavos'] ?? 0,
                            'stripe_payment_intent_id' => null,    // stripped
                            'stripe_charge_id' => null,            // stripped
                            'stripe_refund_id' => null,            // stripped
                            'status' => $pay['status'] ?? null,
                            'refunded_centavos' => $pay['refunded_centavos'] ?? 0,
                            'paid_at' => $pay['paid_at'] ?? null,
                            'refunded_at' => $pay['refunded_at'] ?? null,
                            'meta' => array_merge((array) ($pay['meta'] ?? []), ['imported' => true]),
                        ]);
                    }
                }

                // Matches pass 1: create with pair/group/court refs resolved.
                foreach (($cat['matches'] ?? []) as $m) {
                    $match = GameMatch::create([
                        'category_id' => $category->id,
                        'group_id' => $m['group'] ? ($groupMap[$m['group']] ?? null) : null,
                        'pair_a_id' => $m['pair_a'] ? ($pairMap[$m['pair_a']] ?? null) : null,
                        'pair_b_id' => $m['pair_b'] ? ($pairMap[$m['pair_b']] ?? null) : null,
                        'seed_label_a' => $m['seed_label_a'] ?? null,
                        'seed_label_b' => $m['seed_label_b'] ?? null,
                        'round' => $m['round'] ?? null,
                        'slot' => $m['slot'] ?? null,
                        'feeder_a_source' => $m['feeder_a_source'] ?? null,
                        'feeder_b_source' => $m['feeder_b_source'] ?? null,
                        'is_third_place' => $m['is_third_place'] ?? false,
                        'court_id' => $m['court'] ? ($courtMap[$m['court']] ?? null) : null,
                        'starts_at' => $m['starts_at'] ?? null,
                        'duration_minutes' => $m['duration_minutes'] ?? null,
                        'state' => $m['state'] ?? null,
                        'result_type' => $m['result_type'] ?? null,
                        'sets' => $m['sets'] ?? null,
                        'incident_note' => $m['incident_note'] ?? null,
                        'confirmed_at' => $m['confirmed_at'] ?? null,
                    ]);
                    $matchMap[$m['ref']] = $match->id;

                    // Defer self-referential links (feeders point at other matches).
                    if (($m['feeder_a'] ?? null) || ($m['feeder_b'] ?? null) || ($m['winner'] ?? null)) {
                        $deferredMatchLinks[$match->id] = [
                            'feeder_a' => $m['feeder_a'] ?? null,
                            'feeder_b' => $m['feeder_b'] ?? null,
                            'winner' => $m['winner'] ?? null,
                        ];
                    }
                }
            }

            // Matches pass 2: backfill feeder/winner ids now that all exist.
            foreach ($deferredMatchLinks as $matchId => $links) {
                GameMatch::where('id', $matchId)->update([
                    'feeder_a_id' => $links['feeder_a'] ? ($matchMap[$links['feeder_a']] ?? null) : null,
                    'feeder_b_id' => $links['feeder_b'] ? ($matchMap[$links['feeder_b']] ?? null) : null,
                    'winner_pair_id' => $links['winner'] ? ($pairMap[$links['winner']] ?? null) : null,
                ]);
            }

            // 8) Sponsors + ads (paths kept; clicks reset).
            foreach (($data['sponsors'] ?? []) as $s) {
                Sponsor::create([
                    'tournament_id' => $tournament->id,
                    'name' => $s['name'],
                    'image_path' => $s['image_path'] ?? null,
                    'link_url' => $s['link_url'] ?? null,
                    'sort_order' => $s['sort_order'] ?? 0,
                    'is_active' => $s['is_active'] ?? true,
                    'is_admin' => $s['is_admin'] ?? false,
                    'scope' => $s['scope'] ?? null,
                ]);
            }
            foreach (($data['ads'] ?? []) as $a) {
                Ad::create([
                    'tournament_id' => $tournament->id,
                    'title' => $a['title'] ?? null,
                    'image_path' => $a['image_path'] ?? null,
                    'link_url' => $a['link_url'] ?? null,
                    'scope' => $a['scope'] ?? null,
                    'is_active' => $a['is_active'] ?? true,
                    'sort_order' => $a['sort_order'] ?? 0,
                    'clicks' => 0,
                ]);
            }

            return $tournament->fresh();
        });
    }

    /* ======================================================================
       Helpers
       ====================================================================== */

    private function assertValid(array $data): void
    {
        if (($data['format'] ?? null) !== self::FORMAT) {
            throw new \InvalidArgumentException('Archivo no reconocido: formato inválido.');
        }
        if ((int) ($data['version'] ?? 0) > self::VERSION) {
            throw new \InvalidArgumentException('Este archivo fue exportado por una versión más nueva de Voleo.');
        }
        if (! isset($data['tournament']['name'])) {
            throw new \InvalidArgumentException('El archivo no contiene un torneo válido.');
        }
    }

    /** Enum → its backing value (string|int) or null. */
    private function enum($v)
    {
        if ($v === null) return null;
        return $v instanceof \BackedEnum ? $v->value : $v;
    }

    /** DateTime → ISO-8601 string or null. */
    private function dt($v): ?string
    {
        if (! $v) return null;
        return $v instanceof \DateTimeInterface ? $v->format(\DateTime::ATOM) : (string) $v;
    }

    /** Date → Y-m-d string or null. */
    private function d($v): ?string
    {
        if (! $v) return null;
        return $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : (string) $v;
    }
}
