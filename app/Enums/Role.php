<?php

namespace App\Enums;

enum Role: string
{
    case Patient = 'patient';
    case Doctor = 'doctor';
    case Pharmacy = 'pharmacy';
    case Admin = 'admin';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Roles a visitor may choose at self-registration. */
    public static function registrable(): array
    {
        return [self::Patient, self::Doctor, self::Pharmacy];
    }

    /** Roles that must be approved by an admin before use. */
    public function needsApproval(): bool
    {
        return in_array($this, [self::Doctor, self::Pharmacy], true);
    }
}
