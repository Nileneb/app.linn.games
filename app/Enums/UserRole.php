<?php

namespace App\Enums;

/**
 * User role identifiers for Spatie Permission roles.
 *
 * Centralizes role name constants to avoid string duplication and typos
 * across tests, seeders, and authorization logic.
 */
class UserRole
{
    const ADMIN = 'admin';

    const EDITOR = 'editor';

    const MITGLIED = 'mitglied';

    /**
     * Returns all role names as an array.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::ADMIN,
            self::EDITOR,
            self::MITGLIED,
        ];
    }
}
