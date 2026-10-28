<?php

return [
    'title' => 'Värdarnas hälsa',
    'refresh' => 'Kontrollera igen',
    'hosts' => '{0} Inga värdar|{1} :available av 1 värd svarar|[2,*] :available av :hosts värdar svarar',
    'state' => [
        'healthy' => 'Frisk',
        'warning' => 'Hög belastning',
        'critical' => 'Kritisk belastning',
        'maintenance' => 'Underhåll',
        'partly' => 'Delvis otillgänglig',
        'unavailable' => 'Otillgänglig',
    ],
    'problem' => [
        'timeout' => 'Wings svarade inte i tid',
        'connect' => 'Kunde inte ansluta till Wings',
        'refused' => 'Wings nekade begäran (HTTP :status)',
        'unreachable' => 'Wings går inte att nå (HTTP :status)',
        'http' => 'Wings svarade med HTTP :status',
        'no_stats' => 'Wings skickade inga användbara siffror',
    ],
];
