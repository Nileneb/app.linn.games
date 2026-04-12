<?php

return [
    /*
     | Geoblocking — Ländercodes, die keinen Zugang zur Registrierung erhalten.
     | ISO 3166-1 alpha-2 Codes (2 Buchstaben, Großbuchstaben).
     |
     | Rechtliche Grundlage: Art. 6(1)(f) DSGVO (Berechtigtes Interesse: Schutz
     | vor automatisierten Angriffen und Missbrauch).
     |
     | Auf true setzen um Geoblocking zu aktivieren.
     */
    'geoblocking_enabled' => env('GEOBLOCKING_ENABLED', false),

    'blocked_countries' => array_filter(
        explode(',', env('GEOBLOCKING_BLOCKED_COUNTRIES', ''))
    ),
];
