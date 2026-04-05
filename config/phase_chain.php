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
    // P4 → P5: kein Auto-Chain — P5 erfordert manuellen Paper-Import
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
];
