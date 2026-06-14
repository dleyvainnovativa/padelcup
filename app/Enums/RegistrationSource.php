<?php

namespace App\Enums;

enum RegistrationSource: string
{
    case Manager = 'manager'; // manager-created/imported — pay later allowed
    case Self_ = 'self';      // self-registered — payment mandatory before slot valid

    public function label(): string
    {
        return match ($this) {
            self::Manager => 'Manual',
            self::Self_ => 'Autoinscripción',
        };
    }

    /** Manager-created registrations may hold a slot before paying. */
    public function allowsPayLater(): bool
    {
        return $this === self::Manager;
    }
}
