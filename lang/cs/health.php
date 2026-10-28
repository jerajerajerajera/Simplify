<?php

return [
    'title' => 'Stav hostitelů',
    'refresh' => 'Zkontrolovat znovu',
    'hosts' => '{0} Žádní hostitelé|{1} Odpovídá :available z 1 hostitele|[2,*] Odpovídající hostitelé: :available z :hosts',
    'state' => [
        'healthy' => 'V pořádku',
        'warning' => 'Vysoká zátěž',
        'critical' => 'Kritická zátěž',
        'maintenance' => 'Údržba',
        'partly' => 'Částečně nedostupné',
        'unavailable' => 'Nedostupné',
    ],
    'problem' => [
        'timeout' => 'Wings neodpověděl včas',
        'connect' => 'Nepodařilo se připojit k Wings',
        'refused' => 'Wings požadavek odmítl (HTTP :status)',
        'unreachable' => 'Wings je nedostupný (HTTP :status)',
        'http' => 'Wings odpověděl HTTP :status',
        'no_stats' => 'Wings neposlal použitelná čísla',
    ],
];
