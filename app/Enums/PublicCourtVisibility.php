<?php

namespace App\Enums;

/**
 * How much court/venue detail the PUBLIC surfaces (public calendar, bracket,
 * results, player page, pantalla) show for a match. The manager dashboard is
 * never affected — organizers always see full court info to run the event.
 */
enum PublicCourtVisibility: string
{
    case Full = 'full';         // sede · cancha  (default — current behaviour)
    case VenueOnly = 'venue_only'; // sede only — the court number is hidden
    case Hidden = 'hidden';     // nothing — court and venue both hidden

    public function label(): string
    {
        return match ($this) {
            self::Full => 'Completa (sede y cancha)',
            self::VenueOnly => 'Solo sede (ocultar número de cancha)',
            self::Hidden => 'Oculta (sin cancha)',
        };
    }

    /** Show the specific court (e.g. "Cancha 1")? Only when Full. */
    public function showsCourtName(): bool
    {
        return $this === self::Full;
    }

    /** Show the venue/sede name? Full and VenueOnly do; Hidden does not. */
    public function showsVenue(): bool
    {
        return $this !== self::Hidden;
    }

    public function isHidden(): bool
    {
        return $this === self::Hidden;
    }
}
