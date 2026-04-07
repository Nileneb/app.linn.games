<?php

/**
 * Auto-Chain-Konfiguration für Phasen-Agenten.
 *
 * Nach erfolgreichem Abschluss eines Phase-Jobs wird geprüft, ob die
 * aktuelle Phase einen definierten Nachfolger hat. Falls ja, wird der
 * nächste ProcessPhaseAgentJob automatisch dispatcht.
 *
 * P4 → P5 ist bewusst ausgelassen: P5 erfordert manuellen Paper-Import.
 *
 * Format: phase_nr => ['next_phase' => int, 'agent_config_key' => string, 'label' => string]
 */
return [
    1 => [
        'next_phase'       => 2,
        'agent_config_key' => 'scoping_mapping_agent',
        'label'            => '🧭 KI: Mapping schärfen',
    ],
    2 => [
        'next_phase'       => 3,
        'agent_config_key' => 'scoping_mapping_agent',
        'label'            => '🗂️ KI: Datenbankauswahl schärfen',
    ],
    3 => [
        'next_phase'       => 4,
        'agent_config_key' => 'search_agent',
        'label'            => '🔍 KI: Suchstrings generieren',
    ],
    4 => [
        'next_phase'       => null, // P4 → P5: kein Auto-Chain — P5 erfordert manuellen Paper-Import
        'agent_config_key' => 'search_agent',
        'label'            => '🔍 KI: Suchstrings optimieren',
    ],
    5 => [
        'next_phase'       => 6,
        'agent_config_key' => 'review_agent',
        'label'            => '📝 KI: Codierung starten',
    ],
    6 => [
        'next_phase'       => 7,
        'agent_config_key' => 'review_agent',
        'label'            => '🧠 KI: Synthese & Report',
    ],
    7 => [
        'next_phase'       => 8,
        'agent_config_key' => 'review_agent',
        'label'            => '📋 KI: Dokumentation finalisieren',
    ],

    'thresholds' => [
        1 => [
            'min_components' => 3,
            'blocking'       => false,
            'warning'        => 'Mindestens 3 P1-Komponenten empfohlen für robuste Suchstrategie',
            'agent_check'    => false,
        ],
        2 => [
            'min_cluster'  => 1,
            'min_mapping'  => 1,
            'blocking'     => false,
            'warning'      => 'Mindestens 1 Cluster und 1 Mapping-Komponente empfohlen',
            'agent_check'  => false,
        ],
        3 => [
            'min_databases' => 1,
            'blocking'      => false,
            'warning'       => 'Mindestens 1 Datenbank in der Matrix empfohlen',
            'agent_check'   => false,
        ],
        4 => [
            'min_searchstrings' => 1,
            'blocking'          => false,
            'warning'           => 'Mindestens 1 Suchstring empfohlen vor Paper-Import',
            'agent_check'       => true,
        ],
        5 => [
            'min_treffer' => 5,
            'blocking'    => false,
            'warning'     => 'Mindestens 5 Treffer für Phase 6 empfohlen',
            'agent_check' => false,
        ],
        6 => [
            'min_assessments' => 1,
            'blocking'        => true,
            'warning'         => 'Mindestens 1 Qualitätsbewertung erforderlich um fortzufahren',
            'agent_check'     => false,
        ],
        7 => [
            'min_extractions' => 1,
            'blocking'        => true,
            'warning'         => 'Mindestens 1 Datenextraktion erforderlich um fortzufahren',
            'agent_check'     => false,
        ],
    ],
];
