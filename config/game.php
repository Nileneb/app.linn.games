<?php

return [
    /*
     * Kill-Meilensteine → Belohnungen
     * type: 'topup'    → amount_cents direkt auf Workspace-Guthaben
     * type: 'discount' → discount_factor wird um den Wert reduziert (max. bis 0.0)
     */
    'kill_rewards' => [
        ['kills' => 100, 'type' => 'topup', 'value' => 100],      // 100 Kills  = 1 €
        ['kills' => 500, 'type' => 'topup', 'value' => 300],      // 500 Kills  = 3 €
        ['kills' => 1000, 'type' => 'topup', 'value' => 500],     // 1000 Kills = 5 €
        ['kills' => 2500, 'type' => 'discount', 'value' => 0.05], // 2500 Kills = 5% Rabatt
        ['kills' => 5000, 'type' => 'discount', 'value' => 0.10], // 5000 Kills = 10% Rabatt
    ],
];
