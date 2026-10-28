<?php

return [
    'title' => 'Gazdagépek állapota',
    'refresh' => 'Ellenőrzés újra',
    'hosts' => '{0} Nincs gazdagép|{1} Válaszol: :available / 1 gazdagép|[2,*] Válaszol: :available / :hosts gazdagép',
    'state' => [
        'healthy' => 'Rendben',
        'warning' => 'Nagy terhelés',
        'critical' => 'Kritikus terhelés',
        'maintenance' => 'Karbantartás',
        'partly' => 'Részben elérhetetlen',
        'unavailable' => 'Elérhetetlen',
    ],
    'problem' => [
        'timeout' => 'A Wings nem válaszolt időben',
        'connect' => 'Nem sikerült csatlakozni a Wingshez',
        'refused' => 'A Wings elutasította a kérést (HTTP :status)',
        'unreachable' => 'A Wings nem érhető el (HTTP :status)',
        'http' => 'A Wings válasza: HTTP :status',
        'no_stats' => 'A Wings nem küldött használható számokat',
    ],
];
