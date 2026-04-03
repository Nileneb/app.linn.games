<?php

namespace Database\Migrations\Support;

use Illuminate\Support\Facades\DB;

/**
 * Wiederverwendbare Hilfsmethoden für PostgreSQL-Enum-Operationen in Migrationen.
 *
 * Verwendung: `use Database\Migrations\Support\PgsqlEnumHelpers;`
 * Dann `self::enumExists('my_enum')` in der Migration aufrufen.
 */
trait PgsqlEnumHelpers
{
    /**
     * Prüft, ob ein PostgreSQL-Enum-Typ bereits existiert.
     * Gibt auf SQLite immer false zurück (kein pg_type-Katalog vorhanden).
     */
    protected static function enumExists(string $name): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return false;
        }

        return (bool) DB::scalar(
            'SELECT EXISTS (SELECT 1 FROM pg_type WHERE typname = ?)',
            [$name]
        );
    }
}
