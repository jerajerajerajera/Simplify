<?php

return [
    'title' => 'Stav hostiteľov',
    'refresh' => 'Skontrolovať znova',
    'hosts' => '{0} Žiadni hostitelia|{1} Odpovedá :available z 1 hostiteľa|[2,*] Odpovedajúci hostitelia: :available z :hosts',
    'state' => [
        'healthy' => 'V poriadku',
        'warning' => 'Vysoké zaťaženie',
        'critical' => 'Kritické zaťaženie',
        'maintenance' => 'Údržba',
        'partly' => 'Čiastočne nedostupné',
        'unavailable' => 'Nedostupné',
    ],
    'problem' => [
        'timeout' => 'Wings neodpovedal včas',
        'connect' => 'Nepodarilo sa pripojiť k Wings',
        'refused' => 'Wings požiadavku odmietol (HTTP :status)',
        'unreachable' => 'Wings je nedostupný (HTTP :status)',
        'http' => 'Wings odpovedal HTTP :status',
        'no_stats' => 'Wings neposlal použiteľné čísla',
    ],
];
